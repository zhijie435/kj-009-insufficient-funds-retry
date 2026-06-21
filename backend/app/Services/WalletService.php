<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getOrCreateWallet(User $user): Wallet
    {
        return $user->wallet ?? $this->createWallet($user);
    }

    public function createWallet(User $user): Wallet
    {
        return Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'frozen' => 0,
        ]);
    }

    public function deposit(Wallet $wallet, int $amount, string $sourceType, int $sourceId, string $remark = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $sourceType, $sourceId, $remark) {
            $wallet = Wallet::lockForUpdate()->find($wallet->id);
            $balanceBefore = $wallet->balance;
            $wallet->increment('balance', $amount);
            $wallet->refresh();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'deposit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'remark' => $remark,
            ]);
        });
    }

    public function deduct(Wallet $wallet, int $amount, string $sourceType, int $sourceId, string $remark = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $sourceType, $sourceId, $remark) {
            $wallet = Wallet::lockForUpdate()->find($wallet->id);

            if ($wallet->available_balance < $amount) {
                throw new \RuntimeException('余额不足');
            }

            $balanceBefore = $wallet->balance;
            $wallet->decrement('balance', $amount);
            $wallet->refresh();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'deduct',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'remark' => $remark,
            ]);
        });
    }

    public function hasSufficientBalance(Wallet $wallet, int $amount): bool
    {
        return $wallet->available_balance >= $amount;
    }
}
