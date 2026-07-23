<?php

// app/Http/Middleware/CheckClientCredentials.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Exceptions\MissingScopeException;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

class CheckClientCredentials
{
    /**
     * The Resource Server instance.
     *
     * @var \League\OAuth2\Server\ResourceServer
     */
    protected $server;

    /**
     * The PSR-7 factory implementation.
     *
     * @var \Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory
     */
    protected $psrHttpFactory;

    /**
     * Create a new middleware instance.
     */
    public function __construct(ResourceServer $server, PsrHttpFactory $psrHttpFactory)
    {
        $this->server = $server;
        $this->psrHttpFactory = $psrHttpFactory;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$scopes): Response
    {
        $psrRequest = $this->psrHttpFactory->createRequest($request);

        try {
            $psrRequest = $this->server->validateAuthenticatedRequest($psrRequest);
        } catch (OAuthServerException $e) {
            throw new \Laravel\Passport\Exceptions\OAuthServerException($e);
        }

        // Check scopes if provided
        if (!empty($scopes)) {
            $tokenScopes = $psrRequest->getAttribute('oauth_scopes', []);

            foreach ($scopes as $scope) {
                if (!in_array($scope, $tokenScopes)) {
                    throw new MissingScopeException($scopes);
                }
            }
        }

        return $next($request);
    }
}
