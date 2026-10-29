<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Derrière le proxy de Render : sinon les URL générées sont en http.
        $middleware->trustProxies(at: '*');
        // API seule : pas de page de connexion côté serveur, un visiteur non connecté reçoit toujours un 401 JSON.
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias(['active' => EnsureUserIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Les index uniques partiels (codes, e-mails) signalent un doublon : message clair en 422.
        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Cette valeur existe déjà.', 'code' => 'DUPLICATE'], 422);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
