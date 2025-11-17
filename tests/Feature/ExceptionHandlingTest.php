<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Account;
use App\Models\Balance;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withAccount(1000.0)->create();
        $this->user->refresh();
        $this->token = auth()->login($this->user);
    }

    /**
     * Test InsufficientBalanceException is thrown correctly
     */
    public function test_insufficient_balance_exception(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', [
                'amount' => 2000, // More than balance
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

        // Verify error message contains balance info
        $this->assertStringContainsString('Saldo insuficiente', $response->json('data.error'));
    }

    /**
     * Test DailyLimitExceededException is thrown correctly
     */
    public function test_daily_limit_exceeded_exception(): void
    {
        // Update balance to have enough funds
        $account = \App\Models\Account::where('user_id', $this->user->id)->first();
        \DB::table('balances')->where('account_id', $account->id)->update(['amount' => 10000]);

        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', [
                'amount' => 6000, // More than daily limit (5000)
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'data' => [
                    'code' => 'DAILY_LIMIT_EXCEEDED',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'error',
                    'code',
                    'details' => [
                        'daily_limit',
                        'daily_used',
                        'daily_available',
                        'attempted_amount',
                    ],
                ],
            ]);

        // Verify error message
        $this->assertStringContainsString('Limite diário', $response->json('data.error'));
        $this->assertStringContainsString('excedido', $response->json('data.error'));
    }

    /**
     * Test InvalidAccountException is thrown for non-existent account
     */
    public function test_invalid_account_exception_nonexistent(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => '9999999999',
                'amount' => 100,
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'data' => [
                    'code' => 'INVALID_ACCOUNT',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'error',
                    'code',
                ],
            ]);

        // Verify error message
        $this->assertStringContainsString('não encontrada ou inativa', $response->json('data.error'));
    }

    /**
     * Test InvalidAccountException is thrown for inactive account
     */
    public function test_invalid_account_exception_inactive(): void
    {
        // Create inactive account
        $inactiveUser = User::factory()->withAccount(500.0)->create();
        $inactiveAccount = $inactiveUser->account;
        $inactiveAccount->update(['status' => 'inactive']);

        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $inactiveAccount->account_number,
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
     * Test InvalidTransferException is thrown for same account transfer
     */
    public function test_invalid_transfer_exception(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $this->user->account->account_number,
                'amount' => 100,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'data' => [
                    'code' => 'INVALID_TRANSFER',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'error',
                    'code',
                ],
            ]);

        // Verify error message
        $this->assertStringContainsString('transferir para a mesma conta', $response->json('data.error'));
    }

    /**
     * Test ValidationException for missing required fields
     */
    public function test_validation_exception_missing_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', []);

        $response->assertStatus(422)
            ->assertJson([
                'data' => [
                    'code' => 'VALIDATION_ERROR',
                    'error' => 'Dados de entrada inválidos',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'error',
                    'code',
                ],
                'errors',
            ]);
    }

    /**
     * Test ValidationException for invalid field types
     */
    public function test_validation_exception_invalid_types(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', [
                'amount' => 'not-a-number',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'data' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ])
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test ValidationException for registration with invalid email
     */
    public function test_validation_exception_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test AuthenticationException for unauthenticated request
     */
    public function test_authentication_exception(): void
    {
        $response = $this->getJson('/api/wallet/balance');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Token not provided',
            ]);
    }

    /**
     * Test AuthenticationException with invalid token
     */
    public function test_authentication_exception_invalid_token(): void
    {
        $response = $this->withToken('invalid-token-string')
            ->getJson('/api/wallet/balance');

        $response->assertStatus(401);
    }

    /**
     * Test unauthorized login attempt
     */
    public function test_unauthorized_exception_wrong_password(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'data' => [
                    'error' => 'Credenciais inválidas',
                    'code' => 'INVALID_CREDENTIALS',
                ],
            ]);
    }

    /**
     * Test daily limit is enforced for withdrawals
     */
    public function test_daily_limit_enforced_for_withdrawals(): void
    {
        // Set high balance
        $account = \App\Models\Account::where('user_id', $this->user->id)->first();
        \DB::table('balances')->where('account_id', $account->id)->update(['amount' => 10000]);

        // First withdrawal within limit
        $response1 = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', ['amount' => 3000]);
        $response1->assertStatus(201);

        // Second withdrawal that would exceed daily limit
        $response2 = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', ['amount' => 2500]);

        $response2->assertStatus(422)
            ->assertJson([
                'data' => [
                    'code' => 'DAILY_LIMIT_EXCEEDED',
                ],
            ]);
    }

    /**
     * Test daily limit is enforced for transfers
     */
    public function test_daily_limit_enforced_for_transfers(): void
    {
        // Create receiver
        $receiver = User::factory()->withAccount(0.0)->create();
        $receiverAccount = \App\Models\Account::where('user_id', $receiver->id)->first();

        // Set high balance
        $account = \App\Models\Account::where('user_id', $this->user->id)->first();
        \DB::table('balances')->where('account_id', $account->id)->update(['amount' => 10000]);

        // First transfer within limit
        $response1 = $this->withToken($this->token)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
                'amount' => 3000,
            ]);
        $response1->assertStatus(200);

        // Second transfer that would exceed daily limit
        $response2 = $this->withToken($this->token)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $receiverAccount->account_number,
                'amount' => 2500,
            ]);

        $response2->assertStatus(422)
            ->assertJson([
                'data' => [
                    'code' => 'DAILY_LIMIT_EXCEEDED',
                ],
            ]);
    }

    /**
     * Test exception response format is consistent
     */
    public function test_exception_response_format_consistency(): void
    {
        // Test insufficient balance
        $response1 = $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', ['amount' => 5000]);

        $this->assertArrayHasKey('data', $response1->json());
        $this->assertArrayHasKey('error', $response1->json('data'));
        $this->assertArrayHasKey('code', $response1->json('data'));

        // Test invalid transfer
        $response2 = $this->withToken($this->token)
            ->postJson('/api/wallet/transfer', [
                'receiver_account_number' => $this->user->account->account_number,
                'amount' => 100,
            ]);

        $this->assertArrayHasKey('data', $response2->json());
        $this->assertArrayHasKey('error', $response2->json('data'));
        $this->assertArrayHasKey('code', $response2->json('data'));

        // Test validation error
        $response3 = $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', []);

        $this->assertArrayHasKey('data', $response3->json());
        $this->assertArrayHasKey('error', $response3->json('data'));
        $this->assertArrayHasKey('code', $response3->json('data'));
    }
}
