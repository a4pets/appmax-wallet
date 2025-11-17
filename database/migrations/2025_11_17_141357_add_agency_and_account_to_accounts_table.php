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
        // Add columns as nullable first
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('agency', 4)->nullable()->after('user_id')->comment('Agência - 4 dígitos numéricos');
            $table->string('account', 10)->nullable()->after('agency')->comment('Conta - até 10 dígitos numéricos');
            $table->string('account_digit', 2)->nullable()->after('account')->comment('Dígito verificador da conta');
        });

        // Populate existing accounts with generated agency and account numbers
        $accounts = DB::table('accounts')->whereNull('agency')->get();
        foreach ($accounts as $account) {
            $agency = str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $accountNumber = str_pad((string) rand(1, 999999999), 9, '0', STR_PAD_LEFT);
            $digit = rand(0, 9);

            DB::table('accounts')
                ->where('id', $account->id)
                ->update([
                    'agency' => $agency,
                    'account' => $accountNumber,
                    'account_digit' => $digit,
                ]);
        }

        // Make agency and account NOT NULL after populating
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('agency', 4)->nullable(false)->change();
            $table->string('account', 10)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['agency', 'account', 'account_digit']);
        });
    }
};
