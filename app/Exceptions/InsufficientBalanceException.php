<?php

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    protected $message = 'Saldo insuficiente para completar a operação';
    protected $code = 422;

    public function __construct(float $currentBalance, float $requiredAmount)
    {
        $this->message = sprintf(
            'Saldo insuficiente. Saldo atual: R$ %.2f, Valor requerido: R$ %.2f',
            $currentBalance,
            $requiredAmount
        );

        parent::__construct($this->message, $this->code);
    }

    public function render()
    {
        return response()->json([
            'data' => [
                'error' => $this->message,
                'code' => 'INSUFFICIENT_BALANCE',
            ],
        ], $this->code);
    }
}
