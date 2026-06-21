<?php

namespace App\Models;

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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusTextAttribute(): string
    {
        $statuses = [
            'pending' => '处理中',
            'completed' => '已完成',
            'failed' => '失败',
        ];
        return $statuses[$this->status] ?? '未知';
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
