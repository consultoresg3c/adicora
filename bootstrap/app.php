<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Support\AsyncResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['active' => EnsureActiveAccount::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if (! $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();
            $message = match ($status) {
                401 => 'Inicia sesión para continuar.',
                403 => 'No tienes permiso para realizar esta acción.',
                404 => 'No encontramos el registro solicitado.',
                419 => 'Tu sesión expiró. Recarga la página e intenta de nuevo.',
                422 => 'Revisa los campos indicados.',
                429 => 'Demasiados intentos. Espera un minuto antes de continuar.',
                default => 'No pudimos completar la operación. Intenta nuevamente.',
            };

            return AsyncResponse::error($message, $status, $exception instanceof ValidationException ? $exception->errors() : []);
        });
    })->create();
