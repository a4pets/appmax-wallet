<?php

namespace App\Exceptions;

use Exception;

class InvalidTransferException extends Exception
{
    protected $message = 'Transferência inválida';
    protected $code = 422;

    public function __construct(string $reason)
    {
        $this->message = $reason;
        parent::__construct($this->message, $this->code);
    }

    public function render()
    {
        return response()->json([
            'data' => [
                'error' => $this->message,
                'code' => 'INVALID_TRANSFER',
            ],
        ], $this->code);
    }
}
