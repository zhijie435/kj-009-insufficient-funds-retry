<?php

namespace App\Http\Controllers;

use App\Models\BalanceRetry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BalanceRetryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BalanceRetry::with(['order', 'user']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $retries = $query->paginate($request->get('per_page', 15));

        return response()->json($retries);
    }

    public function show(BalanceRetry $balanceRetry): JsonResponse
    {
        return response()->json($balanceRetry->load(['order', 'user']));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'user_id' => 'required|exists:users,id',
            'required_amount' => 'required|numeric|min:0.01',
            'max_retry' => 'nullable|integer|min:1',
        ]);

        $validated['current_balance'] = \App\Models\Wallet::where('user_id', $validated['user_id'])->first()->balance;
        $validated['retry_count'] = 0;
        $validated['status'] = 0;
        $validated['next_retry_at'] = now()->addMinutes(5);

        $retry = BalanceRetry::create($validated);

        return response()->json($retry, 201);
    }

    public function update(Request $request, BalanceRetry $balanceRetry): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|integer',
            'fail_reason' => 'nullable|string',
        ]);

        $balanceRetry->update($validated);

        return response()->json($balanceRetry);
    }

    public function destroy(BalanceRetry $balanceRetry): JsonResponse
    {
        $balanceRetry->delete();

        return response()->json(null, 204);
    }

    public function retry(BalanceRetry $balanceRetry): JsonResponse
    {
        if ($balanceRetry->status !== 0) {
            return response()->json(['message' => '重试任务状态不正确'], 400);
        }

        $order = $balanceRetry->order;

        if (!$order->isRetryable()) {
            $balanceRetry->update([
                'status' => 3,
                'fail_reason' => '订单已达最大重试次数',
            ]);
            $order->update(['status' => 'failed']);
            return response()->json(['message' => '订单已达最大重试次数'], 400);
        }

        if ($balanceRetry->retry_count >= $balanceRetry->max_retry) {
            $balanceRetry->update(['status' => 3, 'fail_reason' => '已达到最大重试次数']);
            $order->update(['status' => 'failed']);
            return response()->json(['message' => '已达到最大重试次数'], 400);
        }

        $balanceRetry->update(['status' => 1]);
        \App\Jobs\BalanceRetryJob::dispatch($order);

        return response()->json(['message' => '已加入重试队列']);
    }

    public function pending(Request $request): JsonResponse
    {
        $query = BalanceRetry::with(['order', 'user'])
            ->where('status', 0)
            ->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at', 'asc');

        $retries = $query->paginate($request->get('per_page', 15));

        return response()->json($retries);
    }

    public function cancel(BalanceRetry $balanceRetry): JsonResponse
    {
        if ($balanceRetry->status !== 0) {
            return response()->json(['message' => '重试任务状态不正确'], 400);
        }

        $balanceRetry->update([
            'status' => 4,
            'fail_reason' => '手动取消',
        ]);

        $balanceRetry->order->update([
            'status' => 'failed',
            'fail_reason' => '重试任务已手动取消',
        ]);

        return response()->json(['message' => '已取消重试']);
    }
}
