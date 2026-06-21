<?php

namespace App\Services;

use App\Exceptions\BalanceException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public function getOrCreateWallet(User $user): Wallet
    {
        return $user->wallet ?? $this->createWallet($user);
    }

    public function createWallet(User $user): Wallet
    {
        Log::info('Creating wallet for user', ['user_id' => $user->id]);

        return Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'frozen' => 0,
        ]);
    }

    public function deposit(
        Wallet $wallet,
        int $amount,
        string $sourceType,
        int $sourceId,
        string $remark = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw BalanceException::invalidAmount($amount);
        }

        return DB::transaction(function () use ($wallet, $amount, $sourceType, $sourceId, $remark) {
            $lockedWallet = Wallet::lockForUpdate()->findOrFail($wallet->id);
            $balanceBefore = $lockedWallet->balance;

            $lockedWallet->increment('balance', $amount);
            $lockedWallet->refresh();

            $transaction = WalletTransaction::create([
                'wallet_id' => $lockedWallet->id,
                'type' => 'deposit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $lockedWallet->balance,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'remark' => $remark,
            ]);

            Log::info('Wallet deposit successful', [
                'wallet_id' => $lockedWallet->id,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $lockedWallet->balance,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);

            return $transaction;
        });
    }

    public function deduct(
        Wallet $wallet,
        int $amount,
        string $sourceType,
        int $sourceId,
        string $remark = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw BalanceException::invalidAmount($amount);
        }

        return DB::transaction(function () use ($wallet, $amount, $sourceType, $sourceId, $remark) {
            $lockedWallet = Wallet::lockForUpdate()->findOrFail($wallet->id);

            if (!$this->hasSufficientBalance($lockedWallet, $amount)) {
                Log::warning('Insufficient balance for deduction', [
                    'wallet_id' => $lockedWallet->id,
                    'required' => $amount,
                    'available' => $lockedWallet->available_balance,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ]);

                throw BalanceException::insufficient($amount, $lockedWallet->available_balance);
            }

            $balanceBefore = $lockedWallet->balance;
            $lockedWallet->decrement('balance', $amount);
            $lockedWallet->refresh();

            $transaction = WalletTransaction::create([
                'wallet_id' => $lockedWallet->id,
                'type' => 'deduct',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $lockedWallet->balance,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'remark' => $remark,
            ]);

            Log::info('Wallet deduction successful', [
                'wallet_id' => $lockedWallet->id,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $lockedWallet->balance,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);

            return $transaction;
        });
    }

    public function hasSufficientBalance(Wallet $wallet, int $amount): bool
    {
        return $wallet->available_balance >= $amount;
    }

    public function getBalance(Wallet $wallet): int
    {
        return $wallet->balance;
    }

    public function getAvailableBalance(Wallet $wallet): int
    {
        return $wallet->available_balance;
    }

    public function getTransactions(Wallet $wallet, array $filters = [])
    {
        return $wallet->transactions()
            ->when($filters['type'] ?? null, function ($query, $type) {
                $query->where('type', $type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }
}
