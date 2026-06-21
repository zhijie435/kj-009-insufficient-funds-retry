<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case INSUFFICIENT_BALANCE = 'insufficient_balance';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待支付',
            self::PAID => '已支付',
            self::INSUFFICIENT_BALANCE => '余额不足待重试',
            self::FAILED => '支付失败',
            self::CANCELLED => '已取消',
        };
    }

    public function isRetryable(): bool
    {
        return $this === self::INSUFFICIENT_BALANCE;
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        $allowedTransitions = [
            self::PENDING->value => [self::PAID->value, self::INSUFFICIENT_BALANCE->value, self::CANCELLED->value],
            self::INSUFFICIENT_BALANCE->value => [self::PAID->value, self::FAILED->value, self::CANCELLED->value, self::INSUFFICIENT_BALANCE->value],
            self::FAILED->value => [],
            self::PAID->value => [],
            self::CANCELLED->value => [],
        ];

        return in_array($newStatus->value, $allowedTransitions[$this->value] ?? [], true);
    }
}
