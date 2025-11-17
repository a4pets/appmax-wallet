<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InitialAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test user
        $user = \DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => \Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create an account for the user
        $accountNumber = 'DW' . str_pad((string) rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        $agency = str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $accountNum = str_pad((string) rand(1, 999999999), 9, '0', STR_PAD_LEFT);
        $digit = rand(0, 9);

        $account = \DB::table('accounts')->insertGetId([
            'user_id' => $user,
            'agency' => $agency,
            'account' => $accountNum,
            'account_digit' => $digit,
            'account_number' => $accountNumber,
            'account_type' => 'digital_wallet',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create initial balance for the account (starting with 0)
        \DB::table('balances')->insert([
            'account_id' => $account,
            'amount' => 0.00,
            'updated_at' => now(),
        ]);

        // Create daily limits for the account
        $dailyLimits = [
            [
                'account_id' => $account,
                'limit_type' => 'deposit',
                'daily_limit' => 10000.00,
                'current_used' => 0,
                'reset_at' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $account,
                'limit_type' => 'withdraw',
                'daily_limit' => 5000.00,
                'current_used' => 0,
                'reset_at' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $account,
                'limit_type' => 'transfer',
                'daily_limit' => 5000.00,
                'current_used' => 0,
                'reset_at' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        \DB::table('daily_limits')->insert($dailyLimits);
    }
}
