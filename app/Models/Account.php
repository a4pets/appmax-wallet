<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'agency',
        'account',
        'account_digit',
        'account_number',
        'account_type',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns the account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the balance for the account.
     */
    public function balance(): HasOne
    {
        return $this->hasOne(Balance::class);
    }

    /**
     * Get the transactions for the account.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the transfers sent from this account.
     */
    public function transfersSent(): HasMany
    {
        return $this->hasMany(Transfer::class, 'sender_account_id');
    }

    /**
     * Get the transfers received by this account.
     */
    public function transfersReceived(): HasMany
    {
        return $this->hasMany(Transfer::class, 'receiver_account_id');
    }

    /**
     * Get the daily limits for the account.
     */
    public function dailyLimits(): HasMany
    {
        return $this->hasMany(DailyLimit::class);
    }

    /**
     * Get the webhooks for the account.
     */
    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }
}
