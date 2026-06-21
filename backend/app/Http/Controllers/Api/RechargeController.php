<?php

namespace App\Http\Controllers\Api;

use App\Enums\RechargeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RechargeStoreRequest;
use App\Models\RechargeTransaction;
use App\Services\RechargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RechargeController extends Controller
{
    public function __construct(
        private RechargeService $rechargeService,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', RechargeTransaction::class);

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::enum(RechargeStatus::class)],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $recharges = $this->rechargeService->getUserRecharges(
            $request->user(),
            $validated
        );

        return response()->json($recharges);
    }

    public function store(RechargeStoreRequest $request): JsonResponse
    {
        Gate::authorize('create', RechargeTransaction::class);

        $validated = $request->validated();
        $result = $this->rechargeService->createRecharge(
            $request->user(),
            $validated['amount'],
            $validated['payment_method'] ?? 'manual'
        );

        $transaction = $result['transaction'];
        $retryResult = $result['retry_result'];

        $message = $this->buildRechargeMessage($retryResult);

        return response()->json([
            'message' => $message,
            'data' => $transaction,
            'retry_result' => $retryResult,
        ], 201);
    }

    public function show(Request $request, RechargeTransaction $rechargeTransaction): JsonResponse
    {
        Gate::authorize('view', $rechargeTransaction);

        return response()->json([
            'data' => $rechargeTransaction,
        ]);
    }

    private function buildRechargeMessage(array $retryResult): string
    {
        if ($retryResult['total'] === 0) {
            return '充值成功';
        }

        if ($retryResult['success'] > 0 && $retryResult['still_insufficient'] === 0) {
            return "充值成功，已自动完成 {$retryResult['success']} 笔订单支付";
        }

        if ($retryResult['success'] > 0 && $retryResult['still_insufficient'] > 0) {
            return "充值成功，{$retryResult['success']} 笔订单已支付，{$retryResult['still_insufficient']} 笔订单余额仍不足";
        }

        if ($retryResult['still_insufficient'] > 0) {
            return "充值成功，{$retryResult['still_insufficient']} 笔订单余额仍不足，请继续充值";
        }

        return "充值成功，自动处理 {$retryResult['total']} 笔订单";
    }
}
