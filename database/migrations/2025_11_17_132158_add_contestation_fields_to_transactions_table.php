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
            $table->boolean('is_contested')->default(false)->after('is_chargebacked')->comment('Indicates if this transaction has been contested');
            $table->timestamp('contested_at')->nullable()->after('is_contested')->comment('When the transaction was contested');
            $table->text('contested_reason')->nullable()->after('contested_at')->comment('Reason for contestation');
            $table->foreignId('contestation_transaction_id')->nullable()->after('chargeback_of_transaction_id')->constrained('transactions')->comment('Reference to the contestation transaction if this is the original');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['contestation_transaction_id']);
            $table->dropColumn(['is_contested', 'contested_at', 'contested_reason', 'contestation_transaction_id']);
        });
    }
};
