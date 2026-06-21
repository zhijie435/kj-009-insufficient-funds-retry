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
        $transaction = $this->rechargeService->createRecharge(
            $request->user(),
            $validated['amount'],
            $validated['payment_method'] ?? 'manual'
        );

        return response()->json([
            'message' => '充值成功',
            'data' => $transaction,
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
