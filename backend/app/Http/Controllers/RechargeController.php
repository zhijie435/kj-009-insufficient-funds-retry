<?php

namespace App\Http\Controllers;

use App\Models\RechargeRecord;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RechargeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RechargeRecord::with(['user', 'order']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('pay_type')) {
            $query->where('pay_type', $request->pay_type);
        }

        $records = $query->paginate($request->get('per_page', 15));

        return response()->json($records);
    }

    public function show(RechargeRecord $rechargeRecord): JsonResponse
    {
        return response()->json($rechargeRecord->load(['user', 'order']));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'order_id' => 'nullable|exists:orders,id',
            'amount' => 'required|numeric|min:0.01',
            'pay_type' => 'required|integer|in:1,2,3',
        ]);

        $validated['transaction_no'] = 'REC' . date('YmdHis') . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $validated['status'] = 0;

        $record = RechargeRecord::create($validated);

        return response()->json($record, 201);
    }

    public function update(Request $request, RechargeRecord $rechargeRecord): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|integer',
            'fail_reason' => 'nullable|string',
        ]);

        if ($validated['status'] == 1 && empty($rechargeRecord->paid_at)) {
            $validated['paid_at'] = now();
        }

        $rechargeRecord->update($validated);

        return response()->json($rechargeRecord);
    }

    public function destroy(RechargeRecord $rechargeRecord): JsonResponse
    {
        $rechargeRecord->delete();

        return response()->json(null, 204);
    }

    public function confirm(RechargeRecord $rechargeRecord): JsonResponse
    {
        if ($rechargeRecord->status !== 0) {
            return response()->json(['message' => '充值记录状态不正确'], 400);
        }

        \DB::transaction(function () use ($rechargeRecord) {
            $user = $rechargeRecord->user;
            $user->increment('balance', $rechargeRecord->amount);

            $rechargeRecord->status = 1;
            $rechargeRecord->paid_at = now();
            $rechargeRecord->save();
        });

        return response()->json(['message' => '充值成功', 'record' => $rechargeRecord->fresh()]);
    }

    public function fail(Request $request, RechargeRecord $rechargeRecord): JsonResponse
    {
        $validated = $request->validate([
            'fail_reason' => 'required|string',
        ]);

        $rechargeRecord->update([
            'status' => 2,
            'fail_reason' => $validated['fail_reason'],
        ]);

        return response()->json(['message' => '充值已标记为失败', 'record' => $rechargeRecord]);
    }
}
