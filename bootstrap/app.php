<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\VerifyOfflineSyncSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'offline-sync' => VerifyOfflineSyncSecret::class,
        ]);

        // En-têtes HTTP de sécurité (CSP, HSTS, X-Frame-Options, etc.)
        $middleware->append(SecurityHeadersMiddleware::class);

        // Trust reverse proxies (Render, Cloudflare, Nginx...) pour IP et HTTPS
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // En production, masquer les détails techniques internes sur erreur serveur
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (($request->is('api/*') || $request->expectsJson()) && ! config('app.debug')) {
                $statusCode = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
                $message = match ($statusCode) {
                    401 => 'Non authentifié. Veuillez vous connecter.',
                    403 => 'Action non autorisée.',
                    404 => 'Ressource introuvable.',
                    405 => 'Méthode HTTP non autorisée.',
                    422 => $e->getMessage() ?: 'Données fournies non valides.',
                    429 => 'Trop de requêtes. Veuillez patienter avant de réessayer.',
                    default => 'Une erreur interne est survenue. Veuillez contacter le support si le problème persiste.',
                };

                return response()->json([
                    'message' => $message,
                ], $statusCode);
            }

            return null;
        });
    })->create();