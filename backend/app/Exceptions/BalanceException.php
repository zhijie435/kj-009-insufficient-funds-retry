<?php

namespace App\Exceptions;

use Exception;

class BalanceException extends Exception
{
    public static function insufficient(int $required, int $available): self
    {
        return new self(sprintf(
            '余额不足，需要 %d，当前可用余额 %d',
            $required,
            $available
        ), 1001);
    }

    public static function walletNotFound(int $userId): self
    {
        return new self(sprintf('用户 %d 的钱包不存在', $userId), 1002);
    }

    public static function invalidAmount(int $amount): self
    {
        return new self(sprintf('无效的金额: %d', $amount), 1003);
    }
}
