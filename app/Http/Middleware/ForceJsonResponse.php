<?php

// app/Http/Middleware/ForceJsonResponse.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force API requests to accept JSON
        if ($request->is('api/*') || $request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        $response = $next($request);

        // التأكد من أن جميع الاستجابات بصيغة JSON
        if (!$response->headers->get('Content-Type') ||
            str_contains($response->headers->get('Content-Type'), 'text/html')) {
            if ($response->getStatusCode() >= 300 && $response->getStatusCode() < 600) {
                // إذا كان هناك خطأ ولم نتعامل معه بعد
                if (empty($response->getContent()) ||
                    !json_decode($response->getContent(), true)) {
                    $response->setContent(json_encode([
                        'success' => false,
                        'message' => $response->getContent() ?: 'حدث خطأ غير متوقع',
                        'error_code' => 'UNHANDLED_ERROR',
                        'status_code' => $response->getStatusCode()
                    ]));
                }
            }
        }

        return $response;
    }
}
