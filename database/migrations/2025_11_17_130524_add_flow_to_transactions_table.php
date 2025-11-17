<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('flow', ['C', 'D', 'E'])
                ->nullable()
                ->after('transaction_type_id')
                ->comment('C = Credit, D = Debit, E = Chargeback');
        });

        // Update existing transactions based on their transaction type
        DB::statement("
            UPDATE transactions
            SET flow = CASE
                WHEN transaction_type_id IN (
                    SELECT id FROM transaction_types WHERE code IN ('DEPOSIT', 'TRANSFER_RECEIVED')
                ) THEN 'C'
                WHEN transaction_type_id IN (
                    SELECT id FROM transaction_types WHERE code IN ('WITHDRAW', 'TRANSFER_SENT')
                ) THEN 'D'
                ELSE 'C'
            END
        ");

        // Make the column NOT NULL after populating existing data
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('flow', ['C', 'D', 'E'])
                ->nullable(false)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('flow');
        });
    }
};
