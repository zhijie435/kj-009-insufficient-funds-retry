<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalanceRetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public function __construct(
        protected Order $order
    ) {}

    public function handle(): void
    {
        $order = $this->order->fresh();
        $user = $order->user;
        $balanceRetry = $order->balanceRetries()->where('status', 0)->first();

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

        $balanceRetry->increment('retry_count');
        $balanceRetry->update([
            'last_retry_at' => now(),
            'current_balance' => $user->balance,
        ]);

        if ($user->balance >= $order->amount) {
            DB::transaction(function () use ($order, $user, $balanceRetry) {
                $user->decrement('balance', $order->amount);
                $order->update([
                    'status' => 1,
                    'retry_count' => $order->retry_count + 1,
                ]);

                \App\Models\RechargeRecord::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'transaction_no' => 'TXN' . date('YmdHis') . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT),
                    'amount' => $order->amount,
                    'pay_type' => 1,
                    'status' => 1,
                    'paid_at' => now(),
                ]);

                $balanceRetry->update(['status' => 2]);
            });

            Log::info("Balance retry success for order {$order->id}");
        } else {
            $nextRetryMinutes = min(pow(2, $balanceRetry->retry_count) * 5, 60);
            $balanceRetry->update([
                'next_retry_at' => now()->addMinutes($nextRetryMinutes),
            ]);
            $order->update(['retry_at' => now()->addMinutes($nextRetryMinutes)]);

            self::dispatch($order)->delay(now()->addMinutes($nextRetryMinutes));

            Log::info("Balance retry scheduled for order {$order->id}, next retry in {$nextRetryMinutes} minutes");
        }
    }
}
