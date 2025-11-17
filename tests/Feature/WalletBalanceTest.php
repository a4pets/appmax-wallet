<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Account;
use App\Models\Balance;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletBalanceTest extends TestCase
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
     * Test authenticated user can check balance
     */
    public function test_authenticated_user_can_check_balance(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/wallet/balance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'account_number',
                    'balance',
                    'daily_limit',
                    'daily_used',
                    'daily_available',
                ],
            ])
            ->assertJson([
                'data' => [
                    'balance' => 0.0,
                    'daily_limit' => 5000.0,
                    'daily_used' => 0.0,
                    'daily_available' => 5000.0,
                ],
            ]);
    }

    /**
     * Test unauthenticated user cannot check balance
     */
    public function test_unauthenticated_user_cannot_check_balance(): void
    {
        $response = $this->getJson('/api/wallet/balance');

        $response->assertStatus(401);
    }

    /**
     * Test balance reflects correct amount after manual update
     */
    public function test_balance_reflects_correct_amount(): void
    {
        // Manually set balance to 1000
        $this->user->account->balance->update(['amount' => 1000]);

        $response = $this->withToken($this->token)
            ->getJson('/api/wallet/balance');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'balance' => 1000.0,
                ],
            ]);
    }

    /**
     * Test daily limit is correctly calculated
     */
    public function test_daily_limit_calculation(): void
    {
        // Set balance to 1000
        $this->user->account->balance->update(['amount' => 1000]);

        $response = $this->withToken($this->token)
            ->getJson('/api/wallet/balance');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'balance' => 1000.0,
                    'daily_limit' => 5000.0,
                    'daily_available' => 5000.0,
                ],
            ]);
    }

    /**
     * Test balance response includes account number
     */
    public function test_balance_includes_account_number(): void
    {
        $accountNumber = $this->user->account->account_number;

        $response = $this->withToken($this->token)
            ->getJson('/api/wallet/balance');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'account_number' => $accountNumber,
                ],
            ]);
    }

    /**
     * Test balance is zero for new account
     */
    public function test_new_account_has_zero_balance(): void
    {
        $newUser = User::factory()->withAccount(0.0)->create();
        $newToken = auth()->login($newUser);

        $response = $this->withToken($newToken)
            ->getJson('/api/wallet/balance');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'balance' => 0.0,
                ],
            ]);
    }
}
