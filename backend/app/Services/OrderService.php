<?php

namespace App\Services;

use App\Models\BalanceRetry;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private WalletService $walletService,
    ) {}

    private function syncBalanceRetry(Order $order, string $event): void
    {
        $retry = $order->balanceRetries()
            ->whereIn('status', [0, 1])
            ->first();

        if (!$retry) {
            return;
        }

        $wallet = $this->walletService->getOrCreateWallet($order->user);

        switch ($event) {
            case 'paid':
                $retry->update([
                    'status' => 2,
                    'retry_count' => $order->retry_count,
                    'current_balance' => $wallet->balance,
                    'last_retry_at' => now(),
                    'fail_reason' => null,
                ]);
                break;
            case 'failed':
                $retry->update([
                    'status' => 3,
                    'retry_count' => $order->retry_count,
                    'current_balance' => $wallet->balance,
                    'last_retry_at' => now(),
                    'fail_reason' => $order->fail_reason,
                ]);
                break;
            case 'still_insufficient':
                $retry->update([
                    'retry_count' => $order->retry_count,
                    'current_balance' => $wallet->balance,
                    'last_retry_at' => now(),
                    'next_retry_at' => now()->addMinutes(5),
                    'fail_reason' => $order->fail_reason,
                ]);
                break;
        }
    }

    public function createOrder(User $user, string $title, int $amount, int $maxRetries = 3): Order
    {
        return DB::transaction(function () use ($user, $title, $amount, $maxRetries) {
            $wallet = $this->walletService->getOrCreateWallet($user);

            $order = Order::create([
                'order_no' => Order::generateOrderNo(),
                'user_id' => $user->id,
                'title' => $title,
                'amount' => $amount,
                'status' => 'pending',
                'max_retries' => $maxRetries,
            ]);

            try {
                $this->walletService->deduct(
                    $wallet,
                    $amount,
                    Order::class,
                    $order->id,
                    "订单扣款: {$order->order_no}"
                );

                $order->update(['status' => 'paid']);
            } catch (\RuntimeException $e) {
                $order->update([
                    'status' => 'insufficient_balance',
                    'failed_at' => now(),
                    'fail_reason' => $e->getMessage(),
                ]);

                BalanceRetry::create([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'required_amount' => $amount,
                    'current_balance' => $wallet->balance,
                    'retry_count' => 0,
                    'max_retry' => $maxRetries,
                    'status' => 0,
                    'next_retry_at' => now()->addMinutes(5),
                ]);
            }

            return $order->fresh();
        });
    }

    public function retryOrder(Order $order): Order
    {
        if (!$order->isRetryable()) {
            throw new \RuntimeException('该订单不可重试');
        }

        return DB::transaction(function () use ($order) {
            $wallet = $this->walletService->getOrCreateWallet($order->user);

            $order->increment('retry_count');
            $order->update(['retried_at' => now()]);
            $order->refresh();

            try {
                $this->walletService->deduct(
                    $wallet,
                    $order->amount,
                    Order::class,
                    $order->id,
                    "订单重试扣款: {$order->order_no}"
                );

                $order->update([
                    'status' => 'paid',
                    'fail_reason' => null,
                ]);

                $this->syncBalanceRetry($order, 'paid');
            } catch (\RuntimeException $e) {
                $order->update([
                    'fail_reason' => $e->getMessage() . " (第{$order->retry_count}次重试)",
                ]);

                if ($order->retry_count >= $order->max_retries) {
                    $order->update(['status' => 'failed']);
                    $this->syncBalanceRetry($order, 'failed');
                } else {
                    $this->syncBalanceRetry($order, 'still_insufficient');
                }
            }

            return $order->fresh();
        });
    }

    public function getUserOrders(User $user, array $filters = [])
    {
        return Order::where('user_id', $user->id)
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_no', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }
}
