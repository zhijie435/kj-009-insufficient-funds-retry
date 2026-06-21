<?php

namespace App\Http\Controllers;

use App\Enums\BalanceRetryStatus;
use App\Exceptions\RetryException;
use App\Models\BalanceRetry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BalanceRetryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BalanceRetry::class);

        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'order_id' => 'nullable|exists:orders,id',
            'status' => ['nullable', Rule::enum(BalanceRetryStatus::class)],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = BalanceRetry::with(['order', 'user'])
            ->when($validated['user_id'] ?? null, fn ($q, $v) => $q->forUser(\App\Models\User::find($v)))
            ->when($validated['order_id'] ?? null, fn ($q, $v) => $q->forOrder(\App\Models\Order::find($v)))
            ->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('created_at', 'desc');

        $retries = $query->paginate($validated['per_page'] ?? 15);

        return response()->json($retries);
    }

    public function show(BalanceRetry $balanceRetry): JsonResponse
    {
        Gate::authorize('view', $balanceRetry);

        return response()->json($balanceRetry->load(['order', 'user']));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', BalanceRetry::class);

        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'user_id' => 'required|exists:users,id',
            'required_amount' => 'required|numeric|min:0.01',
            'max_retry' => 'nullable|integer|min:1|max:10',
        ]);

        $wallet = \App\Models\Wallet::where('user_id', $validated['user_id'])->first();

        $balanceRetry = BalanceRetry::create([
            'order_id' => $validated['order_id'],
            'user_id' => $validated['user_id'],
            'required_amount' => $validated['required_amount'],
            'current_balance' => $wallet?->balance ?? 0,
            'retry_count' => 0,
            'max_retry' => $validated['max_retry'] ?? 3,
            'status' => BalanceRetryStatus::PENDING,
            'next_retry_at' => now()->addMinutes(5),
        ]);

        return response()->json($balanceRetry, 201);
    }

    public function update(Request $request, BalanceRetry $balanceRetry): JsonResponse
    {
        Gate::authorize('update', $balanceRetry);

        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(BalanceRetryStatus::class)],
            'fail_reason' => 'nullable|string|max:255',
        ]);

        $balanceRetry->update($validated);

        return response()->json($balanceRetry);
    }

    public function destroy(BalanceRetry $balanceRetry): JsonResponse
    {
        Gate::authorize('delete', $balanceRetry);

        $balanceRetry->delete();

        return response()->json(null, 204);
    }

    public function retry(BalanceRetry $balanceRetry): JsonResponse
    {
        Gate::authorize('retry', $balanceRetry);

        if (!$balanceRetry->status->isRetryable()) {
            throw RetryException::invalidStatus($balanceRetry->id, $balanceRetry->status);
        }

        $order = $balanceRetry->order;

        if (!$order->isRetryable()) {
            $balanceRetry->markAsFailed(
                '订单已达最大重试次数',
                $order->user->wallet->balance ?? 0
            );
            $order->markAsFailed('已达到最大重试次数');

            return response()->json([
                'message' => '订单已达最大重试次数',
            ], 400);
        }

        if ($balanceRetry->isMaxRetriesReached()) {
            throw RetryException::maxRetriesReached($balanceRetry->id);
        }

        $balanceRetry->markAsProcessing();
        \App\Jobs\BalanceRetryJob::dispatch($order);

        return response()->json([
            'message' => '已加入重试队列',
            'data' => $balanceRetry->fresh(),
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        Gate::authorize('pending', BalanceRetry::class);

        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = BalanceRetry::with(['order', 'user'])
            ->dueForRetry()
            ->orderBy('next_retry_at', 'asc');

        $retries = $query->paginate($validated['per_page'] ?? 15);

        return response()->json($retries);
    }

    public function cancel(BalanceRetry $balanceRetry): JsonResponse
    {
        Gate::authorize('cancel', $balanceRetry);

        $balanceRetry->markAsCancelled();
        $balanceRetry->order->markAsFailed('重试任务已手动取消');

        return response()->json([
            'message' => '已取消重试',
            'data' => $balanceRetry->fresh(),
        ]);
    }
}
