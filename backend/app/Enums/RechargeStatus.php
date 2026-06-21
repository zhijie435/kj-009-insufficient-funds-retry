<?php

namespace App\Enums;

enum RechargeStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '处理中',
            self::COMPLETED => '已完成',
            self::FAILED => '失败',
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        $allowedTransitions = [
            self::PENDING->value => [self::COMPLETED->value, self::FAILED->value],
            self::COMPLETED->value => [],
            self::FAILED->value => [],
        ];

        return in_array($newStatus->value, $allowedTransitions[$this->value] ?? [], true);
    }
}
