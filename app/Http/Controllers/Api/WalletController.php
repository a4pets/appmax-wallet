<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepositRequest;
use App\Http\Requests\WithdrawRequest;
use App\Http\Requests\TransferRequest;
use App\Http\Requests\StatementRequest;
use App\Http\Requests\ChargebackRequest;
use App\Http\Requests\ContestarRequest;
use App\Http\Resources\BalanceResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\TransferResource;
use App\Http\Resources\StatementDayResource;
use App\Models\Account;
use App\Models\Balance;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\Transfer;
use App\Models\DailyLimit;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\DailyLimitExceededException;
use App\Exceptions\InvalidAccountException;
use App\Exceptions\InvalidTransferException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class WalletController extends Controller
{
    #[OA\Get(
        path: '/api/wallet/balance',
        tags: ['Wallet'],
        summary: 'Get account balance',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Balance retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'account_number', type: 'string', example: 'DW12345678'),
                                new OA\Property(property: 'balance', type: 'number', format: 'float', example: 1000.00),
                                new OA\Property(property: 'account_type', type: 'string', example: 'digital_wallet'),
                                new OA\Property(property: 'status', type: 'string', example: 'active'),
                                new OA\Property(property: 'daily_limit', type: 'number', format: 'float', example: 5000.00),
                                new OA\Property(property: 'daily_used', type: 'number', format: 'float', example: 0.00),
                                new OA\Property(property: 'daily_available', type: 'number', format: 'float', example: 5000.00)
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Account not found')
        ]
    )]
    /**
     * Get account balance
     */
    public function balance(): JsonResponse
    {
        $user = auth()->user();

        $account = Account::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('balance')
            ->first();

        if (!$account) {
            throw new InvalidAccountException('Conta não encontrada ou inativa');
        }

        $dailyLimit = DailyLimit::where('account_id', $account->id)
            ->where('limit_type', 'withdraw')
            ->whereDate('reset_at', now()->toDateString())
            ->first();

        if (!$dailyLimit) {
            try {
                $dailyLimit = DailyLimit::create([
                    'account_id' => $account->id,
                    'limit_type' => 'withdraw',
                    'daily_limit' => 5000.00,
                    'current_used' => 0,
                    'reset_at' => now()->toDateString(),
                ]);
            } catch (\Exception $e) {
                $dailyLimit = DailyLimit::where('account_id', $account->id)
                    ->where('limit_type', 'withdraw')
                    ->whereDate('reset_at', now()->toDateString())
                    ->first();
            }
        }

        if (!$dailyLimit) {
            throw new \Exception('Unable to retrieve or create daily limit record for withdraw');
        }

        return response()->json([
            'data' => [
                'account_number' => $account->account_number,
                'agency' => $account->agency,
                'account' => $account->account,
                'account_digit' => $account->account_digit,
                'balance' => (float) $account->balance->amount,
                'account_type' => $account->account_type,
                'status' => $account->status,
                'daily_limit' => (float) $dailyLimit->daily_limit,
                'daily_used' => (float) $dailyLimit->current_used,
                'daily_available' => (float) ($dailyLimit->daily_limit - $dailyLimit->current_used),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/wallet/deposit',
        tags: ['Wallet'],
        summary: 'Make a deposit',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        required: ['amount'],
                        properties: [
                            new OA\Property(property: 'amount', type: 'number', format: 'float', example: 500.00),
                            new OA\Property(property: 'description', type: 'string', example: 'Depósito via PIX')
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Deposit successful'),
            new OA\Response(response: 422, description: 'Daily limit exceeded'),
            new OA\Response(response: 404, description: 'Account not found')
        ]
    )]
    /**
     * Make a deposit
     */
    public function deposit(DepositRequest $request): JsonResponse
    {
        DB::beginTransaction();

        $user = auth()->user();
        $account = Account::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('balance')
            ->lockForUpdate()
            ->first();

        if (!$account) {
            DB::rollBack();
            throw new InvalidAccountException('Conta não encontrada ou inativa');
        }

        $amount = $request->amount;

        $today = now()->toDateString();
        try {
            $dailyLimit = DailyLimit::firstOrCreate(
                [
                    'account_id' => $account->id,
                    'limit_type' => 'deposit',
                    'reset_at' => $today,
                ],
                [
                    'daily_limit' => 10000.00,
                    'current_used' => 0,
                ]
            );
        } catch (\Exception $e) {
            $dailyLimit = DailyLimit::where('account_id', $account->id)
                ->where('limit_type', 'deposit')
                ->whereDate('reset_at', $today)
                ->first();
        }

        if ($dailyLimit) {
            $dailyLimit->refresh();
        }

        if (!$dailyLimit) {
            DB::rollBack();
            throw new \Exception('Unable to retrieve or create daily limit record for deposit');
        }

        if (($dailyLimit->current_used + $amount) > $dailyLimit->daily_limit) {
            DB::rollBack();
            throw new DailyLimitExceededException(
                'deposit',
                $dailyLimit->current_used,
                $dailyLimit->daily_limit,
                $amount
            );
        }

        $transactionType = TransactionType::where('code', 'DEPOSIT')->first();

        $balance = $account->balance;
        $balanceBefore = $balance->amount;
        $balanceAfter = $balanceBefore + $amount;

        $balance->update(['amount' => $balanceAfter]);

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'transaction_type_id' => $transactionType->id,
            'flow' => 'C',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $request->description ?? 'Depósito',
            'transaction_id' => 'DEP-' . now()->format('YmdHis') . '-' . uniqid('', true),
        ]);

        $dailyLimit->increment('current_used', $amount);

        DB::commit();

        return response()->json([
            'data' => [
                'transaction' => new TransactionResource($transaction->load('transactionType')),
                'new_balance' => (float) $balanceAfter,
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/api/wallet/withdraw',
        tags: ['Wallet'],
        summary: 'Make a withdrawal',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        required: ['amount'],
                        properties: [
                            new OA\Property(property: 'amount', type: 'number', format: 'float', example: 200.00),
                            new OA\Property(property: 'description', type: 'string', example: 'Saque em caixa eletrônico')
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Withdrawal successful'),
            new OA\Response(response: 422, description: 'Insufficient balance or daily limit exceeded')
        ]
    )]
    /**
     * Make a withdrawal
     */
    public function withdraw(WithdrawRequest $request): JsonResponse
    {
        DB::beginTransaction();

        $user = auth()->user();
        $account = Account::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('balance')
            ->lockForUpdate()
            ->first();

        if (!$account) {
            DB::rollBack();
            throw new InvalidAccountException('Conta não encontrada ou inativa');
        }

        $amount = $request->amount;
        $balance = $account->balance;

        if ($balance->amount < $amount) {
            DB::rollBack();
            throw new InsufficientBalanceException($balance->amount, $amount);
        }

        $today = now()->toDateString();
        try {
            $dailyLimit = DailyLimit::firstOrCreate(
                [
                    'account_id' => $account->id,
                    'limit_type' => 'withdraw',
                    'reset_at' => $today,
                ],
                [
                    'daily_limit' => 5000.00,
                    'current_used' => 0,
                ]
            );
        } catch (\Exception $e) {
            $dailyLimit = DailyLimit::where('account_id', $account->id)
                ->where('limit_type', 'withdraw')
                ->whereDate('reset_at', $today)
                ->first();
        }

        if ($dailyLimit) {
            $dailyLimit->refresh();
        }

        if (!$dailyLimit) {
            DB::rollBack();
            throw new \Exception('Unable to retrieve or create daily limit record for withdraw');
        }

        if (($dailyLimit->current_used + $amount) > $dailyLimit->daily_limit) {
            DB::rollBack();
            throw new DailyLimitExceededException(
                'withdraw',
                $dailyLimit->current_used,
                $dailyLimit->daily_limit,
                $amount
            );
        }

        $transactionType = TransactionType::where('code', 'WITHDRAW')->first();

        $balanceBefore = $balance->amount;
        $balanceAfter = $balanceBefore - $amount;

        $balance->update(['amount' => $balanceAfter]);

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'transaction_type_id' => $transactionType->id,
            'flow' => 'D',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $request->description ?? 'Saque',
            'transaction_id' => 'WIT-' . now()->format('YmdHis') . '-' . uniqid('', true),
        ]);

        $dailyLimit->increment('current_used', $amount);

        DB::commit();

        return response()->json([
            'data' => [
                'transaction' => new TransactionResource($transaction->load('transactionType')),
                'new_balance' => (float) $balanceAfter,
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/api/wallet/transfer',
        tags: ['Wallet'],
        summary: 'Make a transfer',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        required: ['receiver_account_number', 'amount'],
                        properties: [
                            new OA\Property(property: 'receiver_account_number', type: 'string', example: 'DW87654321'),
                            new OA\Property(property: 'amount', type: 'number', format: 'float', example: 100.00),
                            new OA\Property(property: 'description', type: 'string', example: 'Transferência para amigo')
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Transfer successful'),
            new OA\Response(response: 422, description: 'Insufficient balance, daily limit exceeded, or invalid transfer'),
            new OA\Response(response: 404, description: 'Receiver account not found')
        ]
    )]
    /**
     * Make a transfer
     */
    public function transfer(TransferRequest $request): JsonResponse
    {
        DB::beginTransaction();

        $user = auth()->user();
        $senderAccount = Account::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('balance')
            ->lockForUpdate()
            ->first();

        if (!$senderAccount) {
            DB::rollBack();
            throw new InvalidAccountException('Conta de origem não encontrada ou inativa');
        }

        $receiverQuery = Account::where('status', 'active')
            ->with('balance')
            ->lockForUpdate();

        if ($request->filled('receiver_account_number')) {
            $receiverQuery->where('account_number', $request->receiver_account_number);
        } elseif ($request->filled('receiver_agency') && $request->filled('receiver_account')) {
            $receiverQuery->where('agency', $request->receiver_agency)
                ->where('account', $request->receiver_account);
        } else {
            DB::rollBack();
            throw new InvalidAccountException('Informe o número da conta ou a agência e conta de destino');
        }

        $receiverAccount = $receiverQuery->first();

        if (!$receiverAccount) {
            DB::rollBack();
            throw new InvalidAccountException('Conta de destino não encontrada ou inativa');
        }

        if ($senderAccount->id === $receiverAccount->id) {
            DB::rollBack();
            throw new InvalidTransferException('Não é possível transferir para a mesma conta');
        }

        $amount = $request->amount;
        $senderBalance = $senderAccount->balance;

        if ($senderBalance->amount < $amount) {
            DB::rollBack();
            throw new InsufficientBalanceException($senderBalance->amount, $amount);
        }

        $today = now()->toDateString();
        try {
            $dailyLimit = DailyLimit::firstOrCreate(
                [
                    'account_id' => $senderAccount->id,
                    'limit_type' => 'transfer',
                    'reset_at' => $today,
                ],
                [
                    'daily_limit' => 5000.00,
                    'current_used' => 0,
                ]
            );
        } catch (\Exception $e) {
            $dailyLimit = DailyLimit::where('account_id', $senderAccount->id)
                ->where('limit_type', 'transfer')
                ->whereDate('reset_at', $today)
                ->first();
        }

        if ($dailyLimit) {
            $dailyLimit->refresh();
        }

        if (!$dailyLimit) {
            DB::rollBack();
            throw new \Exception('Unable to retrieve or create daily limit record for transfer');
        }

        if (($dailyLimit->current_used + $amount) > $dailyLimit->daily_limit) {
            DB::rollBack();
            throw new DailyLimitExceededException(
                'transfer',
                $dailyLimit->current_used,
                $dailyLimit->daily_limit,
                $amount
            );
        }

        $transferSentType = TransactionType::where('code', 'TRANSFER_SENT')->first();
        $transferReceivedType = TransactionType::where('code', 'TRANSFER_RECEIVED')->first();

        $transactionId = 'TRF-' . now()->format('YmdHis') . '-' . uniqid('', true);

        $senderBalanceBefore = $senderBalance->amount;
        $senderBalanceAfter = $senderBalanceBefore - $amount;
        $senderBalance->update(['amount' => $senderBalanceAfter]);

        $senderTransaction = Transaction::create([
            'account_id' => $senderAccount->id,
            'transaction_type_id' => $transferSentType->id,
            'flow' => 'D',
            'amount' => $amount,
            'balance_before' => $senderBalanceBefore,
            'balance_after' => $senderBalanceAfter,
            'description' => $request->description ?? 'Transferência enviada',
            'transaction_id' => $transactionId,
            'metadata' => ['receiver_account' => $receiverAccount->account_number],
        ]);

        $receiverBalance = $receiverAccount->balance;
        $receiverBalanceBefore = $receiverBalance->amount;
        $receiverBalanceAfter = $receiverBalanceBefore + $amount;
        $receiverBalance->update(['amount' => $receiverBalanceAfter]);

        $receiverTransaction = Transaction::create([
            'account_id' => $receiverAccount->id,
            'transaction_type_id' => $transferReceivedType->id,
            'flow' => 'C',
            'amount' => $amount,
            'balance_before' => $receiverBalanceBefore,
            'balance_after' => $receiverBalanceAfter,
            'description' => $request->description ?? 'Transferência recebida',
            'transaction_id' => $transactionId,
            'metadata' => ['sender_account' => $senderAccount->account_number],
        ]);

        $transfer = Transfer::create([
            'sender_account_id' => $senderAccount->id,
            'receiver_account_id' => $receiverAccount->id,
            'amount' => $amount,
            'description' => $request->description,
            'status' => 'completed',
            'transaction_id' => $transactionId,
        ]);

        $dailyLimit->increment('current_used', $amount);

        DB::commit();

        return response()->json([
            'data' => [
                'transfer' => new TransferResource($transfer->load(['senderAccount', 'receiverAccount'])),
                'transaction' => new TransactionResource($senderTransaction->load('transactionType')),
                'new_balance' => (float) $senderBalanceAfter,
            ],
        ], 200);
    }

    #[OA\Post(
        path: '/api/wallet/chargeback',
        tags: ['Wallet'],
        summary: 'Chargeback a transaction',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        required: ['transaction_id'],
                        properties: [
                            new OA\Property(property: 'transaction_id', type: 'integer', example: 1),
                            new OA\Property(property: 'reason', type: 'string', example: 'Fraudulent transaction')
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Chargeback successful'),
            new OA\Response(response: 422, description: 'Transaction already chargebacked or invalid'),
            new OA\Response(response: 404, description: 'Transaction not found')
        ]
    )]
    /**
     * Chargeback a transaction
     */
    public function chargeback(ChargebackRequest $request): JsonResponse
    {
        DB::beginTransaction();

        $user = auth()->user();
        $account = Account::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('balance')
            ->lockForUpdate()
            ->first();

        if (!$account) {
            DB::rollBack();
            throw new InvalidAccountException('Conta não encontrada ou inativa');
        }

        $originalTransaction = Transaction::where('id', $request->transaction_id)
            ->where('account_id', $account->id)
            ->lockForUpdate()
            ->first();

        if (!$originalTransaction) {
            DB::rollBack();
            return response()->json([
                'message' => 'Transação não encontrada',
            ], 404);
        }

        if ($originalTransaction->is_chargebacked) {
            DB::rollBack();
            return response()->json([
                'message' => 'Esta transação já foi estornada',
            ], 422);
        }

        if ($originalTransaction->flow === 'E') {
            DB::rollBack();
            return response()->json([
                'message' => 'Não é possível estornar um estorno',
            ], 422);
        }

        $chargebackType = TransactionType::where('code', 'CHARGEBACK')->first();

        $balance = $account->balance;
        $balanceBefore = $balance->amount;

        $amount = $originalTransaction->amount;
        $balanceAfter = $originalTransaction->flow === 'C'
            ? $balanceBefore - $amount
            : $balanceBefore + $amount;

        $balance->update(['amount' => $balanceAfter]);

        $chargebackTransaction = Transaction::create([
            'account_id' => $account->id,
            'transaction_type_id' => $chargebackType->id,
            'flow' => 'E',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => 'Estorno: ' . ($request->reason ?? $originalTransaction->description),
            'transaction_id' => 'CHB-' . now()->format('YmdHis') . '-' . uniqid('', true),
            'chargeback_of_transaction_id' => $originalTransaction->id,
            'metadata' => [
                'original_transaction_id' => $originalTransaction->transaction_id,
                'original_flow' => $originalTransaction->flow,
                'reason' => $request->reason ?? 'Estorno solicitado',
            ],
        ]);

        $originalTransaction->update(['is_chargebacked' => true]);

        DB::commit();

        return response()->json([
            'data' => [
                'chargeback' => new TransactionResource($chargebackTransaction->load('transactionType')),
                'original_transaction' => new TransactionResource($originalTransaction->load('transactionType')),
                'new_balance' => (float) $balanceAfter,
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/api/wallet/contestar',
        tags: ['Wallet'],
        summary: 'Contest a transaction and reverse it',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        required: ['transaction_id'],
                        properties: [
                            new OA\Property(property: 'transaction_id', type: 'integer', example: 1),
                            new OA\Property(property: 'motivo', type: 'string', example: 'Transação não autorizada')
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Contestation successful'),
            new OA\Response(response: 422, description: 'Transaction already contested or invalid'),
            new OA\Response(response: 404, description: 'Transaction not found')
        ]
    )]
    /**
     * Contest a transaction (Contestação)
     */
    public function contestar(ContestarRequest $request): JsonResponse
    {
        DB::beginTransaction();

        $user = auth()->user();
        $account = Account::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('balance')
            ->lockForUpdate()
            ->first();

        if (!$account) {
            DB::rollBack();
            throw new InvalidAccountException('Conta não encontrada ou inativa');
        }

        $originalTransaction = Transaction::where('id', $request->transaction_id)
            ->where('account_id', $account->id)
            ->lockForUpdate()
            ->first();

        if (!$originalTransaction) {
            DB::rollBack();
            return response()->json([
                'message' => 'Transação não encontrada',
            ], 404);
        }

        if ($originalTransaction->is_contested) {
            DB::rollBack();
            return response()->json([
                'message' => 'Esta transação já foi contestada',
                'details' => [
                    'contested_at' => $originalTransaction->contested_at,
                    'contested_reason' => $originalTransaction->contested_reason,
                ],
            ], 422);
        }

        if ($originalTransaction->flow === 'E') {
            DB::rollBack();
            return response()->json([
                'message' => 'Não é possível contestar um estorno',
            ], 422);
        }

        $contestationType = TransactionType::where('code', 'CHARGEBACK')->first();

        $balance = $account->balance;
        $balanceBefore = $balance->amount;

        $amount = $originalTransaction->amount;
        $balanceAfter = $originalTransaction->flow === 'C'
            ? $balanceBefore - $amount
            : $balanceBefore + $amount;

        $balance->update(['amount' => $balanceAfter]);

        $contestationTransaction = Transaction::create([
            'account_id' => $account->id,
            'transaction_type_id' => $contestationType->id,
            'flow' => 'E',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => 'Estorno de contestação: ' . ($request->motivo ?? $originalTransaction->description),
            'transaction_id' => 'EST-' . now()->format('YmdHis') . '-' . uniqid('', true),
            'chargeback_of_transaction_id' => $originalTransaction->id,
            'metadata' => [
                'original_transaction_id' => $originalTransaction->transaction_id,
                'original_flow' => $originalTransaction->flow,
                'original_type' => $originalTransaction->transactionType->code ?? 'UNKNOWN',
                'motivo_contestacao' => $request->motivo ?? 'Contestação solicitada pelo usuário',
            ],
        ]);

        $originalTransaction->update([
            'is_contested' => true,
            'contested_at' => now(),
            'contested_reason' => $request->motivo ?? 'Contestação solicitada pelo usuário',
            'contestation_transaction_id' => $contestationTransaction->id,
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Contestação processada com sucesso',
            'data' => [
                'estorno' => new TransactionResource($contestationTransaction->load('transactionType')),
                'transacao_original' => new TransactionResource($originalTransaction->load('transactionType')),
                'novo_saldo' => (float) $balanceAfter,
                'status_contestacao' => [
                    'contestada' => true,
                    'contestada_em' => $originalTransaction->contested_at->toISOString(),
                    'motivo' => $originalTransaction->contested_reason,
                ],
            ],
        ], 201);
    }

    #[OA\Get(
        path: '/api/wallet/statement',
        tags: ['Wallet'],
        summary: 'Get account statement with daily consolidation',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'start_date',
                in: 'query',
                required: true,
                description: 'Start date (YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2025-01-01')
            ),
            new OA\Parameter(
                name: 'end_date',
                in: 'query',
                required: true,
                description: 'End date (YYYY-MM-DD) - Maximum 90 days from start_date',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2025-01-31')
            ),
            new OA\Parameter(
                name: 'transaction_type',
                in: 'query',
                required: false,
                description: 'Filter by transaction type',
                schema: new OA\Schema(type: 'string', enum: ['deposit', 'withdraw', 'transfer_in', 'transfer_out'])
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Items per page (1-100)',
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 15)
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Page number',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statement retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-01-15'),
                                    new OA\Property(property: 'opening_balance', type: 'number', format: 'float', example: 1000.00),
                                    new OA\Property(property: 'closing_balance', type: 'number', format: 'float', example: 1500.00),
                                    new OA\Property(property: 'total_credits', type: 'number', format: 'float', example: 500.00),
                                    new OA\Property(property: 'total_debits', type: 'number', format: 'float', example: 0.00),
                                    new OA\Property(property: 'net_change', type: 'number', format: 'float', example: 500.00),
                                    new OA\Property(property: 'transaction_count', type: 'integer', example: 3),
                                    new OA\Property(property: 'transactions', type: 'array', items: new OA\Items(type: 'object'))
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(
                            property: 'summary',
                            properties: [
                                new OA\Property(
                                    property: 'period',
                                    properties: [
                                        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                                        new OA\Property(property: 'end_date', type: 'string', format: 'date')
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'opening_balance', type: 'number', format: 'float'),
                                new OA\Property(property: 'closing_balance', type: 'number', format: 'float'),
                                new OA\Property(property: 'total_credits', type: 'number', format: 'float'),
                                new OA\Property(property: 'total_debits', type: 'number', format: 'float'),
                                new OA\Property(property: 'net_change', type: 'number', format: 'float'),
                                new OA\Property(property: 'total_days', type: 'integer'),
                                new OA\Property(property: 'total_transactions', type: 'integer')
                            ],
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer'),
                                new OA\Property(property: 'per_page', type: 'integer'),
                                new OA\Property(property: 'total', type: 'integer'),
                                new OA\Property(property: 'last_page', type: 'integer')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Account not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    /**
     * Get account statement with daily consolidation
     */
    public function statement(StatementRequest $request): JsonResponse
    {
        $user = auth()->user();
        $account = Account::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$account) {
            throw new InvalidAccountException('Conta não encontrada ou inativa');
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $transactionType = $request->transaction_type;
        $perPage = $request->get('per_page', 15);

        $query = Transaction::where('account_id', $account->id)
            ->with('transactionType')
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->orderBy('created_at', 'desc');

        if ($transactionType) {
            $query->whereHas('transactionType', function ($q) use ($transactionType) {
                $q->where('code', $transactionType);
            });
        }

        $transactions = $query->get();

        $firstTransaction = Transaction::where('account_id', $account->id)
            ->whereDate('created_at', '<', $startDate)
            ->orderBy('created_at', 'desc')
            ->first();

        $openingBalancePeriod = $firstTransaction ? $firstTransaction->balance_after : 0;

        $transactionsByDate = $transactions->groupBy(function ($transaction) {
            return $transaction->created_at->format('Y-m-d');
        });

        $dailyStatements = [];
        $runningBalance = $openingBalancePeriod;

        foreach ($transactionsByDate->sortKeysDesc() as $date => $dayTransactions) {
            $dayOpeningBalance = $runningBalance;

            $totalCredits = 0;
            $totalDebits = 0;

            $processedTransactions = $dayTransactions->sortByDesc('created_at')->map(function ($transaction) use (&$totalCredits, &$totalDebits) {
                $type = $transaction->transactionType->code;

                if (in_array($type, ['DEPOSIT', 'TRANSFER_RECEIVED'])) {
                    $totalCredits += $transaction->amount;
                } else if (in_array($type, ['WITHDRAW', 'TRANSFER_SENT'])) {
                    $totalDebits += $transaction->amount;
                }

                return $transaction;
            });

            $dayClosingBalance = $dayOpeningBalance + $totalCredits - $totalDebits;
            $runningBalance = $dayClosingBalance;

            $dailyStatements[] = [
                'date' => $date,
                'opening_balance' => $dayOpeningBalance,
                'closing_balance' => $dayClosingBalance,
                'total_credits' => $totalCredits,
                'total_debits' => $totalDebits,
                'transaction_count' => $processedTransactions->count(),
                'transactions' => $processedTransactions,
            ];
        }

        $currentPage = $request->get('page', 1);
        $pagedData = array_slice($dailyStatements, ($currentPage - 1) * $perPage, $perPage);
        $total = count($dailyStatements);

        $allTransactions = $transactions;
        $totalCredits = $allTransactions->filter(function ($t) {
            return in_array($t->transactionType->code, ['DEPOSIT', 'TRANSFER_RECEIVED']);
        })->sum('amount');

        $totalDebits = $allTransactions->filter(function ($t) {
            return in_array($t->transactionType->code, ['WITHDRAW', 'TRANSFER_SENT']);
        })->sum('amount');

        $closingBalancePeriod = $openingBalancePeriod + $totalCredits - $totalDebits;

        return response()->json([
            'data' => StatementDayResource::collection(collect($pagedData)),
            'summary' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'opening_balance' => (float) $openingBalancePeriod,
                'closing_balance' => (float) $closingBalancePeriod,
                'total_credits' => (float) $totalCredits,
                'total_debits' => (float) $totalDebits,
                'net_change' => (float) ($totalCredits - $totalDebits),
                'total_days' => count($dailyStatements),
                'total_transactions' => $allTransactions->count(),
            ],
            'meta' => [
                'current_page' => (int) $currentPage,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/wallet/transaction/{id}',
        tags: ['Wallet'],
        summary: 'Get transaction details by ID',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Transaction ID',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transaction details retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'transaction_id', type: 'string', example: 'DEP-20250115123456-1234'),
                                new OA\Property(property: 'amount', type: 'number', format: 'float', example: 500.00),
                                new OA\Property(property: 'transaction_type', type: 'string', example: 'deposit'),
                                new OA\Property(property: 'description', type: 'string', example: 'Depósito via PIX'),
                                new OA\Property(property: 'balance_before', type: 'number', format: 'float', example: 1000.00),
                                new OA\Property(property: 'balance_after', type: 'number', format: 'float', example: 1500.00),
                                new OA\Property(property: 'metadata', type: 'object'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Transaction not found')
        ]
    )]
    /**
     * Get transaction details by ID
     */
    public function transaction(int $id): JsonResponse
    {
        $user = auth()->user();
        $account = Account::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$account) {
            throw new InvalidAccountException('Conta não encontrada ou inativa');
        }

        $transaction = Transaction::where('account_id', $account->id)
            ->where('id', $id)
            ->with('transactionType')
            ->first();

        if (!$transaction) {
            return response()->json([
                'message' => 'Transação não encontrada',
            ], 404);
        }

        return response()->json([
            'data' => new TransactionResource($transaction),
        ]);
    }
}
