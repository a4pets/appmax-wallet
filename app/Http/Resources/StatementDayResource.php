<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatementDayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this['date'],
            'opening_balance' => (float) $this['opening_balance'],
            'closing_balance' => (float) $this['closing_balance'],
            'total_credits' => (float) $this['total_credits'],
            'total_debits' => (float) $this['total_debits'],
            'net_change' => (float) ($this['total_credits'] - $this['total_debits']),
            'transaction_count' => $this['transaction_count'],
            'transactions' => StatementTransactionResource::collection($this['transactions']),
        ];
    }
}
