<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Webhook extends Model
{
    protected $fillable = [
        'account_id',
        'url',
        'events',
        'secret',
        'active',
        'retry_count',
        'last_triggered_at',
    ];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
        'retry_count' => 'integer',
        'last_triggered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the account that owns the webhook.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
