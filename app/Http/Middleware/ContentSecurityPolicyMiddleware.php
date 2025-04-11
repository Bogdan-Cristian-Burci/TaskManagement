<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContentSecurityPolicyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Skip non-HTTP responses
        if (!method_exists($response, 'header')) {
            return $response;
        }

        // Content Security Policy
        $cspDirectives = [
            "default-src" => "'self'",
            "script-src" => "'self' 'unsafe-inline' 'unsafe-eval'", // Adjust based on your needs
            "style-src" => "'self' 'unsafe-inline'",
            "img-src" => "'self' data: blob:",
            "font-src" => "'self'",
            "connect-src" => "'self'",
            "media-src" => "'self'",
            "object-src" => "'none'",
            "frame-src" => "'self'",
            "frame-ancestors" => "'self'",
            "form-action" => "'self'",
            "base-uri" => "'self'",
        ];

        // Build the CSP header value
        $cspHeader = implode('; ', array_map(
            fn($key, $value) => "$key $value",
            array_keys($cspDirectives),
            $cspDirectives
        ));

        // Add security headers
        $response->headers->set('Content-Security-Policy', $cspHeader);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $response;
    }
}