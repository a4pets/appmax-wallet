<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $transactionTypes = [
            [
                'code' => 'DEPOSIT',
                'name' => 'Deposit',
                'description' => 'Money added to account',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'WITHDRAW',
                'name' => 'Withdrawal',
                'description' => 'Money withdrawn from account',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'TRANSFER_SENT',
                'name' => 'Transfer Sent',
                'description' => 'Money transferred to another account',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'TRANSFER_RECEIVED',
                'name' => 'Transfer Received',
                'description' => 'Money received from another account',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'REVERSAL',
                'name' => 'Reversal',
                'description' => 'Transaction reversal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'CHARGEBACK',
                'name' => 'Chargeback',
                'description' => 'Transaction chargeback/reversal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        \DB::table('transaction_types')->insertOrIgnore($transactionTypes);
    }
}
