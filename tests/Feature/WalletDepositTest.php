<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Account;
use App\Models\Balance;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletDepositTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user with account and balance
        $this->user = User::factory()->withAccount(0.0)->create();
        $this->user->refresh();
        $this->token = auth()->login($this->user);
    }

    /**
     * Test user can deposit valid amount
     */
    public function test_user_can_deposit_valid_amount(): void
    {
        $account = \App\Models\Account::where('user_id', $this->user->id)->first();
        $initialBalance = \DB::table('balances')->where('account_id', $account->id)->value('amount');

        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', [
                'amount' => 100.50,
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
                        'type' => 'deposit',
                        'amount' => 100.50,
                    ],
                    'new_balance' => $initialBalance + 100.50,
                ],
            ]);

        // Verify balance was updated in database
        $this->assertDatabaseHas('balances', [
            'account_id' => $account->id,
            'amount' => $initialBalance + 100.50,
        ]);

        // Verify transaction was created
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'amount' => 100.50,
        ]);
    }

    /**
     * Test deposit fails with negative amount
     */
    public function test_deposit_fails_with_negative_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', [
                'amount' => -50,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test deposit fails with zero amount
     */
    public function test_deposit_fails_with_zero_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', [
                'amount' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test deposit fails without amount
     */
    public function test_deposit_fails_without_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test deposit fails with invalid amount format
     */
    public function test_deposit_fails_with_invalid_format(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', [
                'amount' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test deposit fails without authentication
     */
    public function test_deposit_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/wallet/deposit', [
            'amount' => 100,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test deposit with very large amount
     */
    public function test_deposit_accepts_large_amount(): void
    {
        $largeAmount = 9999.99; // Within daily limit of 10000

        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', [
                'amount' => $largeAmount,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'transaction' => [
                        'amount' => $largeAmount,
                    ],
                    'new_balance' => $largeAmount,
                ],
            ]);
    }

    /**
     * Test deposit with decimal precision
     */
    public function test_deposit_handles_decimal_precision(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', [
                'amount' => 99.99,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'transaction' => [
                        'amount' => 99.99,
                    ],
                    'new_balance' => 99.99,
                ],
            ]);
    }

    /**
     * Test multiple deposits accumulate correctly
     */
    public function test_multiple_deposits_accumulate(): void
    {
        // First deposit
        $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', ['amount' => 100]);

        // Second deposit
        $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', ['amount' => 50]);

        // Third deposit
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', ['amount' => 25]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'new_balance' => 175.0,
                ],
            ]);

        // Verify all transactions were created
        $account = \App\Models\Account::where('user_id', $this->user->id)->first();
        $this->assertEquals(3, Transaction::where('account_id', $account->id)->count());
    }

    /**
     * Test deposit creates transaction with correct metadata
     */
    public function test_deposit_creates_correct_transaction_metadata(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', [
                'amount' => 150.75,
            ]);

        $response->assertStatus(201);

        $account = \App\Models\Account::where('user_id', $this->user->id)->first();
        $transaction = Transaction::with('transactionType')->latest()->first();

        $this->assertEquals('DEPOSIT', $transaction->transactionType->code);
        $this->assertEquals(150.75, $transaction->amount);
        $this->assertEquals($account->id, $transaction->account_id);
        $this->assertNull($transaction->related_account_id);
        $this->assertStringContainsString('Depósito', $transaction->description);
    }
}
