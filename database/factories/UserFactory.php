<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Balance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }


    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create user with an account and balance.
     */
    public function withAccount(float $initialBalance = 0.0): static
    {
        return $this->afterCreating(function (\App\Models\User $user) use ($initialBalance) {
            $accountData = [
                'user_id' => $user->id,
                'agency' => str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'account' => str_pad((string) rand(1, 999999999), 9, '0', STR_PAD_LEFT),
                'account_digit' => (string) rand(0, 9),
                'account_number' => 'DW' . str_pad((string) rand(1, 99999999), 8, '0', STR_PAD_LEFT),
                'account_type' => 'digital_wallet',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $accountId = \DB::table('accounts')->insertGetId($accountData);

            \DB::table('balances')->insert([
                'account_id' => $accountId,
                'amount' => $initialBalance,
                'updated_at' => now(),
            ]);
        });
    }
}
