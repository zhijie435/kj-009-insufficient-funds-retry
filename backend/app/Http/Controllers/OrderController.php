<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'rechargeRecords', 'balanceRetries']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate($request->get('per_page', 15));

        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load(['user', 'rechargeRecords', 'balanceRetries']));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'remark' => 'nullable|string',
        ]);

        $validated['order_no'] = 'ORD' . date('YmdHis') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $validated['status'] = 0;
        $validated['retry_count'] = 0;

        $order = Order::create($validated);

        return response()->json($order, 201);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|integer',
            'remark' => 'nullable|string',
        ]);

        $order->update($validated);

        return response()->json($order);
    }

    public function destroy(Order $order): JsonResponse
    {
        $order->delete();

        return response()->json(null, 204);
    }

    public function pay(Order $order): JsonResponse
    {
        if ($order->status !== 0) {
            return response()->json(['message' => '订单状态不正确'], 400);
        }

        $user = $order->user;

        if ($user->balance < $order->amount) {
            $order->increment('retry_count');
            $order->status = 2;
            $order->retry_at = now()->addMinutes(5);
            $order->save();

            \App\Models\BalanceRetry::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'required_amount' => $order->amount,
                'current_balance' => $user->balance,
                'retry_count' => 0,
                'max_retry' => 5,
                'status' => 0,
                'next_retry_at' => now()->addMinutes(5),
            ]);

            \App\Jobs\BalanceRetryJob::dispatch($order)->delay(now()->addMinutes(5));

            return response()->json(['message' => '余额不足，已加入重试队列'], 400);
        }

        \DB::transaction(function () use ($order, $user) {
            $user->decrement('balance', $order->amount);
            $order->status = 1;
            $order->save();

            \App\Models\RechargeRecord::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'transaction_no' => 'TXN' . date('YmdHis') . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT),
                'amount' => $order->amount,
                'pay_type' => 1,
                'status' => 1,
                'paid_at' => now(),
            ]);
        });

        return response()->json(['message' => '支付成功', 'order' => $order->fresh()]);
    }
}
