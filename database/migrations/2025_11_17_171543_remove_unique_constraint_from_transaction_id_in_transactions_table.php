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
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // For SQLite, we need to check which index exists and drop it
            $indexes = \DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='transactions' AND (name LIKE '%reference%unique' OR name LIKE '%transaction_id%unique')");

            foreach ($indexes as $index) {
                \DB::statement("DROP INDEX IF EXISTS {$index->name}");
            }

            // Create a non-unique index
            \DB::statement("CREATE INDEX IF NOT EXISTS transactions_transaction_id_index ON transactions(transaction_id)");
        } else {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropUnique(['transaction_id']);
                $table->index('transaction_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->unique('transaction_id');
        });
    }
};
