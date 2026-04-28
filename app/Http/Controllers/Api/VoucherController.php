<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Support\PurbalinggaPayPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vouchers = Voucher::query()
            ->where(function ($query) use ($request) {
                $query->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->latest('id')
            ->get();

        return response()->json([
            'vouchers' => PurbalinggaPayPresenter::vouchers($vouchers),
        ]);
    }

    public function redeem(Request $request, Voucher $voucher): JsonResponse
    {
        abort_unless($voucher->user_id === null || $voucher->user_id === $request->user()->id, 404);

        if ($voucher->status === 'redeemed') {
            return response()->json([
                'message' => 'Voucher sudah pernah dipakai.',
            ], 422);
        }

        $user = $request->user();

        if ($user->balance < $voucher->value_amount) {
            return response()->json([
                'message' => 'Saldo tidak cukup untuk redeem voucher.',
            ], 422);
        }

        $user->decrement('balance', $voucher->value_amount);
        $voucher->forceFill([
            'user_id' => $user->id,
            'status' => 'redeemed',
            'redeemed_at' => now(),
        ])->save();

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'reference' => 'TRX-'.strtoupper(Str::random(8)),
            'type' => 'redeem',
            'title' => 'Redeem voucher',
            'description' => $voucher->title,
            'amount' => -$voucher->value_amount,
            'status' => 'completed',
            'happened_at' => now(),
        ]);

        return response()->json([
            'message' => 'Voucher berhasil diredeem.',
            'voucher' => PurbalinggaPayPresenter::voucher($voucher->fresh()),
            'transaction' => PurbalinggaPayPresenter::transaction($transaction),
            'user' => PurbalinggaPayPresenter::user($user->fresh()),
        ]);
    }
}
