<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'api.auth' => \App\Http\Middleware\ApiAuthenticationMiddleware::class,
            'two-factor'=> \App\Http\Middleware\EnsureTwoFactorAuthenticatedMiddleware::class,
            'throttle-login'=> \App\Http\Middleware\ThrottleLoginAttemptsMiddleware::class,
            'org.context' => \App\Http\Middleware\OrganizationContextMiddleware::class,
        ]);

        // Add OrganizationContext to web and api middleware groups
        $middleware->web(append: [
            \App\Http\Middleware\OrganizationContextMiddleware::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\OrganizationContextMiddleware::class,
            \App\Http\Middleware\ContentSecurityPolicyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
