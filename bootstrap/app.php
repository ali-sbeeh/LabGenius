<?php

// bootstrap/app.php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Exceptions\ApiExceptionHandler;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Register middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
              'scope' => \App\Http\Middleware\CheckPassportAnyScope::class,
             'scopes' => \App\Http\Middleware\CheckPassportScopes::class,
             'client' => \App\Http\Middleware\CheckClientCredentials::class,
        ]);

        // Add middleware to API group
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\LogApiRequests::class,
        ]);

        // Configure rate limiting
        $middleware->throttleApi();

    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Force JSON responses for API requests
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Custom exception rendering
        $exceptions->render(function (Throwable $e, Request $request) {
            return ApiExceptionHandler::handle($e, $request);
        });

        // Report exceptions (logging)
        $exceptions->report(function (Throwable $e) {
            // Custom reporting logic here
            if (app()->environment('production')) {
                // Log to Slack, Sentry, etc.
            }
        });

    })
    ->create();
