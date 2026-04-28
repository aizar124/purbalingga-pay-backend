<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\PurbalinggaPayPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password tidak valid.',
            ], 422);
        }

        $plainToken = Str::random(64);

        ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => ['*'],
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'message' => 'Login berhasil.',
            'token_type' => 'Bearer',
            'access_token' => $plainToken,
            'user' => PurbalinggaPayPresenter::user($user->fresh()),
        ]);
    }

    public function ssoLogin(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $ssoBaseUrl = rtrim(config('services.sso.base_url'), '/');
        $userinfoPath = '/'.ltrim((string) config('services.sso.userinfo_path', '/oauth/userinfo'), '/');

        $response = Http::acceptJson()
            ->withToken($payload['access_token'])
            ->timeout(10)
            ->get($ssoBaseUrl.$userinfoPath);

        if (! $response->ok()) {
            return response()->json([
                'message' => 'Token SSO tidak valid.',
            ], 401);
        }

        $profile = $response->json();
        $email = $profile['email'] ?? null;

        if (! $email) {
            return response()->json([
                'message' => 'Profil SSO tidak lengkap.',
            ], 422);
        }

        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $user->name = $profile['name'] ?? $user->name ?? $email;
        $user->role = $profile['role'] ?? $user->role ?? 'Warga';
        $user->avatar_url = $profile['picture'] ?? $user->avatar_url ?? null;

        if (! $user->exists) {
            $user->balance = 0;
            $user->status = 'active';
            $user->phone = $profile['phone'] ?? null;
            $user->password = Str::random(64);
        } else {
            $user->phone = $user->phone ?? ($profile['phone'] ?? null);
            $user->status = $user->status ?? 'active';
        }

        $user->save();

        $plainToken = Str::random(64);

        ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'sso',
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => ['*'],
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'message' => 'Login SSO berhasil.',
            'token_type' => 'Bearer',
            'access_token' => $plainToken,
            'user' => PurbalinggaPayPresenter::user($user->fresh()),
        ]);
    }

    public function ssoSync(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $ssoBaseUrl = rtrim(config('services.sso.base_url'), '/');
        $userinfoPath = '/'.ltrim((string) config('services.sso.userinfo_path', '/oauth/userinfo'), '/');

        $response = Http::acceptJson()
            ->withToken($payload['access_token'])
            ->timeout(10)
            ->get($ssoBaseUrl.$userinfoPath);

        if (! $response->ok()) {
            return response()->json([
                'message' => 'Token SSO tidak valid.',
            ], 401);
        }

        $profile = $response->json();
        $email = $profile['email'] ?? null;

        if (! $email) {
            return response()->json([
                'message' => 'Profil SSO tidak lengkap.',
            ], 422);
        }

        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $user->name = $profile['name'] ?? $user->name ?? $email;
        $user->role = $profile['role'] ?? $user->role ?? 'Warga';
        $user->avatar_url = $profile['picture'] ?? $user->avatar_url ?? null;

        if (! $user->exists) {
            $user->balance = 0;
            $user->status = 'active';
            $user->phone = $profile['phone'] ?? null;
            $user->password = Str::random(64);
        }

        $user->save();

        return response()->json([
            'message' => 'Profil SSO disinkronkan.',
            'user' => PurbalinggaPayPresenter::user($user->fresh()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            ApiToken::query()
                ->where('user_id', $user->id)
                ->delete();
        }

        return response()->json([
            'message' => 'Logout semua sesi Pay berhasil.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => PurbalinggaPayPresenter::user($request->user()),
        ]);
    }
}
