<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RechargeStatus;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\RechargeTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RechargeService
{
    public function __construct(
        private WalletService $walletService,
        private OrderService $orderService,
    ) {}

    public function createRecharge(User $user, int $amount, string $paymentMethod = 'manual'): array
    {
        return DB::transaction(function () use ($user, $amount, $paymentMethod) {
            $transaction = RechargeTransaction::create([
                'transaction_no' => RechargeTransaction::generateTransactionNo(),
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'status' => RechargeStatus::PENDING,
            ]);

            Log::info('Recharge transaction created', [
                'transaction_id' => $transaction->id,
                'transaction_no' => $transaction->transaction_no,
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
            ]);

            $this->completeRecharge($transaction);

            $retryResult = $this->retryInsufficientBalanceOrders($user);

            return [
                'transaction' => $transaction->fresh(),
                'retry_result' => $retryResult,
            ];
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

            $transaction->markAsCompleted();

            Log::info('Recharge completed', [
                'transaction_id' => $transaction->id,
                'transaction_no' => $transaction->transaction_no,
                'user_id' => $transaction->user_id,
                'amount' => $transaction->amount,
                'new_balance' => $wallet->balance,
            ]);

            return $transaction->fresh();
        });
    }

    public function retryInsufficientBalanceOrders(User $user): array
    {
        $result = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'still_insufficient' => 0,
            'orders' => [],
        ];

        $query = Order::forUser($user)
            ->insufficientBalance()
            ->orderBy('created_at', 'asc');

        $result['total'] = $query->count();

        if ($result['total'] === 0) {
            return $result;
        }

        $query->chunk(100, function ($orders) use (&$result) {
            foreach ($orders as $order) {
                $this->processRetryOrder($order, $result);
            }
        });

        Log::info('Retry insufficient balance orders completed', [
            'user_id' => $user->id,
            'total' => $result['total'],
            'success' => $result['success'],
            'failed' => $result['failed'],
            'still_insufficient' => $result['still_insufficient'],
        ]);

        return $result;
    }

    private function processRetryOrder(Order $order, array &$result): void
    {
        if (!$order->isRetryable()) {
            $this->handleNonRetryableOrder($order, $result);
            return;
        }

        try {
            $retriedOrder = $this->orderService->retryOrder($order);
            $this->handleRetryResult($retriedOrder, $result);
        } catch (OrderException $e) {
            $this->handleRetryException($order, $e, $result);
        } catch (\Exception $e) {
            $this->handleRetryException($order, $e, $result);
        }
    }

    private function handleNonRetryableOrder(Order $order, array &$result): void
    {
        $result['failed']++;

        $order->markAsFailed('已达到最大重试次数');

        $balanceRetry = $order->activeBalanceRetry()->first();
        if ($balanceRetry) {
            $balanceRetry->markAsFailed('已达到最大重试次数', $order->user->wallet->balance ?? 0);
        }

        $result['orders'][] = [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'title' => $order->title,
            'amount' => $order->amount,
            'status' => 'not_retryable',
            'message' => '订单已达最大重试次数',
        ];

        Log::warning('Order not retryable during recharge retry', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'retry_count' => $order->retry_count,
            'max_retries' => $order->max_retries,
        ]);
    }

    private function handleRetryResult(Order $retriedOrder, array &$result): void
    {
        $orderResult = [
            'order_id' => $retriedOrder->id,
            'order_no' => $retriedOrder->order_no,
            'title' => $retriedOrder->title,
            'amount' => $retriedOrder->amount,
            'status' => $retriedOrder->status->value,
        ];

        if ($retriedOrder->status === OrderStatus::PAID) {
            $result['success']++;
            $orderResult['message'] = '支付成功';
        } elseif ($retriedOrder->status === OrderStatus::INSUFFICIENT_BALANCE) {
            $result['still_insufficient']++;
            $orderResult['message'] = '余额仍不足，请继续充值';
        } else {
            $result['failed']++;
            $orderResult['message'] = $retriedOrder->fail_reason ?? '处理失败';
        }

        $result['orders'][] = $orderResult;
    }

    private function handleRetryException(Order $order, \Exception $e, array &$result): void
    {
        $result['failed']++;
        $result['orders'][] = [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'title' => $order->title,
            'amount' => $order->amount,
            'status' => 'error',
            'message' => $e->getMessage(),
        ];

        Log::error('Recharge auto-retry order failed', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'error' => $e->getMessage(),
            'exception_class' => get_class($e),
        ]);
    }

    public function getUserRecharges(User $user, array $filters = [])
    {
        return RechargeTransaction::forUser($user)
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }
}
