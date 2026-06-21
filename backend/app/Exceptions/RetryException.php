<?php

namespace App\Exceptions;

use App\Enums\BalanceRetryStatus;
use Exception;

class RetryException extends Exception
{
    public static function invalidStatus(int $retryId, BalanceRetryStatus $currentStatus): self
    {
        return new self(sprintf(
            '重试任务 %d 状态不正确，当前状态: %s',
            $retryId,
            $currentStatus->label()
        ), 3001);
    }

    public static function maxRetriesReached(int $retryId): self
    {
        return new self(sprintf(
            '重试任务 %d 已达到最大重试次数',
            $retryId
        ), 3002);
    }

    public static function notFound(int $retryId): self
    {
        return new self(sprintf('重试任务 %d 不存在', $retryId), 3003);
    }

    public static function alreadyProcessing(int $retryId): self
    {
        return new self(sprintf('重试任务 %d 正在处理中', $retryId), 3004);
    }
}
