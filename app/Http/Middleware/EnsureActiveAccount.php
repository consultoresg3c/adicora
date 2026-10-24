<?php

namespace App\Http\Middleware;

use App\Services\Access\AccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function __construct(private AccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->access->isActive($request->user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Tu cuenta o empresa no tiene acceso activo.');
        }

        return $next($request);
    }
}
