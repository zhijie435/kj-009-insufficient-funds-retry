<?php

namespace App\Services;

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
                'status' => 'pending',
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

            $transaction->update([
                'status' => 'completed',
                'paid_at' => now(),
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

        $orders = Order::where('user_id', $user->id)
            ->where('status', 'insufficient_balance')
            ->orderBy('created_at', 'asc')
            ->get();

        $result['total'] = $orders->count();

        foreach ($orders as $order) {
            if (!$order->isRetryable()) {
                $result['failed']++;

                $order->update(['status' => 'failed']);

                $balanceRetry = $order->balanceRetries()
                    ->whereIn('status', [0, 1])
                    ->first();
                if ($balanceRetry) {
                    $balanceRetry->update([
                        'status' => 3,
                        'fail_reason' => '已达到最大重试次数',
                    ]);
                }

                $result['orders'][] = [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'title' => $order->title,
                    'amount' => $order->amount,
                    'status' => 'not_retryable',
                    'message' => '订单已达最大重试次数',
                ];
                continue;
            }

            try {
                $retriedOrder = $this->orderService->retryOrder($order);
                $orderResult = [
                    'order_id' => $retriedOrder->id,
                    'order_no' => $retriedOrder->order_no,
                    'title' => $retriedOrder->title,
                    'amount' => $retriedOrder->amount,
                    'status' => $retriedOrder->status,
                ];

                if ($retriedOrder->status === 'paid') {
                    $result['success']++;
                    $orderResult['message'] = '支付成功';
                } elseif ($retriedOrder->status === 'insufficient_balance') {
                    $result['still_insufficient']++;
                    $orderResult['message'] = '余额仍不足，请继续充值';
                } else {
                    $result['failed']++;
                    $orderResult['message'] = $retriedOrder->fail_reason ?? '处理失败';
                }

                $result['orders'][] = $orderResult;
            } catch (\Exception $e) {
                $result['failed']++;
                $result['orders'][] = [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'title' => $order->title,
                    'amount' => $order->amount,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
                Log::error("充值后自动重试订单失败: 订单ID={$order->id}, 错误={$e->getMessage()}");
            }
        }

        return $result;
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
