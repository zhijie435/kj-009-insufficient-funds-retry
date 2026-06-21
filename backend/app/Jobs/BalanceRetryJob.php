<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BalanceRetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public function __construct(
        protected Order $order
    ) {}

    public function handle(OrderService $orderService, WalletService $walletService): void
    {
        $order = $this->order->fresh();
        $balanceRetry = $order->balanceRetries()->whereIn('status', [0, 1])->first();

        if (!$balanceRetry) {
            Log::info("BalanceRetry not found for order {$order->id}");
            return;
        }

        if ($balanceRetry->retry_count >= $balanceRetry->max_retry) {
            $balanceRetry->update([
                'status' => 3,
                'fail_reason' => '已达到最大重试次数',
            ]);
            $order->update(['status' => 'failed']);
            Log::info("Max retry reached for order {$order->id}");
            return;
        }

        $balanceRetry->update(['status' => 1]);

        $wallet = $walletService->getOrCreateWallet($order->user);

        if ($walletService->hasSufficientBalance($wallet, $order->amount)) {
            try {
                $retriedOrder = $orderService->retryOrder($order);
                Log::info("Balance retry success for order {$order->id}, status: {$retriedOrder->status}");
            } catch (\Exception $e) {
                Log::error("Balance retry failed for order {$order->id}: {$e->getMessage()}");

                $balanceRetry->increment('retry_count');
                $order->increment('retry_count');

                if ($balanceRetry->fresh()->retry_count >= $balanceRetry->max_retry) {
                    $balanceRetry->update([
                        'status' => 3,
                        'last_retry_at' => now(),
                        'current_balance' => $wallet->balance,
                        'fail_reason' => $e->getMessage(),
                    ]);
                    $order->update(['status' => 'failed', 'fail_reason' => $e->getMessage()]);
                } else {
                    $nextRetryMinutes = min(pow(2, $balanceRetry->fresh()->retry_count) * 5, 60);
                    $balanceRetry->update([
                        'status' => 0,
                        'last_retry_at' => now(),
                        'current_balance' => $wallet->balance,
                        'next_retry_at' => now()->addMinutes($nextRetryMinutes),
                        'fail_reason' => $e->getMessage(),
                    ]);
                    self::dispatch($order)->delay(now()->addMinutes($nextRetryMinutes));
                    Log::info("Balance retry scheduled for order {$order->id} (exception), next retry in {$nextRetryMinutes} minutes");
                }
            }
        } else {
            $balanceRetry->increment('retry_count');
            $order->increment('retry_count');

            $updatedRetry = $balanceRetry->fresh();

            if ($updatedRetry->retry_count >= $updatedRetry->max_retry) {
                $updatedRetry->update([
                    'status' => 3,
                    'last_retry_at' => now(),
                    'current_balance' => $wallet->balance,
                    'fail_reason' => '余额仍不足，已达最大重试次数',
                ]);
                $order->update([
                    'status' => 'failed',
                    'fail_reason' => '余额仍不足，已达最大重试次数',
                ]);
                Log::info("Max retry reached for order {$order->id} (insufficient balance)");
                return;
            }

            $nextRetryMinutes = min(pow(2, $updatedRetry->retry_count) * 5, 60);
            $updatedRetry->update([
                'status' => 0,
                'next_retry_at' => now()->addMinutes($nextRetryMinutes),
            ]);

            self::dispatch($order)->delay(now()->addMinutes($nextRetryMinutes));

            Log::info("Balance retry scheduled for order {$order->id}, next retry in {$nextRetryMinutes} minutes");
        }
    }
}
