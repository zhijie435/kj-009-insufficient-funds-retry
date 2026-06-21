<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderStoreRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->getUserOrders(
            $request->user(),
            $request->only(['status', 'search', 'per_page'])
        );

        return response()->json($orders);
    }

    public function store(OrderStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $order = $this->orderService->createOrder(
            $request->user(),
            $validated['title'],
            $validated['amount']
        );

        $message = $order->status === 'insufficient_balance'
            ? '余额不足，订单待充值后重试'
            : '订单创建成功';

        return response()->json([
            'message' => $message,
            'data' => $order,
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json([
            'data' => $order,
        ]);
    }

    public function retry(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        try {
            $order = $this->orderService->retryOrder($order);

            $message = $order->status === 'paid'
                ? '重试成功，订单已支付'
                : '余额仍不足，请充值后重试';

            return response()->json([
                'message' => $message,
                'data' => $order,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
