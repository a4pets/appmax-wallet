<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Account;
use App\Models\Balance;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletWithdrawTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user with account and balance (1000 initial balance for withdrawal tests)
        $this->user = User::factory()->withAccount(1000.0)->create();
        $this->user->refresh();
        $this->token = auth()->login($this->user);
    }

    /**
     * Test user can withdraw valid amount with sufficient balance
     */
    public function test_user_can_withdraw_with_sufficient_balance(): void
    {
        $account = \App\Models\Account::where('user_id', $this->user->id)->first();
        $initialBalance = \DB::table('balances')->where('account_id', $account->id)->value('amount');

        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', [
                'amount' => 250.50,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'transaction' => [
                        'id',
                        'type',
                        'amount',
                        'description',
                        'created_at',
                    ],
                    'new_balance',
                ],
            ])
            ->assertJson([
                'data' => [
                    'transaction' => [
                        'type' => 'withdraw',
                        'amount' => 250.50,
                    ],
                    'new_balance' => $initialBalance - 250.50,
                ],
            ]);

        // Verify balance was updated
        $this->assertDatabaseHas('balances', [
            'account_id' => $account->id,
            'amount' => $initialBalance - 250.50,
        ]);

        // Verify transaction was created
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'amount' => 250.50,
        ]);
    }

    /**
     * Test withdrawal fails with insufficient balance
     */
    public function test_withdraw_fails_with_insufficient_balance(): void
    {
        $account = \App\Models\Account::where('user_id', $this->user->id)->first();

        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', [
                'amount' => 2000, // More than balance (1000)
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'data' => [
                    'code' => 'INSUFFICIENT_BALANCE',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'error',
                    'code',
                ],
            ]);

        // Verify balance was not changed
        $this->assertDatabaseHas('balances', [
            'account_id' => $account->id,
            'amount' => 1000,
        ]);
    }

    /**
     * Test withdrawal fails with negative amount
     */
    public function test_withdraw_fails_with_negative_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', [
                'amount' => -100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test withdrawal fails with zero amount
     */
    public function test_withdraw_fails_with_zero_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', [
                'amount' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test withdrawal fails without amount
     */
    public function test_withdraw_fails_without_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test withdrawal fails without authentication
     */
    public function test_withdraw_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/wallet/withdraw', [
            'amount' => 100,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test user can withdraw entire balance
     */
    public function test_user_can_withdraw_entire_balance(): void
    {
        $account = \App\Models\Account::where('user_id', $this->user->id)->first();
        $fullBalance = \DB::table('balances')->where('account_id', $account->id)->value('amount');

        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', [
                'amount' => $fullBalance,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'new_balance' => 0.0,
                ],
            ]);

        $this->assertDatabaseHas('balances', [
            'account_id' => $account->id,
            'amount' => 0,
        ]);
    }

    /**
     * Test withdrawal with decimal precision
     */
    public function test_withdraw_handles_decimal_precision(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', [
                'amount' => 99.99,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'transaction' => [
                        'amount' => 99.99,
                    ],
                    'new_balance' => 900.01,
                ],
            ]);
    }

    /**
     * Test multiple withdrawals work correctly
     */
    public function test_multiple_withdrawals(): void
    {
        // First withdrawal
        $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', ['amount' => 200]);

        // Second withdrawal
        $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', ['amount' => 300]);

        // Third withdrawal
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', ['amount' => 100]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'new_balance' => 400.0,
                ],
            ]);
    }

    /**
     * Test withdrawal exceeding balance by small amount fails
     */
    public function test_withdraw_exceeding_balance_by_small_amount_fails(): void
    {
        $account = \App\Models\Account::where('user_id', $this->user->id)->first();
        $balance = \DB::table('balances')->where('account_id', $account->id)->value('amount');

        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', [
                'amount' => $balance + 0.01,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'data' => [
                    'code' => 'INSUFFICIENT_BALANCE',
                ],
            ]);
    }

    /**
     * Test withdrawal respects daily limit
     */
    public function test_withdraw_respects_daily_limit(): void
    {
        // Update balance to have enough for large withdrawal
        $account = \App\Models\Account::where('user_id', $this->user->id)->first();
        \DB::table('balances')->where('account_id', $account->id)->update(['amount' => 10000]);

        // Try to withdraw more than daily limit (5000)
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', [
                'amount' => 6000,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'data' => [
                    'code' => 'DAILY_LIMIT_EXCEEDED',
                ],
            ]);
    }

    /**
     * Test withdrawal creates correct transaction metadata
     */
    public function test_withdraw_creates_correct_transaction_metadata(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', [
                'amount' => 150.75,
            ]);

        $response->assertStatus(201);

        $account = \App\Models\Account::where('user_id', $this->user->id)->first();
        $transaction = Transaction::with('transactionType')->latest()->first();

        $this->assertEquals('WITHDRAW', $transaction->transactionType->code);
        $this->assertEquals(150.75, $transaction->amount);
        $this->assertEquals($account->id, $transaction->account_id);
        $this->assertNull($transaction->related_account_id);
        $this->assertStringContainsString('Saque', $transaction->description);
    }
}
