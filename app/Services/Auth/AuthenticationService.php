<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function __construct(private AccessService $access) {}

    public function login(array $credentials, string $ip): void
    {
        $key = 'login:'.hash('sha256', $credentials['email'].'|'.$ip);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, 'Demasiados intentos. Intenta nuevamente en un minuto.');
        }

        $user = User::query()->with('status', 'company.status')->where('email', $credentials['email'])->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $this->access->isActive($user)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'No pudimos iniciar sesión con esos datos.']);
        }

        Auth::login($user, $credentials['remember'] ?? false);
        RateLimiter::clear($key);
    }
}
