<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            'status' => 'integer',
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

    public function getStatusTextAttribute(): string
    {
        $statuses = [
            0 => '待重试',
            1 => '重试中',
            2 => '成功',
            3 => '失败',
            4 => '已取消',
        ];
        return $statuses[$this->status] ?? '未知';
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
