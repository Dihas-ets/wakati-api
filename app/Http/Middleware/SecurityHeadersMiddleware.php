<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Ajoute des en-têtes HTTP de sécurité stricts pour protéger l'API et l'application.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Protection anti-clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Empêche le reniflage de type MIME (MIME-sniffing)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Politique de référencement stricte
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions matérielles limitées (Caméra et Géolocalisation pour le pointage QR/PWA)
        $response->headers->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');

        // HSTS (HTTP Strict Transport Security)
        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Content Security Policy compatible API et PWA
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: blob: https:; " .
               "font-src 'self' data: https:; " .
               "connect-src 'self' https: http:; " .
               "frame-ancestors 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self';";
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
