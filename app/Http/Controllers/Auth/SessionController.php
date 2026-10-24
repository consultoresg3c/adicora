<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthenticationService;
use App\Support\AsyncResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function store(LoginRequest $request, AuthenticationService $service): JsonResponse|RedirectResponse
    {
        $service->login($request->validated(), (string) $request->ip());
        $request->session()->regenerate();
        $redirect = route('dashboard');

        return $request->expectsJson()
            ? AsyncResponse::success('Sesión iniciada.', ['redirect' => $redirect])
            : redirect()->to($redirect);
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? AsyncResponse::success('Sesión cerrada.', ['redirect' => route('login')])
            : redirect()->route('login');
    }
}
