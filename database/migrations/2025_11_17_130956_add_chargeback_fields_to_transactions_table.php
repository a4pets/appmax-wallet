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
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_chargebacked')->default(false)->after('metadata')->comment('Indicates if this transaction has been chargebacked');
            $table->foreignId('chargeback_of_transaction_id')->nullable()->after('is_chargebacked')->constrained('transactions')->comment('Reference to the original transaction if this is a chargeback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['chargeback_of_transaction_id']);
            $table->dropColumn(['is_chargebacked', 'chargeback_of_transaction_id']);
        });
    }
};
