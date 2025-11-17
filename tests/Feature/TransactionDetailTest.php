<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Account;
use App\Models\Balance;
use App\Models\Transaction;
use App\Models\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user with account and balance
        $this->user = User::factory()->withAccount(1000.0)->create();
        $this->user->refresh();
        $this->token = auth()->login($this->user);
        $this->account = $this->user->account;

        // Transaction types are seeded by TestCase
    }

    /**
     * Test user can get transaction details by ID
     */
    public function test_user_can_get_transaction_by_id(): void
    {
        $transactionType = TransactionType::where('code', 'DEPOSIT')->first();

        $transaction = Transaction::create([
            'account_id' => $this->account->id,
            'transaction_type_id' => $transactionType->id,
            'flow' => 'C',
            'amount' => 500.00,
            'balance_before' => 1000.00,
            'balance_after' => 1500.00,
            'description' => 'Depósito via PIX',
            'transaction_id' => 'DEP-20250115123456-1234',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/wallet/transaction/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'transaction_id',
                    'amount',
                    'transaction_type',
                    'description',
                    'balance_before',
                    'balance_after',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.description', 'Depósito via PIX');

        $data = $response->json('data');
        $this->assertEquals(500.00, (float) $data['amount']);
    }

    /**
     * Test returns 404 when transaction not found
     */
    public function test_returns_404_when_transaction_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/wallet/transaction/99999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Transação não encontrada',
            ]);
    }

    /**
     * Test user cannot see another user's transaction
     */
    public function test_user_cannot_see_other_user_transaction(): void
    {
        // Create another user with account
        $otherUser = User::factory()->withAccount(500.0)->create();
        $otherAccount = $otherUser->account;

        $transactionType = TransactionType::where('code', 'DEPOSIT')->first();

        // Create transaction for other user
        $otherTransaction = Transaction::create([
            'account_id' => $otherAccount->id,
            'transaction_type_id' => $transactionType->id,
            'flow' => 'C',
            'amount' => 500.00,
            'balance_before' => 500.00,
            'balance_after' => 1000.00,
            'description' => 'Other user deposit',
            'transaction_id' => 'DEP-20250115123456-5678',
        ]);

        // Try to access other user's transaction
        $response = $this->withToken($this->token)
            ->getJson("/api/wallet/transaction/{$otherTransaction->id}");

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Transação não encontrada',
            ]);
    }

    /**
     * Test transaction detail fails without authentication
     */
    public function test_detail_fails_without_authentication(): void
    {
        $transactionType = TransactionType::where('code', 'DEPOSIT')->first();

        $transaction = Transaction::create([
            'account_id' => $this->account->id,
            'transaction_type_id' => $transactionType->id,
            'flow' => 'C',
            'amount' => 500.00,
            'balance_before' => 1000.00,
            'balance_after' => 1500.00,
            'description' => 'Depósito',
            'transaction_id' => 'DEP-20250115123456-1234',
        ]);

        $response = $this->getJson("/api/wallet/transaction/{$transaction->id}");

        $response->assertStatus(401);
    }

    /**
     * Test transaction detail includes metadata
     */
    public function test_transaction_includes_metadata(): void
    {
        $transactionType = TransactionType::where('code', 'TRANSFER_SENT')->first();

        $transaction = Transaction::create([
            'account_id' => $this->account->id,
            'transaction_type_id' => $transactionType->id,
            'flow' => 'D',
            'amount' => 100.00,
            'balance_before' => 1000.00,
            'balance_after' => 900.00,
            'description' => 'Transferência para amigo',
            'transaction_id' => 'TRF-20250115123456-1234',
            'metadata' => ['receiver_account' => 'DW87654321'],
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/wallet/transaction/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.metadata.receiver_account', 'DW87654321');
    }

    /**
     * Test transaction detail for withdraw type
     */
    public function test_withdraw_transaction_detail(): void
    {
        $transactionType = TransactionType::where('code', 'WITHDRAW')->first();

        $transaction = Transaction::create([
            'account_id' => $this->account->id,
            'transaction_type_id' => $transactionType->id,
            'flow' => 'D',
            'amount' => 200.00,
            'balance_before' => 1000.00,
            'balance_after' => 800.00,
            'description' => 'Saque em caixa eletrônico',
            'transaction_id' => 'WIT-20250115123456-1234',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/wallet/transaction/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.transaction_type.code', 'WITHDRAW');

        $data = $response->json('data');
        $this->assertEquals(200.00, (float) $data['amount']);
        $this->assertEquals(1000.00, (float) $data['balance_before']);
        $this->assertEquals(800.00, (float) $data['balance_after']);
    }

    /**
     * Test transaction detail for transfer received
     */
    public function test_transfer_received_transaction_detail(): void
    {
        $transactionType = TransactionType::where('code', 'TRANSFER_RECEIVED')->first();

        $transaction = Transaction::create([
            'account_id' => $this->account->id,
            'transaction_type_id' => $transactionType->id,
            'flow' => 'C',
            'amount' => 300.00,
            'balance_before' => 1000.00,
            'balance_after' => 1300.00,
            'description' => 'Transferência recebida',
            'transaction_id' => 'TRF-20250115123456-5678',
            'metadata' => ['sender_account' => 'DW12345678'],
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/wallet/transaction/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.transaction_type.code', 'TRANSFER_RECEIVED')
            ->assertJsonPath('data.metadata.sender_account', 'DW12345678');

        $data = $response->json('data');
        $this->assertEquals(300.00, (float) $data['amount']);
    }
}
