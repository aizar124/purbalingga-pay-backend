<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Transaction;
use App\Support\PurbalinggaPayPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $merchantName = trim((string) ($data['merchant_name'] ?? ''));
        $hasExplicitCardSelection = ! empty($data['card_id']) || ! empty($data['card_code']);

        if ($data['type'] === 'topup') {
            $transaction = DB::transaction(function () use ($user, $amount, $data) {
                $referenceCode = $this->generateReferenceCode();

                return Transaction::query()->create([
                    'user_id' => $user->id,
                    'reference' => $referenceCode,
                    'reference_code' => $referenceCode,
                    'type' => 'topup',
                    'title' => $data['title'] ?? 'Top up saldo',
                    'description' => $data['description'] ?? null,
                    'amount' => $amount,
                    'status' => 'pending',
                    'happened_at' => now(),
                ]);
            });

            return response()->json([
                'message' => 'Top up dibuat, silakan lanjut ke simulasi gateway.',
                'transaction' => $this->topupTransactionResponse($transaction),
                'user' => PurbalinggaPayPresenter::user($user->fresh()),
            ], 201);
        }

        $card = null;

        if ($hasExplicitCardSelection && ! empty($data['card_id'])) {
            $cardQuery = Card::query()->where('user_id', $user->id);

            if (ctype_digit($data['card_id'])) {
                $cardQuery->where('id', (int) $data['card_id']);
            } else {
                $cardQuery->where('code', $data['card_id']);
            }

            $card = $cardQuery->firstOrFail();
        } elseif ($hasExplicitCardSelection && ! empty($data['card_code'])) {
            $card = Card::query()
                ->where('user_id', $user->id)
                ->where('code', $data['card_code'])
                ->firstOrFail();
        }

        if ($user->balance < $amount) {
            return response()->json([
                'message' => 'Saldo tidak cukup.',
            ], 422);
        }

        if ($hasExplicitCardSelection && $card && $card->balance_amount < $amount) {
            return response()->json([
                'message' => 'Saldo kartu tidak cukup.',
            ], 422);
        }

        $transaction = DB::transaction(function () use ($user, $amount, $data, $card, $merchantName) {
            $user->decrement('balance', $amount);

            if ($card) {
                $card->decrement('balance_amount', $amount);
                $card->forceFill(['last_used_at' => now()])->save();
            }

            return Transaction::query()->create([
                'user_id' => $user->id,
                'reference' => 'TRX-'.strtoupper(Str::random(8)),
                'type' => 'payment',
                'title' => $data['title']
                    ?? ($merchantName !== '' ? 'Bayar '.$merchantName : 'Pembayaran'),
                'description' => $data['description'] ?? ($merchantName !== '' ? 'Merchant: '.$merchantName : null),
                'amount' => -$amount,
                'status' => 'success',
                'happened_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Transaksi berhasil.',
            'transaction' => PurbalinggaPayPresenter::transaction($transaction),
            'user' => PurbalinggaPayPresenter::user($user->fresh()),
            'card' => $card ? PurbalinggaPayPresenter::card($card->fresh()) : null,
        ], 201);
    }

    public function simulatePayment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:success,failed'],
        ]);

        $transaction = Transaction::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('type', 'topup')
            ->firstOrFail();

        if ($transaction->status !== 'pending') {
            return response()->json([
                'message' => 'Transaksi sudah diproses.',
                'transaction' => $this->topupTransactionResponse($transaction),
                'balance' => $request->user()->fresh()->balance,
            ], 409);
        }

        $user = $request->user();

        $updatedTransaction = DB::transaction(function () use ($transaction, $user, $data) {
            if ($data['action'] === 'success') {
                $user->increment('balance', (int) $transaction->amount);
                $transaction->forceFill(['status' => 'success'])->save();
            } else {
                $transaction->forceFill(['status' => 'failed'])->save();
            }

            return $transaction->fresh();
        });

        $freshUser = $user->fresh();

        return response()->json([
            'message' => $data['action'] === 'success' ? 'Simulasi pembayaran berhasil.' : 'Simulasi pembayaran gagal.',
            'transaction' => $this->topupTransactionResponse($updatedTransaction),
            'user' => PurbalinggaPayPresenter::user($freshUser),
            'balance' => $freshUser->balance,
        ]);
    }

    private function topupTransactionResponse(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'reference_code' => $transaction->reference_code ?? $transaction->reference,
            'nominal' => (int) $transaction->amount,
            'status' => $transaction->status,
            'type' => $transaction->type,
        ];
    }

    private function generateReferenceCode(): string
    {
        do {
            $referenceCode = 'TXN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Transaction::query()->where('reference_code', $referenceCode)->exists());

        return $referenceCode;
    }
}
