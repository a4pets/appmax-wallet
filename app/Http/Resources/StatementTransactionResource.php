<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatementTransactionResource extends JsonResource
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
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'time' => $this->created_at->format('H:i:s'),
            'balance_after' => (float) $this->balance_after,
            'related_account' => $this->related_account_id ? [
                'account_number' => $this->relatedAccount?->account_number,
            ] : null,
        ];
    }
}
