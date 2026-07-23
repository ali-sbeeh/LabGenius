<?php

// app/Http/Middleware/CheckPassportScopes.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Exceptions\MissingScopeException;
use Symfony\Component\HttpFoundation\Response;

class CheckPassportScopes
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$scopes): Response
    {
        if (!$request->user() || !$request->user()->token()) {
            throw new MissingScopeException($scopes);
        }

        foreach ($scopes as $scope) {
            if (!$request->user()->token()->can($scope)) {
                throw new MissingScopeException([$scope]);
            }
        }

        return $next($request);
    }
}
