<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'account_id',
        'transaction_type_id',
        'flow',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'transaction_id',
        'metadata',
        'is_chargebacked',
        'chargeback_of_transaction_id',
        'is_contested',
        'contested_at',
        'contested_reason',
        'contestation_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
        'is_chargebacked' => 'boolean',
        'is_contested' => 'boolean',
        'contested_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the account that owns the transaction.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the transaction type.
     */
    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    /**
     * Get the related account (for transfers).
     */
    public function relatedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'related_account_id');
    }
}
