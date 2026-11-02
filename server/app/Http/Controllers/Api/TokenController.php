<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/** Log the phone app in with e-mail + password (+ 2FA code when enabled). */
class TokenController extends Controller
{
    public function store(Request $request, TwoFactorAuthenticationProvider $twoFactor): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Nesprávny e-mail alebo heslo.']);
        }

        if ($user->two_factor_confirmed_at !== null) {
            if (blank($data['code'] ?? null)) {
                return response()->json(['message' => 'Zadaj kód z autentifikačnej aplikácie.', 'two_factor' => true], 422);
            }

            if (! $twoFactor->verify(decrypt($user->two_factor_secret), $data['code'])) {
                throw ValidationException::withMessages(['code' => 'Nesprávny overovací kód.']);
            }
        }

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ], 201);
    }

    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
