<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'order_id',
    'transaction_no',
    'amount',
    'pay_type',
    'status',
    'paid_at',
    'fail_reason',
])]
class RechargeRecord extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'pay_type' => 'integer',
            'status' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getStatusTextAttribute(): string
    {
        $statuses = [
            0 => '待支付',
            1 => '成功',
            2 => '失败',
        ];
        return $statuses[$this->status] ?? '未知';
    }

    public function getPayTypeTextAttribute(): string
    {
        $types = [
            1 => '微信',
            2 => '支付宝',
            3 => '银行卡',
        ];
        return $types[$this->pay_type] ?? '未知';
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['status_text'] = $this->status_text;
        $array['pay_type_text'] = $this->pay_type_text;
        return $array;
    }
}
