<?php

namespace App\Exceptions;

use Exception;

class DailyLimitExceededException extends Exception
{
    protected $message = 'Limite diário excedido';
    protected $code = 422;
    protected $limitType;
    protected $currentUsed;
    protected $dailyLimit;
    protected $attemptedAmount;

    public function __construct(string $limitType, float $currentUsed, float $dailyLimit, float $attemptedAmount)
    {
        $this->limitType = $limitType;
        $this->currentUsed = $currentUsed;
        $this->dailyLimit = $dailyLimit;
        $this->attemptedAmount = $attemptedAmount;

        $available = $dailyLimit - $currentUsed;

        $this->message = sprintf(
            'Limite diário de %s excedido. Utilizado: R$ %.2f, Limite: R$ %.2f, Disponível: R$ %.2f, Tentativa: R$ %.2f',
            $limitType === 'deposit' ? 'depósito' : ($limitType === 'withdraw' ? 'saque' : 'transferência'),
            $currentUsed,
            $dailyLimit,
            $available,
            $attemptedAmount
        );

        parent::__construct($this->message, $this->code);
    }

    public function render()
    {
        return response()->json([
            'data' => [
                'error' => $this->message,
                'code' => 'DAILY_LIMIT_EXCEEDED',
                'details' => [
                    'limit_type' => $this->limitType,
                    'daily_limit' => (float) $this->dailyLimit,
                    'daily_used' => (float) $this->currentUsed,
                    'daily_available' => (float) ($this->dailyLimit - $this->currentUsed),
                    'attempted_amount' => (float) $this->attemptedAmount,
                ],
            ],
        ], $this->code);
    }
}
