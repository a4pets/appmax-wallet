<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Balance extends Model
{
    use HasFactory;
    const UPDATED_AT = 'updated_at';
    const CREATED_AT = null;

    protected $fillable = [
        'account_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the account that owns the balance.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
