<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RechargeStoreRequest;
use App\Models\RechargeTransaction;
use App\Services\RechargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RechargeController extends Controller
{
    public function __construct(
        private RechargeService $rechargeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $recharges = $this->rechargeService->getUserRecharges(
            $request->user(),
            $request->only(['status', 'per_page'])
        );

        return response()->json($recharges);
    }

    public function store(RechargeStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->rechargeService->createRecharge(
            $request->user(),
            $validated['amount'],
            $validated['payment_method'] ?? 'manual'
        );

        $transaction = $result['transaction'];
        $retryResult = $result['retry_result'];

        $message = '充值成功';
        if ($retryResult['total'] > 0) {
            if ($retryResult['success'] > 0 && $retryResult['still_insufficient'] === 0) {
                $message = "充值成功，已自动完成 {$retryResult['success']} 笔订单支付";
            } elseif ($retryResult['success'] > 0 && $retryResult['still_insufficient'] > 0) {
                $message = "充值成功，{$retryResult['success']} 笔订单已支付，{$retryResult['still_insufficient']} 笔订单余额仍不足";
            } elseif ($retryResult['still_insufficient'] > 0) {
                $message = "充值成功，{$retryResult['still_insufficient']} 笔订单余额仍不足，请继续充值";
            } else {
                $message = "充值成功，自动处理 {$retryResult['total']} 笔订单";
            }
        }

        return response()->json([
            'message' => $message,
            'data' => $transaction,
            'retry_result' => $retryResult,
        ], 201);
    }

    public function show(Request $request, RechargeTransaction $rechargeTransaction): JsonResponse
    {
        if ($rechargeTransaction->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json([
            'data' => $rechargeTransaction,
        ]);
    }
}
