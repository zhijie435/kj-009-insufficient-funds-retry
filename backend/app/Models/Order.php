<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use Illuminate\Database\Eloquent\Builder;
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
            'status' => OrderStatus::class,
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

    public function activeBalanceRetry(): HasMany
    {
        return $this->balanceRetries()->active();
    }

    public function rechargeRecords(): HasMany
    {
        return $this->hasMany(RechargeRecord::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::PENDING);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::PAID);
    }

    public function scopeInsufficientBalance(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::INSUFFICIENT_BALANCE);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::FAILED);
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->insufficientBalance()
            ->whereColumn('retry_count', '<', 'max_retries');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeWithSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('order_no', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%");
        });
    }

    public function isRetryable(): bool
    {
        return $this->status->isRetryable()
            && $this->retry_count < $this->max_retries;
    }

    public function isMaxRetriesReached(): bool
    {
        return $this->retry_count >= $this->max_retries;
    }

    public function transitionTo(OrderStatus $newStatus, ?string $failReason = null): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw OrderException::invalidStatusTransition($this->id, $this->status, $newStatus);
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === OrderStatus::PAID) {
            $updateData['fail_reason'] = null;
        } elseif ($newStatus === OrderStatus::FAILED || $newStatus === OrderStatus::INSUFFICIENT_BALANCE) {
            $updateData['failed_at'] = now();
            if ($failReason !== null) {
                $updateData['fail_reason'] = $failReason;
            }
        }

        $this->update($updateData);
    }

    public function markAsPaid(): void
    {
        $this->transitionTo(OrderStatus::PAID);
    }

    public function markAsInsufficientBalance(string $reason): void
    {
        $this->transitionTo(OrderStatus::INSUFFICIENT_BALANCE, $reason);
    }

    public function markAsFailed(string $reason): void
    {
        $this->transitionTo(OrderStatus::FAILED, $reason);
    }

    public function incrementRetry(): void
    {
        $this->increment('retry_count');
        $this->update(['retried_at' => now()]);
        $this->refresh();
    }

    public function getRetryAttemptText(): string
    {
        return "第{$this->retry_count}次重试";
    }

    public function getStatusTextAttribute(): string
    {
        return $this->status->label();
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['status_text'] = $this->status_text;
        return $array;
    }

    public static function generateOrderNo(): string
    {
        return 'ORD' . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
