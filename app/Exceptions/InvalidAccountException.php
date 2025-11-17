<?php

namespace App\Exceptions;

use Exception;

class InvalidAccountException extends Exception
{
    protected $message = 'Conta inválida ou inexistente';
    protected $code = 404;

    public function __construct(string $reason = 'Conta não encontrada ou inativa')
    {
        $this->message = $reason;
        parent::__construct($this->message, $this->code);
    }

    public function render()
    {
        return response()->json([
            'data' => [
                'error' => $this->message,
                'code' => 'INVALID_ACCOUNT',
            ],
        ], $this->code);
    }
}
