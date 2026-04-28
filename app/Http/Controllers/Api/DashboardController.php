<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Support\PurbalinggaPayPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $cards = Card::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get();

        $transactions = Transaction::query()
            ->where('user_id', $user->id)
            ->latest('happened_at')
            ->limit(12)
            ->get();

        $vouchers = Voucher::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')->orWhere('user_id', $user->id);
            })
            ->latest('id')
            ->get();

        return response()->json([
            'user' => PurbalinggaPayPresenter::user($user),
            'cards' => PurbalinggaPayPresenter::cards($cards),
            'transactions' => PurbalinggaPayPresenter::transactions($transactions),
            'vouchers' => PurbalinggaPayPresenter::vouchers($vouchers),
            'stats' => [
                'cards_active' => $cards->where('status', 'active')->count(),
                'transactions_today' => Transaction::query()
                    ->where('user_id', $user->id)
                    ->whereDate('happened_at', today())
                    ->count(),
                'vouchers_available' => $vouchers->where('status', 'available')->count(),
            ],
        ]);
    }
}
