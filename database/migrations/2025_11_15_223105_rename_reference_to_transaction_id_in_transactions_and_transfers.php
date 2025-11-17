<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename reference to transactionId in transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('reference', 'transaction_id');
        });

        // Rename reference to transactionId in transfers table
        Schema::table('transfers', function (Blueprint $table) {
            $table->renameColumn('reference', 'transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert transactionId back to reference in transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('transaction_id', 'reference');
        });

        // Revert transactionId back to reference in transfers table
        Schema::table('transfers', function (Blueprint $table) {
            $table->renameColumn('transaction_id', 'reference');
        });
    }
};
