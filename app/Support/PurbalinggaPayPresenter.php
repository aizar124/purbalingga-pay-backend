<?php

namespace App\Support;

use App\Models\Card;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PurbalinggaPayPresenter
{
    public static function money(int $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }

    public static function signedMoney(int $value): string
    {
        $prefix = $value >= 0 ? '+ ' : '- ';

        return $prefix.self::money(abs($value));
    }

    public static function activityMeta(CarbonInterface $date): string
    {
        $now = now();

        if ($date->isSameDay($now)) {
            return 'Hari ini, '.$date->format('H:i');
        }

        if ($date->isSameDay($now->copy()->subDay())) {
            return 'Kemarin, '.$date->format('H:i');
        }

        return $date->translatedFormat('d M Y, H:i');
    }

    public static function user(User $user): array
    {
        return [
            'name' => $user->name,
            'role' => $user->role,
            'balance' => $user->balance,
            'status' => $user->status,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatarUrl' => $user->avatar_url,
        ];
    }

    public static function card(Card $card): array
    {
        return [
            'id' => $card->code,
            'label' => $card->label,
            'status' => self::statusLabel($card->status),
            'limit' => self::money($card->limit_amount),
            'balance' => self::money($card->balance_amount),
            'rawStatus' => $card->status,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function cards(Collection|array $cards): array
    {
        $items = $cards instanceof Collection ? $cards->all() : $cards;

        return array_map(fn (Card $card) => self::card($card), $items);
    }

    public static function transaction(Transaction $transaction): array
    {
        return [
            'id' => $transaction->reference,
            'title' => $transaction->title,
            'meta' => self::activityMeta($transaction->happened_at),
            'amount' => self::signedMoney($transaction->amount),
            'tone' => $transaction->amount >= 0 ? 'positive' : 'negative',
            'type' => $transaction->type,
            'status' => $transaction->status,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function transactions(Collection|array $transactions): array
    {
        $items = $transactions instanceof Collection ? $transactions->all() : $transactions;

        return array_map(fn (Transaction $transaction) => self::transaction($transaction), $items);
    }

    public static function voucher(Voucher $voucher): array
    {
        return [
            'id' => $voucher->code,
            'title' => $voucher->title,
            'desc' => $voucher->description,
            'status' => self::statusLabel($voucher->status),
            'value' => self::money($voucher->value_amount),
            'rawStatus' => $voucher->status,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function vouchers(Collection|array $vouchers): array
    {
        $items = $vouchers instanceof Collection ? $vouchers->all() : $vouchers;

        return array_map(fn (Voucher $voucher) => self::voucher($voucher), $items);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Aktif',
            'locked' => 'Terkunci',
            'available' => 'Tersedia',
            'limited' => 'Terbatas',
            'redeemed' => 'Sudah Dipakai',
            'blocked' => 'Diblokir',
            'pending' => 'Pending',
            'failed' => 'Gagal',
            default => ucfirst($status),
        };
    }
}
