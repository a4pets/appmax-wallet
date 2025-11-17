<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'account_id' => $this->account_id,
            'type' => $this->when($this->relationLoaded('transactionType'), strtolower($this->transactionType->code)),
            'transaction_type' => $this->when($this->relationLoaded('transactionType'), function () {
                return [
                    'id' => $this->transactionType->id,
                    'code' => $this->transactionType->code,
                    'name' => $this->transactionType->name,
                ];
            }),
            'flow' => $this->flow,
            'amount' => (float) $this->amount,
            'balance_before' => (float) $this->balance_before,
            'balance_after' => (float) $this->balance_after,
            'description' => $this->description,
            'reference' => $this->reference,
            'metadata' => $this->metadata,
            'is_contested' => $this->is_contested,
            'contestation' => $this->when($this->is_contested, function () {
                return [
                    'contested_at' => $this->contested_at?->toISOString(),
                    'contested_reason' => $this->contested_reason,
                    'contestation_transaction_id' => $this->contestation_transaction_id,
                ];
            }),
            'is_chargebacked' => $this->is_chargebacked,
            'chargeback_of_transaction_id' => $this->chargeback_of_transaction_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
