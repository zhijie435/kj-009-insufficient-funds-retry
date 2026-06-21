<?php

namespace App\Enums;

enum BalanceRetryStatus: int
{
    case PENDING = 0;
    case PROCESSING = 1;
    case SUCCESS = 2;
    case FAILED = 3;
    case CANCELLED = 4;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待重试',
            self::PROCESSING => '重试中',
            self::SUCCESS => '成功',
            self::FAILED => '失败',
            self::CANCELLED => '已取消',
        };
    }

    public function isRetryable(): bool
    {
        return $this === self::PENDING;
    }

    public function isProcessing(): bool
    {
        return $this === self::PROCESSING;
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED, self::CANCELLED], true);
    }
}
