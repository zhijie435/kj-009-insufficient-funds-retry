<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BalanceException;
use App\Exceptions\OrderException;
use App\Models\BalanceRetry;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        private WalletService $walletService,
    ) {}

    private function syncBalanceRetry(Order $order, string $event): void
    {
        $retry = $order->activeBalanceRetry()->first();

        if (!$retry) {
            return;
        }

        $wallet = $this->walletService->getOrCreateWallet($order->user);

        switch ($event) {
            case 'paid':
                $retry->markAsSuccess($wallet->balance);
                break;
            case 'failed':
                $retry->markAsFailed($order->fail_reason, $wallet->balance);
                break;
            case 'still_insufficient':
                $nextRetryMinutes = $retry->scheduleNextRetry(
                    $wallet->balance,
                    $order->fail_reason
                );
                \App\Jobs\BalanceRetryJob::dispatch($order)
                    ->delay(now()->addMinutes($nextRetryMinutes));

                Log::info('Balance retry scheduled for order', [
                    'order_id' => $order->id,
                    'next_retry_minutes' => $nextRetryMinutes,
                    'retry_count' => $order->retry_count,
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
                'status' => OrderStatus::PENDING,
                'max_retries' => $maxRetries,
            ]);

            Log::info('Order created', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'user_id' => $user->id,
                'amount' => $amount,
            ]);

            try {
                $this->walletService->deduct(
                    $wallet,
                    $amount,
                    Order::class,
                    $order->id,
                    "订单扣款: {$order->order_no}"
                );

                $order->markAsPaid();

                Log::info('Order payment successful', [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                ]);
            } catch (BalanceException $e) {
                $failReason = $e->getMessage();
                $order->markAsInsufficientBalance($failReason);

                BalanceRetry::create([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'required_amount' => $amount,
                    'current_balance' => $wallet->balance,
                    'retry_count' => 0,
                    'max_retry' => $maxRetries,
                    'status' => \App\Enums\BalanceRetryStatus::PENDING,
                    'next_retry_at' => now()->addMinutes(5),
                ]);

                Log::warning('Order payment failed - insufficient balance', [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'required' => $amount,
                    'current_balance' => $wallet->balance,
                    'fail_reason' => $failReason,
                ]);
            }

            return $order->fresh();
        });
    }

    public function retryOrder(Order $order): Order
    {
        if (!$order->isRetryable()) {
            throw OrderException::notRetryable($order->id, $order->status);
        }

        return DB::transaction(function () use ($order) {
            $wallet = $this->walletService->getOrCreateWallet($order->user);

            $order->incrementRetry();

            Log::info('Retrying order payment', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'retry_count' => $order->retry_count,
                'max_retries' => $order->max_retries,
            ]);

            try {
                $this->walletService->deduct(
                    $wallet,
                    $order->amount,
                    Order::class,
                    $order->id,
                    "订单重试扣款: {$order->order_no}"
                );

                $order->markAsPaid();
                $this->syncBalanceRetry($order, 'paid');

                Log::info('Order retry payment successful', [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'retry_count' => $order->retry_count,
                ]);
            } catch (BalanceException $e) {
                $failReason = $e->getMessage() . " ({$order->getRetryAttemptText()})";

                $order->update(['fail_reason' => $failReason]);

                if ($order->isMaxRetriesReached()) {
                    $order->markAsFailed($failReason);
                    $this->syncBalanceRetry($order, 'failed');

                    Log::warning('Order retry failed - max retries reached', [
                        'order_id' => $order->id,
                        'order_no' => $order->order_no,
                        'retry_count' => $order->retry_count,
                        'fail_reason' => $failReason,
                    ]);
                } else {
                    $this->syncBalanceRetry($order, 'still_insufficient');

                    Log::info('Order retry failed - will retry again', [
                        'order_id' => $order->id,
                        'order_no' => $order->order_no,
                        'retry_count' => $order->retry_count,
                        'fail_reason' => $failReason,
                    ]);
                }
            }

            return $order->fresh();
        });
    }

    public function getUserOrders(User $user, array $filters = [])
    {
        return Order::forUser($user)
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->withSearch($search);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getRetryableOrdersForUser(User $user)
    {
        return Order::forUser($user)->retryable()->get();
    }
}
