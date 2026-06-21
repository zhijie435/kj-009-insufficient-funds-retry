<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderStoreRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::enum(OrderStatus::class)],
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $orders = $this->orderService->getUserOrders(
            $request->user(),
            $validated
        );

        return response()->json($orders);
    }

    public function store(OrderStoreRequest $request): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $validated = $request->validated();
        $order = $this->orderService->createOrder(
            $request->user(),
            $validated['title'],
            $validated['amount']
        );

        $message = $order->status === OrderStatus::INSUFFICIENT_BALANCE
            ? '余额不足，订单待充值后重试'
            : '订单创建成功';

        return response()->json([
            'message' => $message,
            'data' => $order,
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return response()->json([
            'data' => $order,
        ]);
    }

    public function retry(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('retry', $order);

        try {
            $order = $this->orderService->retryOrder($order);

            $message = $order->status === OrderStatus::PAID
                ? '重试成功，订单已支付'
                : '余额仍不足，请充值后重试';

            return response()->json([
                'message' => $message,
                'data' => $order,
            ]);
        } catch (OrderException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 422);
        }
    }
}
