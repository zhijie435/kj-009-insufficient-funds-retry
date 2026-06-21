<?php

namespace App\Jobs;

use App\Exceptions\BalanceException;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\WalletService;
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

    public function handle(OrderService $orderService, WalletService $walletService): void
    {
        $order = $this->order;
        $balanceRetry = $order->activeBalanceRetry()->first();

        if (!$balanceRetry) {
            Log::info('BalanceRetry not found for order, skipping', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
            ]);
            return;
        }

        Log::info('Starting balance retry job', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'retry_id' => $balanceRetry->id,
            'retry_count' => $balanceRetry->retry_count,
            'max_retry' => $balanceRetry->max_retry,
        ]);

        if ($balanceRetry->isMaxRetriesReached()) {
            $this->handleMaxRetriesReached($order, $balanceRetry);
            return;
        }

        DB::transaction(function () use ($order, $balanceRetry, $orderService, $walletService) {
            $balanceRetry->markAsProcessing();

            $wallet = $walletService->getOrCreateWallet($order->user);

            if ($walletService->hasSufficientBalance($wallet, $order->amount)) {
                $this->processRetryWithSufficientBalance($order, $balanceRetry, $orderService, $wallet);
            } else {
                $this->processRetryWithInsufficientBalance($order, $balanceRetry, $wallet);
            }
        });
    }

    private function handleMaxRetriesReached(Order $order, $balanceRetry): void
    {
        $wallet = $order->user->wallet;
        $currentBalance = $wallet ? $wallet->balance : 0;

        $balanceRetry->markAsFailed('已达到最大重试次数', $currentBalance);
        $order->markAsFailed('已达到最大重试次数');

        Log::info('Max retry reached for order', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'retry_id' => $balanceRetry->id,
            'retry_count' => $balanceRetry->retry_count,
            'current_balance' => $currentBalance,
        ]);
    }

    private function processRetryWithSufficientBalance(
        Order $order,
        $balanceRetry,
        OrderService $orderService,
        $wallet
    ): void {
        try {
            $retriedOrder = $orderService->retryOrder($order);

            Log::info('Balance retry success for order', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'retry_id' => $balanceRetry->id,
                'new_status' => $retriedOrder->status->label(),
                'retry_count' => $retriedOrder->retry_count,
            ]);
        } catch (OrderException | BalanceException $e) {
            $this->handleRetryException($order, $balanceRetry, $e, $wallet);
        } catch (\Exception $e) {
            $this->handleRetryException($order, $balanceRetry, $e, $wallet);
        }
    }

    private function processRetryWithInsufficientBalance(
        Order $order,
        $balanceRetry,
        $wallet
    ): void {
        $order->incrementRetry();

        if ($order->isMaxRetriesReached()) {
            $this->handleMaxRetriesReached($order, $balanceRetry);

            Log::info('Max retry reached for order (insufficient balance)', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'retry_id' => $balanceRetry->id,
                'required' => $order->amount,
                'current_balance' => $wallet->balance,
            ]);
            return;
        }

        $nextRetryMinutes = $balanceRetry->scheduleNextRetry(
            $wallet->balance,
            '余额仍不足'
        );

        self::dispatch($order)->delay(now()->addMinutes($nextRetryMinutes));

        Log::info('Balance retry scheduled for order (insufficient balance)', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'retry_id' => $balanceRetry->id,
            'retry_count' => $balanceRetry->retry_count,
            'required' => $order->amount,
            'current_balance' => $wallet->balance,
            'next_retry_minutes' => $nextRetryMinutes,
        ]);
    }

    private function handleRetryException(
        Order $order,
        $balanceRetry,
        \Exception $e,
        $wallet
    ): void {
        Log::error('Balance retry failed for order', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'retry_id' => $balanceRetry->id,
            'error' => $e->getMessage(),
            'exception_class' => get_class($e),
            'retry_count' => $balanceRetry->retry_count,
        ]);

        if ($balanceRetry->isMaxRetriesReached()) {
            $balanceRetry->markAsFailed($e->getMessage(), $wallet->balance);
            $order->markAsFailed($e->getMessage());

            Log::info('Max retry reached for order after exception', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'retry_id' => $balanceRetry->id,
            ]);
            return;
        }

        $nextRetryMinutes = $balanceRetry->scheduleNextRetry(
            $wallet->balance,
            $e->getMessage()
        );

        self::dispatch($order)->delay(now()->addMinutes($nextRetryMinutes));

        Log::info('Balance retry scheduled for order after exception', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'retry_id' => $balanceRetry->id,
            'next_retry_minutes' => $nextRetryMinutes,
        ]);
    }
}
