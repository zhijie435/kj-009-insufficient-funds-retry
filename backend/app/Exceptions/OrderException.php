<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use Exception;

class OrderException extends Exception
{
    public static function notRetryable(int $orderId, OrderStatus $status): self
    {
        return new self(sprintf(
            '订单 %d 不可重试，当前状态: %s',
            $orderId,
            $status->label()
        ), 2001);
    }

    public static function maxRetriesReached(int $orderId): self
    {
        return new self(sprintf(
            '订单 %d 已达到最大重试次数',
            $orderId
        ), 2002);
    }

    public static function invalidStatusTransition(int $orderId, OrderStatus $from, OrderStatus $to): self
    {
        return new self(sprintf(
            '订单 %d 状态转换无效: %s -> %s',
            $orderId,
            $from->label(),
            $to->label()
        ), 2003);
    }

    public static function notFound(int $orderId): self
    {
        return new self(sprintf('订单 %d 不存在', $orderId), 2004);
    }
}
