<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate([
            'email' => 'admin@purbalingga.id',
        ], [
            'name' => 'Admin Purbalingga',
            'role' => 'superadmin',
            'phone' => '0812-3456-7890',
            'avatar_url' => null,
            'balance' => 1250000,
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        Card::query()->updateOrCreate([
            'code' => 'PBG-001',
        ], [
            'user_id' => $user->id,
            'label' => 'Kartu Utama',
            'status' => 'active',
            'limit_amount' => 2500000,
            'balance_amount' => 1250000,
            'currency' => 'IDR',
        ]);

        Card::query()->updateOrCreate([
            'code' => 'PBG-002',
        ], [
            'user_id' => $user->id,
            'label' => 'Kartu Cadangan',
            'status' => 'locked',
            'limit_amount' => 1000000,
            'balance_amount' => 250000,
            'currency' => 'IDR',
        ]);

        Transaction::query()->updateOrCreate([
            'reference' => 'TRX-2048',
        ], [
            'user_id' => $user->id,
            'type' => 'topup',
            'title' => 'Top up saldo',
            'description' => 'Top up awal demo',
            'amount' => 250000,
            'status' => 'completed',
            'happened_at' => now()->subDay()->setTime(9, 24),
        ]);

        Transaction::query()->updateOrCreate([
            'reference' => 'TRX-2047',
        ], [
            'user_id' => $user->id,
            'type' => 'payment',
            'title' => 'Bayar QRIS',
            'description' => 'Pembayaran merchant lokal',
            'amount' => -18500,
            'status' => 'completed',
            'happened_at' => now()->subDays(2)->setTime(18, 10),
        ]);

        Transaction::query()->updateOrCreate([
            'reference' => 'TRX-2046',
        ], [
            'user_id' => $user->id,
            'type' => 'redeem',
            'title' => 'Redeem voucher',
            'description' => 'Voucher parkir',
            'amount' => -50000,
            'status' => 'completed',
            'happened_at' => now()->subDays(3)->setTime(14, 2),
        ]);

        Voucher::query()->updateOrCreate([
            'code' => 'VCH-01',
        ], [
            'title' => 'Diskon Parkir',
            'description' => 'Potongan Rp 5.000 untuk parkir area publik',
            'status' => 'available',
            'value_amount' => 5000,
        ]);

        Voucher::query()->updateOrCreate([
            'code' => 'VCH-02',
        ], [
            'title' => 'Promo UMKM',
            'description' => 'Cashback 10% untuk merchant lokal',
            'status' => 'active',
            'value_amount' => 25000,
        ]);

        Voucher::query()->updateOrCreate([
            'code' => 'VCH-03',
        ], [
            'title' => 'Voucher NFC',
            'description' => 'Kuota transaksi NFC gratis hari ini',
            'status' => 'limited',
            'value_amount' => 10000,
        ]);
    }
}
