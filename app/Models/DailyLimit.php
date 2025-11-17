<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyLimit extends Model
{
    protected $fillable = [
        'account_id',
        'limit_type',
        'daily_limit',
        'current_used',
        'reset_at',
    ];

    protected $casts = [
        'daily_limit' => 'decimal:2',
        'current_used' => 'decimal:2',
        'reset_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the account that owns the daily limit.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
