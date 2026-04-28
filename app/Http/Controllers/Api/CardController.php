<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Support\PurbalinggaPayPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cards = Card::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'cards' => PurbalinggaPayPresenter::cards($cards),
        ]);
    }

    public function block(Request $request, Card $card): JsonResponse
    {
        $this->authorizeCard($request, $card);

        $card->update(['status' => 'locked']);

        return response()->json([
            'message' => 'Kartu berhasil diblokir.',
            'card' => PurbalinggaPayPresenter::card($card->fresh()),
        ]);
    }

    public function unlock(Request $request, Card $card): JsonResponse
    {
        $this->authorizeCard($request, $card);

        $card->update(['status' => 'active']);

        return response()->json([
            'message' => 'Kartu berhasil dibuka.',
            'card' => PurbalinggaPayPresenter::card($card->fresh()),
        ]);
    }

    private function authorizeCard(Request $request, Card $card): void
    {
        abort_unless($card->user_id === $request->user()->id, 404);
    }
}
