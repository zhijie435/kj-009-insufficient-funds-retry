<?php

namespace App\Models;

use App\Enums\RechargeStatus;
use App\Exceptions\OrderException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RechargeTransaction extends Model
{
    protected $fillable = [
        'transaction_no',
        'user_id',
        'amount',
        'payment_method',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'status' => RechargeStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RechargeStatus::PENDING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', RechargeStatus::COMPLETED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', RechargeStatus::FAILED);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function transitionTo(RechargeStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw OrderException::invalidStatusTransition(
                $this->id,
                $this->status,
                $newStatus
            );
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === RechargeStatus::COMPLETED) {
            $updateData['paid_at'] = now();
        }

        $this->update($updateData);
    }

    public function markAsCompleted(): void
    {
        $this->transitionTo(RechargeStatus::COMPLETED);
    }

    public function markAsFailed(): void
    {
        $this->transitionTo(RechargeStatus::FAILED);
    }

    public function getStatusTextAttribute(): string
    {
        return $this->status->label();
    }

    public function getPayTypeTextAttribute(): string
    {
        $methods = [
            'manual' => '手动充值',
            'alipay' => '支付宝',
            'wechat' => '微信',
            'bank' => '银行卡',
        ];
        return $methods[$this->payment_method] ?? $this->payment_method;
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['status_text'] = $this->status_text;
        $array['pay_type_text'] = $this->pay_type_text;
        return $array;
    }

    public static function generateTransactionNo(): string
    {
        return 'RCH' . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
