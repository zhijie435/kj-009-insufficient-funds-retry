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

    public static function generateTransactionNo(): string
    {
        return 'RCH' . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
