<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function show(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet($request->user());

        return response()->json([
            'data' => [
                'balance' => $wallet->balance,
                'frozen' => $wallet->frozen,
                'available_balance' => $wallet->available_balance,
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string|in:deposit,deduct',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $wallet = $this->walletService->getOrCreateWallet($request->user());

        $transactions = $this->walletService->getTransactions($wallet, $validated);

        return response()->json($transactions);
    }
}
