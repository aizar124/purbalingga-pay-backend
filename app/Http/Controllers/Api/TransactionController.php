<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Transaction;
use App\Support\PurbalinggaPayPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $transactions = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->latest('happened_at')
            ->get();

        return response()->json([
            'transactions' => PurbalinggaPayPresenter::transactions($transactions),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:topup,payment'],
            'amount' => ['required', 'integer', 'min:1000'],
            'title' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'merchant_name' => ['nullable', 'string', 'max:120'],
            'card_id' => ['nullable', 'string', 'max:50'],
            'card_code' => ['nullable', 'string', 'exists:cards,code'],
        ]);

        $user = $request->user();
        $amount = (int) $data['amount'];
        $signedAmount = $data['type'] === 'topup' ? $amount : -$amount;

        if ($data['type'] === 'payment' && $user->balance < $amount) {
            return response()->json([
                'message' => 'Saldo tidak cukup.',
            ], 422);
        }

        $card = null;

        if (! empty($data['card_id'])) {
            $cardQuery = Card::query()->where('user_id', $user->id);

            if (ctype_digit($data['card_id'])) {
                $cardQuery->where('id', (int) $data['card_id']);
            } else {
                $cardQuery->where('code', $data['card_id']);
            }

            $card = $cardQuery->firstOrFail();
        } elseif (! empty($data['card_code'])) {
            $card = Card::query()
                ->where('user_id', $user->id)
                ->where('code', $data['card_code'])
                ->firstOrFail();
        } else {
            $card = Card::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->orderBy('id')
                ->first();
        }

        if ($card && $data['type'] === 'payment' && $card->balance_amount < $amount) {
            return response()->json([
                'message' => 'Saldo kartu tidak cukup.',
            ], 422);
        }

        if ($data['type'] === 'topup') {
            $user->increment('balance', $amount);

            if ($card) {
                $card->increment('balance_amount', $amount);
            }
        } else {
            $user->decrement('balance', $amount);

            if ($card) {
                $card->decrement('balance_amount', $amount);
            }
        }

        if ($card) {
            $card->forceFill(['last_used_at' => now()])->save();
        }

        $merchantName = trim((string) ($data['merchant_name'] ?? ''));

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'reference' => 'TRX-'.strtoupper(Str::random(8)),
            'type' => $data['type'],
            'title' => $data['title']
                ?? ($data['type'] === 'topup'
                    ? 'Top up saldo'
                    : ($merchantName !== '' ? 'Bayar '.$merchantName : 'Pembayaran')),
            'description' => $data['description'] ?? ($merchantName !== '' ? 'Merchant: '.$merchantName : null),
            'amount' => $signedAmount,
            'status' => 'completed',
            'happened_at' => now(),
        ]);

        return response()->json([
            'message' => 'Transaksi berhasil.',
            'transaction' => PurbalinggaPayPresenter::transaction($transaction),
            'user' => PurbalinggaPayPresenter::user($user->fresh()),
            'card' => $card ? PurbalinggaPayPresenter::card($card->fresh()) : null,
        ], 201);
    }
}
