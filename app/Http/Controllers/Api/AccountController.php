<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    /**
     * List all active accounts with user information
     * Public endpoint for testing purposes
     *
     * @return JsonResponse
     */
    public function list(): JsonResponse
    {
        $accounts = Account::with(['user', 'balance'])
            ->where('status', 'active')
            ->get()
            ->map(function ($account) {
                return [
                    'user' => [
                        'id' => $account->user->id,
                        'name' => $account->user->name,
                        'email' => $account->user->email,
                    ],
                    'account' => [
                        'agency' => $account->agency,
                        'account' => $account->account,
                        'account_digit' => $account->account_digit,
                        'account_number' => $account->account_number,
                        'account_type' => $account->account_type,
                        'status' => $account->status,
                        'balance' => $account->balance?->amount ?? 0,
                    ],
                ];
            });

        return response()->json([
            'data' => [
                'accounts' => $accounts,
                'total' => $accounts->count(),
                'message' => 'Lista de contas disponíveis para testes',
            ],
        ]);
    }
}
