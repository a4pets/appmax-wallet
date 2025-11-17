<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'account' => $this->when($this->relationLoaded('account'), function () {
                return [
                    'agency' => $this->account->agency,
                    'account' => $this->account->account,
                    'account_digit' => $this->account->account_digit,
                    'account_number' => $this->account->account_number,
                    'account_type' => $this->account->account_type,
                    'status' => $this->account->status,
                    'balance' => $this->when($this->account->relationLoaded('balance'), function () {
                        return (float) $this->account->balance->amount;
                    }),
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [];
    }
}
