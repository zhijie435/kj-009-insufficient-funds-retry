<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    protected $fillable = [
        'order_no',
        'user_id',
        'title',
        'amount',
        'status',
        'retry_count',
        'max_retries',
        'failed_at',
        'fail_reason',
        'retried_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'retry_count' => 'integer',
            'max_retries' => 'integer',
            'failed_at' => 'datetime',
            'retried_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransactions(): MorphMany
    {
        return $this->morphMany(WalletTransaction::class, 'source');
    }

    public function balanceRetries(): HasMany
    {
        return $this->hasMany(BalanceRetry::class);
    }

    public function rechargeRecords(): HasMany
    {
        return $this->hasMany(RechargeRecord::class);
    }

    public function isRetryable(): bool
    {
        return $this->status === 'insufficient_balance'
            && $this->retry_count < $this->max_retries;
    }

    public static function generateOrderNo(): string
    {
        return 'ORD' . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
