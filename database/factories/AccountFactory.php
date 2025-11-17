<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'agency' => str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'account' => str_pad((string) rand(1, 999999999), 9, '0', STR_PAD_LEFT),
            'account_digit' => (string) rand(0, 9),
            'account_number' => 'DW' . str_pad((string) rand(1, 99999999), 8, '0', STR_PAD_LEFT),
            'account_type' => 'digital_wallet',
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the account is blocked.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'blocked',
        ]);
    }
}
