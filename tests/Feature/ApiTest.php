<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiTest extends TestCase
{
    public function test_authenticates_demo_user_and_returns_token(): void
    {
        $this->prepareDatabaseOrSkip();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'demo@purbalingga.pay',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token_type',
                'access_token',
                'user' => ['name', 'role', 'balance', 'email'],
            ]);
    }

    public function test_returns_dashboard_data_for_authenticated_user(): void
    {
        $this->prepareDatabaseOrSkip();

        $user = User::query()->firstWhere('email', 'demo@purbalingga.pay');
        $token = 'test-token-123';

        ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['name', 'role', 'balance'],
                'cards',
                'transactions',
                'vouchers',
                'stats',
            ]);
    }

    public function test_rejects_invalid_credentials(): void
    {
        $this->prepareDatabaseOrSkip();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'demo@purbalingga.pay',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_processes_qr_payment_payload_and_reduces_balance(): void
    {
        $this->prepareDatabaseOrSkip();

        $user = User::query()->firstWhere('email', 'admin@purbalingga.id');
        $card = Card::query()->firstWhere('code', 'PBG-001');
        $token = 'test-token-qr-123';

        ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions', [
                'type' => 'payment',
                'amount' => 18500,
                'card_id' => 'PBG-001',
                'merchant_name' => 'Warung Kopi',
            ]);

        $response->assertCreated()
            ->assertJsonPath('transaction.title', 'Bayar Warung Kopi')
            ->assertJsonPath('transaction.amount', '- Rp 18.500');

        $this->assertSame(1231500, $user->fresh()->balance);
        $this->assertSame(1231500, $card->fresh()->balance_amount);
    }

    public function test_creates_topup_as_pending_and_can_simulate_success(): void
    {
        $this->prepareDatabaseOrSkip();

        $user = User::query()->firstWhere('email', 'demo@purbalingga.pay');
        $token = 'test-token-topup-123';

        ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions', [
                'type' => 'topup',
                'amount' => 25000,
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('transaction.status', 'pending')
            ->assertJsonPath('transaction.nominal', 25000)
            ->assertJsonStructure([
                'transaction' => ['id', 'reference_code', 'nominal', 'status'],
            ]);

        $transactionId = $createResponse->json('transaction.id');
        $referenceCode = $createResponse->json('transaction.reference_code');

        $this->assertNotEmpty($referenceCode);
        $this->assertSame(1000000, $user->fresh()->balance);

        $simulateResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/transactions/'.$transactionId.'/simulate-payment', [
                'action' => 'success',
            ]);

        $simulateResponse->assertOk()
            ->assertJsonPath('transaction.status', 'success')
            ->assertJsonPath('balance', 1025000);

        $this->assertSame(1025000, $user->fresh()->balance);
    }

    private function prepareDatabaseOrSkip(): void
    {
        if (! $this->databaseIsUsable()) {
            $this->markTestSkipped('Database driver unavailable in this environment.');
        }

        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true]);
    }

    private function databaseIsUsable(): bool
    {
        $driver = config('database.default');

        if ($driver === 'sqlite' && ! extension_loaded('pdo_sqlite')) {
            return false;
        }

        if ($driver === 'mysql' && ! extension_loaded('pdo_mysql')) {
            return false;
        }

        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
