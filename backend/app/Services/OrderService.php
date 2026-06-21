<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private WalletService $walletService,
    ) {}

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
            } catch (\RuntimeException $e) {
                $order->update([
                    'fail_reason' => $e->getMessage() . " (第{$order->retry_count}次重试)",
                ]);

                if ($order->retry_count >= $order->max_retries) {
                    $order->update(['status' => 'failed']);
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
