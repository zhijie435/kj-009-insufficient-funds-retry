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
    ) {}

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
        $wallet = $this->walletService->getOrCreateWallet($request->user());

        $transactions = $wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($transactions);
    }
}
