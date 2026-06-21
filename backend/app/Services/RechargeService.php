<?php

namespace App\Services;

use App\Models\RechargeTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RechargeService
{
    public function __construct(
        private WalletService $walletService,
    ) {}

    public function createRecharge(User $user, int $amount, string $paymentMethod = 'manual'): RechargeTransaction
    {
        return DB::transaction(function () use ($user, $amount, $paymentMethod) {
            $transaction = RechargeTransaction::create([
                'transaction_no' => RechargeTransaction::generateTransactionNo(),
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'status' => 'pending',
            ]);

            $this->completeRecharge($transaction);

            return $transaction->fresh();
        });
    }

    public function completeRecharge(RechargeTransaction $transaction): RechargeTransaction
    {
        return DB::transaction(function () use ($transaction) {
            $wallet = $this->walletService->getOrCreateWallet($transaction->user);

            $this->walletService->deposit(
                $wallet,
                $transaction->amount,
                RechargeTransaction::class,
                $transaction->id,
                "充值到账: {$transaction->transaction_no}"
            );

            $transaction->update([
                'status' => 'completed',
                'paid_at' => now(),
            ]);

            return $transaction->fresh();
        });
    }

    public function getUserRecharges(User $user, array $filters = [])
    {
        return RechargeTransaction::where('user_id', $user->id)
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }
}
