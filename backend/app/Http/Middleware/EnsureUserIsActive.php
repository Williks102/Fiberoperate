<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Filet de sécurité : un compte désactivé perd l'accès et ses jetons. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $user->tokens()->delete();

            return response()->json(['message' => 'Ce compte est désactivé.', 'code' => 'ACCOUNT_DISABLED'], 403);
        }

        return $next($request);
    }
}
