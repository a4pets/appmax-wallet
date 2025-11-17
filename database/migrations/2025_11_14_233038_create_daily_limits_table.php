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
        Schema::create('daily_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->enum('limit_type', ['deposit', 'withdraw', 'transfer'])->default('withdraw');
            $table->decimal('daily_limit', 15, 2)->default(5000.00);
            $table->decimal('current_used', 15, 2)->default(0);
            $table->date('reset_at');
            $table->timestamps();

            $table->unique(['account_id', 'limit_type', 'reset_at']);
            $table->index('account_id');
            $table->index('reset_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_limits');
    }
};
