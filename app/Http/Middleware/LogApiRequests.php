<?php

// app/Http/Middleware/LogApiRequests.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // تسجيل بداية الطلب
        $startTime = microtime(true);

        // معالجة الطلب
        $response = $next($request);

        // حساب وقت الاستجابة
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        // تسجيل فقط الطلبات التي استغرقت وقتاً طويلاً أو التي أعطت خطأ
        if ($response->getStatusCode() >= 400 || $responseTime > 1000) {
            Log::channel('daily')->info('API Request', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'status_code' => $response->getStatusCode(),
                'response_time_ms' => $responseTime,
                'user_agent' => $request->userAgent(),
            ]);
        }

        // إضافة وقت الاستجابة في الـ Header (اختياري)
        $response->headers->set('X-Response-Time-Ms', $responseTime);

        return $response;
    }
}
