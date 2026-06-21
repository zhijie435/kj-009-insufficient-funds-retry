<?php

namespace App\Models;

use App\Enums\BalanceRetryStatus;
use App\Exceptions\RetryException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

#[Fillable([
    'order_id',
    'user_id',
    'required_amount',
    'current_balance',
    'retry_count',
    'max_retry',
    'status',
    'last_retry_at',
    'next_retry_at',
    'fail_reason',
])]
class BalanceRetry extends Model
{
    protected function casts(): array
    {
        return [
            'required_amount' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'retry_count' => 'integer',
            'max_retry' => 'integer',
            'status' => BalanceRetryStatus::class,
            'last_retry_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', BalanceRetryStatus::PENDING);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', BalanceRetryStatus::PROCESSING);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BalanceRetryStatus::PENDING,
            BalanceRetryStatus::PROCESSING,
        ]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BalanceRetryStatus::SUCCESS,
            BalanceRetryStatus::FAILED,
            BalanceRetryStatus::CANCELLED,
        ]);
    }

    public function scopeDueForRetry(Builder $query): Builder
    {
        return $query->pending()
            ->where('next_retry_at', '<=', now());
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForOrder(Builder $query, Order $order): Builder
    {
        return $query->where('order_id', $order->id);
    }

    public function isRetryable(): bool
    {
        return $this->status->isRetryable()
            && $this->retry_count < $this->max_retry;
    }

    public function isMaxRetriesReached(): bool
    {
        return $this->retry_count >= $this->max_retry;
    }

    public function markAsProcessing(): void
    {
        if (!$this->status->isRetryable()) {
            throw RetryException::invalidStatus($this->id, $this->status);
        }

        $this->update(['status' => BalanceRetryStatus::PROCESSING]);
    }

    public function markAsSuccess(int $currentBalance): void
    {
        $this->update([
            'status' => BalanceRetryStatus::SUCCESS,
            'retry_count' => $this->retry_count + 1,
            'current_balance' => $currentBalance,
            'last_retry_at' => now(),
            'fail_reason' => null,
        ]);
    }

    public function markAsFailed(string $reason, int $currentBalance): void
    {
        $this->update([
            'status' => BalanceRetryStatus::FAILED,
            'retry_count' => $this->retry_count + 1,
            'current_balance' => $currentBalance,
            'last_retry_at' => now(),
            'fail_reason' => $reason,
        ]);
    }

    public function markAsCancelled(string $reason = '手动取消'): void
    {
        if (!$this->status->isRetryable()) {
            throw RetryException::invalidStatus($this->id, $this->status);
        }

        $this->update([
            'status' => BalanceRetryStatus::CANCELLED,
            'fail_reason' => $reason,
        ]);
    }

    public function scheduleNextRetry(int $currentBalance, string $reason = null): int
    {
        $nextRetryMinutes = $this->calculateNextRetryDelay();

        $this->update([
            'status' => BalanceRetryStatus::PENDING,
            'retry_count' => $this->retry_count + 1,
            'current_balance' => $currentBalance,
            'last_retry_at' => now(),
            'next_retry_at' => now()->addMinutes($nextRetryMinutes),
            'fail_reason' => $reason,
        ]);

        return $nextRetryMinutes;
    }

    public function calculateNextRetryDelay(): int
    {
        return min(pow(2, $this->retry_count + 1) * 5, 60);
    }

    public function getStatusTextAttribute(): string
    {
        return $this->status->label();
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['status_text'] = $this->status_text;
        if ($this->relationLoaded('order')) {
            $array['order_no'] = $this->order->order_no;
        }
        return $array;
    }
}
