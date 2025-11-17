<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
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
            'from_account_number' => $this->whenLoaded('senderAccount', fn() => $this->senderAccount->account_number),
            'receiver_account_number' => $this->whenLoaded('receiverAccount', fn() => $this->receiverAccount->account_number),
            'sender_account_id' => $this->sender_account_id,
            'receiver_account_id' => $this->receiver_account_id,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'status' => $this->status,
            'reference' => $this->reference,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
