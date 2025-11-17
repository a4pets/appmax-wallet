<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Account;
use App\Models\Balance;
use App\Models\Transaction;
use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;
    private User $receiver;
    private string $senderToken;
    private string $receiverToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sender with balance
        $this->sender = User::factory()->withAccount(1000.0)->create();
        $this->sender->refresh();
        $this->senderToken = auth()->login($this->sender);

        // Create receiver with balance
        $this->receiver = User::factory()->withAccount(500.0)->create();
        $this->receiver->refresh();
        $this->receiverToken = auth()->login($this->receiver);
    }

    /**
     * Test successful transfer between two accounts
     */
    public function test_successful_transfer_between_accounts(): void
    {
        $senderAccount = \App\Models\Account::where('user_id', $this->sender->id)->first();
        $receiverAccount = \App\Models\Account::where('user_id', $this->receiver->id)->first();
        $senderInitialBalance = \DB::table('balances')->where('account_id', $senderAccount->id)->value('amount');
        $receiverInitialBalance = \DB::table('balances')->where('account_id', $receiverAccount->id)->value('amount');
        $transferAmount = 200;

        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
                'amount' => $transferAmount,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'transfer' => [
                        'id',
                        'from_account_number',
                        'receiver_account_number',
                        'amount',
                        'created_at',
                    ],
                    'new_balance',
                ],
            ])
            ->assertJson([
                'data' => [
                    'transfer' => [
                        'from_account_number' => $senderAccount->account_number,
                        'receiver_account_number' => $receiverAccount->account_number,
                        'amount' => $transferAmount,
                    ],
                    'new_balance' => $senderInitialBalance - $transferAmount,
                ],
            ]);

        // Verify sender balance decreased
        $this->assertDatabaseHas('balances', [
            'account_id' => $senderAccount->id,
            'amount' => $senderInitialBalance - $transferAmount,
        ]);

        // Verify receiver balance increased
        $this->assertDatabaseHas('balances', [
            'account_id' => $receiverAccount->id,
            'amount' => $receiverInitialBalance + $transferAmount,
        ]);

        // Verify transfer record was created
        $this->assertDatabaseHas('transfers', [
            'sender_account_id' => $senderAccount->id,
            'receiver_account_id' => $receiverAccount->id,
            'amount' => $transferAmount,
        ]);

        // Verify two transactions were created (debit and credit)
        $this->assertEquals(2, Transaction::count());
    }

    /**
     * Test transfer fails to same account
     */
    public function test_transfer_fails_to_same_account(): void
    {
        $senderAccount = \App\Models\Account::where('user_id', $this->sender->id)->first();

        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $senderAccount->account_number,
                'amount' => 100,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'data' => [
                    'code' => 'INVALID_TRANSFER',
                ],
            ]);

        // Verify no transfer was created
        $this->assertEquals(0, Transfer::count());
    }

    /**
     * Test transfer fails with insufficient balance
     */
    public function test_transfer_fails_with_insufficient_balance(): void
    {
        $receiverAccount = \App\Models\Account::where('user_id', $this->receiver->id)->first();

        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
                'amount' => 2000, // More than balance (1000)
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'data' => [
                    'code' => 'INSUFFICIENT_BALANCE',
                ],
            ]);

        // Verify balances were not changed
        $senderAccount = \App\Models\Account::where('user_id', $this->sender->id)->first();
        $this->assertDatabaseHas('balances', [
            'account_id' => $senderAccount->id,
            'amount' => 1000,
        ]);
    }

    /**
     * Test transfer fails to non-existent account
     */
    public function test_transfer_fails_to_nonexistent_account(): void
    {
        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => '9999999999',
                'amount' => 100,
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'data' => [
                    'code' => 'INVALID_ACCOUNT',
                ],
            ]);
    }

    /**
     * Test transfer fails to inactive account
     */
    public function test_transfer_fails_to_inactive_account(): void
    {
        // Set receiver account to inactive
        $this->receiver->account->update(['status' => 'inactive']);

        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $this->receiver->account->account_number,
                'amount' => 100,
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'data' => [
                    'code' => 'INVALID_ACCOUNT',
                ],
            ]);
    }

    /**
     * Test transfer fails with negative amount
     */
    public function test_transfer_fails_with_negative_amount(): void
    {
        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $this->receiver->account->account_number,
                'amount' => -100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test transfer fails with zero amount
     */
    public function test_transfer_fails_with_zero_amount(): void
    {
        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $this->receiver->account->account_number,
                'amount' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test transfer fails without authentication
     */
    public function test_transfer_fails_without_authentication(): void
    {
        $receiverAccount = \App\Models\Account::where('user_id', $this->receiver->id)->first();

        $response = $this->postJson('/api/wallet/transfer', [
            'receiver_account_number' => $receiverAccount->account_number,
            'amount' => 100,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test transfer with decimal precision
     */
    public function test_transfer_handles_decimal_precision(): void
    {
        $receiverAccount = \App\Models\Account::where('user_id', $this->receiver->id)->first();

        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
                'amount' => 99.99,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'transfer' => [
                        'amount' => 99.99,
                    ],
                    'new_balance' => 900.01,
                ],
            ]);
    }

    /**
     * Test transfer of entire balance
     */
    public function test_transfer_entire_balance(): void
    {
        $senderAccount = \App\Models\Account::where('user_id', $this->sender->id)->first();
        $receiverAccount = \App\Models\Account::where('user_id', $this->receiver->id)->first();
        $fullBalance = \DB::table('balances')->where('account_id', $senderAccount->id)->value('amount');

        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
                'amount' => $fullBalance,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'new_balance' => 0.0,
                ],
            ]);

        // Verify sender has zero balance
        $this->assertDatabaseHas('balances', [
            'account_id' => $senderAccount->id,
            'amount' => 0,
        ]);

        // Verify receiver balance increased
        $this->assertDatabaseHas('balances', [
            'account_id' => $receiverAccount->id,
            'amount' => 500 + $fullBalance,
        ]);
    }

    /**
     * Test multiple transfers work correctly
     */
    public function test_multiple_transfers(): void
    {
        $receiverAccount = \App\Models\Account::where('user_id', $this->receiver->id)->first();

        // First transfer
        $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
                'amount' => 100,
            ]);

        // Second transfer
        $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
                'amount' => 150,
            ]);

        // Third transfer
        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
                'amount' => 50,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'new_balance' => 700.0, // 1000 - 100 - 150 - 50
                ],
            ]);

        // Verify receiver got all transfers
        $this->assertDatabaseHas('balances', [
            'account_id' => $receiverAccount->id,
            'amount' => 800, // 500 + 100 + 150 + 50
        ]);

        // Verify 3 transfers were created
        $this->assertEquals(3, Transfer::count());
        // Verify 6 transactions were created (2 per transfer)
        $this->assertEquals(6, Transaction::count());
    }

    /**
     * Test transfer respects daily limit
     */
    public function test_transfer_respects_daily_limit(): void
    {
        // Update sender balance to have enough for large transfer
        $senderAccount = \App\Models\Account::where('user_id', $this->sender->id)->first();
        $receiverAccount = \App\Models\Account::where('user_id', $this->receiver->id)->first();
        \DB::table('balances')->where('account_id', $senderAccount->id)->update(['amount' => 10000]);

        // Try to transfer more than daily limit (5000)
        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
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
     * Test transfer creates correct transaction records for both accounts
     */
    public function test_transfer_creates_correct_transactions(): void
    {
        $senderAccount = \App\Models\Account::where('user_id', $this->sender->id)->first();
        $receiverAccount = \App\Models\Account::where('user_id', $this->receiver->id)->first();

        $response = $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
                'amount' => 250,
            ]);

        $response->assertStatus(200);

        // Verify debit transaction for sender
        $this->assertDatabaseHas('transactions', [
            'account_id' => $senderAccount->id,
            'amount' => 250,
        ]);

        // Verify credit transaction for receiver
        $this->assertDatabaseHas('transactions', [
            'account_id' => $receiverAccount->id,
            'amount' => 250,
        ]);
    }

    /**
     * Test bidirectional transfers work correctly
     */
    public function test_bidirectional_transfers(): void
    {
        $senderAccount = \App\Models\Account::where('user_id', $this->sender->id)->first();
        $receiverAccount = \App\Models\Account::where('user_id', $this->receiver->id)->first();

        // Sender transfers to receiver
        $this->withToken($this->senderToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
                'amount' => 300,
            ]);

        // Receiver transfers back to sender
        $response = $this->withToken($this->receiverToken)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $senderAccount->account_number,
                'amount' => 200,
            ]);

        $response->assertStatus(200);

        // Verify final balances
        // Sender: 1000 - 300 + 200 = 900
        $this->assertDatabaseHas('balances', [
            'account_id' => $senderAccount->id,
            'amount' => 900,
        ]);

        // Receiver: 500 + 300 - 200 = 600
        $this->assertDatabaseHas('balances', [
            'account_id' => $receiverAccount->id,
            'amount' => 600,
        ]);
    }
}
