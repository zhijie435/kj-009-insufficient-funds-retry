<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {}

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
        if ($order->status !== 'pending' && $order->status !== 'insufficient_balance') {
            return response()->json(['message' => '订单状态不正确'], 400);
        }

        try {
            $order = $this->orderService->retryOrder($order);

            if ($order->status === 'paid') {
                return response()->json(['message' => '支付成功', 'order' => $order]);
            }

            return response()->json(['message' => '余额不足，已记录重试', 'order' => $order], 400);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
