<?php

// app/Exceptions/ApiExceptionHandler.php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Laravel\Passport\Exceptions\MissingScopeException;
use Laravel\Passport\Exceptions\OAuthServerException;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueOAuthException;
use Throwable;
use Illuminate\Support\Facades\Log;

class ApiExceptionHandler
{
    /**
     * Handle API exceptions and return formatted JSON response
     */
    public static function handle(Throwable $e, Request $request): ?JsonResponse
    {
        // فقط للـ API
        if (!$request->is('api/*') && !$request->expectsJson()) {
            return null;
        }

        // Validation Exception
        if ($e instanceof ValidationException) {
            return self::validationError($e);
        }

        // Model Not Found Exception
        if ($e instanceof ModelNotFoundException) {
            return self::modelNotFoundError($e);
        }

        // Route Not Found Exception
        if ($e instanceof NotFoundHttpException) {
            return self::routeNotFoundError();
        }

        // Method Not Allowed Exception
        if ($e instanceof MethodNotAllowedHttpException) {
            return self::methodNotAllowedError($e);
        }

        // Authentication Exception (Passport)
        if ($e instanceof AuthenticationException || $e instanceof UnauthorizedHttpException) {
            return self::unauthenticatedError($e);
        }

        // Authorization Exception
        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return self::forbiddenError($e);
        }

        // Passport - Missing Scope Exception (تم التصحيح)
        if ($e instanceof MissingScopeException) {
            return self::invalidScopeError($e);
        }

        // Passport - OAuth Server Exception
        if ($e instanceof OAuthServerException || $e instanceof LeagueOAuthException) {
            return self::oauthError($e);
        }

        // Too Many Requests Exception
        if ($e instanceof TooManyRequestsHttpException || $e instanceof ThrottleRequestsException) {
            return self::tooManyRequestsError($e);
        }

        // File Too Large Exception
        if ($e instanceof PostTooLargeException) {
            return self::fileTooLargeError();
        }

        // Bad Request Exception
        if ($e instanceof BadRequestHttpException) {
            return self::badRequestError($e);
        }

        // Database Query Exception
        if ($e instanceof QueryException) {
            return self::queryError($e);
        }

        // Token Mismatch Exception (CSRF)
        if ($e instanceof \Illuminate\Session\TokenMismatchException) {
            return self::tokenMismatchError();
        }

        // Decrypt Exception (CSRF related)
        if ($e instanceof \Illuminate\Contracts\Encryption\DecryptException) {
            return self::csrfTokenError();
        }

        // Token Expired (handled by message) - لـ Passport
        if (str_contains($e->getMessage(), 'token has expired') ||
            str_contains($e->getMessage(), 'The token has expired')) {
            return self::tokenExpiredError();
        }

        // Invalid Token - لـ Passport
        if (str_contains($e->getMessage(), 'token is invalid') ||
            str_contains($e->getMessage(), 'The token is invalid')) {
            return self::invalidTokenError();
        }

        // Invalid Refresh Token - لـ Passport
        if (str_contains($e->getMessage(), 'refresh token') ||
            str_contains($e->getMessage(), 'Refresh token is invalid')) {
            return self::invalidRefreshTokenError();
        }

        // Any other exception
        return self::genericError($e);
    }

    // ============================================
    // Private helper methods for each error type
    // ============================================

    private static function validationError(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'خطأ في بيانات الإدخال',
            'errors' => $e->errors(),
            'error_code' => 'VALIDATION_ERROR',
            'status_code' => 422
        ], 422);
    }

    private static function modelNotFoundError(ModelNotFoundException $e): JsonResponse
    {
        $model = strtolower(class_basename($e->getModel()));
        $modelNames = [
            'user' => 'المستخدم',
            'product' => 'المنتج',
            'category' => 'الفئة',
            'order' => 'الطلب',
            'cart' => 'السلة',
            'cartitem' => 'عنصر السلة',
            'wishlist' => 'القائمة المفضلة',
            'wishlistitem' => 'عنصر القائمة المفضلة',
            'review' => 'المراجعة',
            'discount' => 'الخصم',
            'shippingcompany' => 'شركة الشحن',
            'companybranch' => 'فرع الشركة',
            'province' => 'المحافظة',
            'payment' => 'الدفع',
            'notification' => 'الإشعار'
        ];
        $modelName = $modelNames[$model] ?? $model;

        return response()->json([
            'success' => false,
            'message' => "{$modelName} غير موجود",
            'error_code' => 'MODEL_NOT_FOUND',
            'status_code' => 404
        ], 404);
    }

    private static function routeNotFoundError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'الرابط المطلوب غير موجود',
            'error_code' => 'ROUTE_NOT_FOUND',
            'status_code' => 404
        ], 404);
    }

    private static function methodNotAllowedError(MethodNotAllowedHttpException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'طريقة الطلب غير مسموحة',
            'error_code' => 'METHOD_NOT_ALLOWED',
            'allowed_methods' => $e->getHeaders()['Allow'] ?? null,
            'status_code' => 405
        ], 405);
    }

    private static function unauthenticatedError($e): JsonResponse
    {
        $message = $e instanceof UnauthorizedHttpException
            ? 'التوكن غير صالح أو منتهي الصلاحية'
            : 'غير مصرح بالوصول. يرجى تسجيل الدخول';

        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHENTICATED',
            'status_code' => 401
        ], 401);
    }

    private static function forbiddenError($e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage() ?: 'ليس لديك صلاحية للوصول إلى هذا المورد',
            'error_code' => 'FORBIDDEN',
            'status_code' => 403
        ], 403);
    }

    /**
     * Handle MissingScopeException - تم التصحيح
     * في Laravel Passport، MissingScopeException يحوي protected property名叫 $scopes
     * لا يمكن الوصول إليها مباشرة، لذا نعطي رسالة عامة
     */
    private static function invalidScopeError(MissingScopeException $e): JsonResponse
    {
        // MissingScopeException ليس لديه public method للوصول إلى الـ scopes
        // لذلك نعطي رسالة عامة
        return response()->json([
            'success' => false,
            'message' => 'لا تمتلك الصلاحيات المطلوبة للوصول إلى هذا المورد',
            'error_code' => 'INVALID_SCOPE',
            'status_code' => 403
        ], 403);
    }

    private static function oauthError($e): JsonResponse
    {
        $statusCode = method_exists($e, 'getHttpStatusCode')
            ? $e->getHttpStatusCode()
            : 400;

        $errorCode = method_exists($e, 'getCode')
            ? $e->getCode()
            : 0;

        $message = match($errorCode) {
            2 => 'رمز التفويض غير صالح',
            3 => 'العميل غير مصرح له',
            4 => 'نوع التفويض غير مدعوم',
            5 => 'طريقة التفويض غير مدعومة',
            6 => 'البريد الإلكتروني أو كلمة المرور غير صحيحة',
            8 => 'تم رفض طلب التفويض',
            9 => 'العميل غير مصرح له بهذا المجال',
            default => 'حدث خطأ في عملية المصادقة'
        };

        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'OAUTH_ERROR',
            'status_code' => $statusCode
        ], $statusCode);
    }

    private static function invalidRefreshTokenError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'رمز التحديث غير صالح أو منتهي الصلاحية. يرجى تسجيل الدخول مرة أخرى',
            'error_code' => 'INVALID_REFRESH_TOKEN',
            'status_code' => 401
        ], 401);
    }

    private static function tooManyRequestsError($e): JsonResponse
    {
        $retryAfter = 60;

        if (method_exists($e, 'getHeaders')) {
            $headers = $e->getHeaders();
            $retryAfter = $headers['Retry-After'] ?? 60;
        }

        return response()->json([
            'success' => false,
            'message' => "لقد تجاوزت الحد الأقصى للطلبات. يرجى المحاولة بعد {$retryAfter} ثانية",
            'error_code' => 'TOO_MANY_REQUESTS',
            'retry_after' => (int) $retryAfter,
            'status_code' => 429
        ], 429);
    }

    private static function fileTooLargeError(): JsonResponse
    {
        $maxSize = ini_get('upload_max_filesize');
        return response()->json([
            'success' => false,
            'message' => "حجم الملف أكبر من الحد المسموح به. الحد الأقصى: {$maxSize}",
            'error_code' => 'FILE_TOO_LARGE',
            'max_size' => $maxSize,
            'status_code' => 413
        ], 413);
    }

    private static function badRequestError(BadRequestHttpException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage() ?: 'طلب غير صحيح',
            'error_code' => 'BAD_REQUEST',
            'status_code' => 400
        ], 400);
    }

    private static function queryError(QueryException $e): JsonResponse
    {
        $errorCode = $e->errorInfo[1] ?? 0;

        // Duplicate entry
        if ($errorCode == 1062) {
            preg_match("/Duplicate entry '(.+)' for key/", $e->getMessage(), $matches);
            $duplicateValue = $matches[1] ?? 'unknown';

            return response()->json([
                'success' => false,
                'message' => "البيان '{$duplicateValue}' موجود مسبقاً في النظام",
                'error_code' => 'DUPLICATE_ENTRY',
                'status_code' => 409
            ], 409);
        }

        // Foreign key constraint (cannot delete)
        if ($errorCode == 1451) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف هذا العنصر لأنه مرتبط بعناصر أخرى في النظام',
                'error_code' => 'FOREIGN_KEY_CONSTRAINT',
                'status_code' => 422
            ], 422);
        }

        // Foreign key constraint (missing reference)
        if ($errorCode == 1452) {
            return response()->json([
                'success' => false,
                'message' => 'العنصر المرجعي غير موجود في النظام',
                'error_code' => 'FOREIGN_KEY_CONSTRAINT_MISSING',
                'status_code' => 422
            ], 422);
        }

        // Database connection error
        if (str_contains($e->getMessage(), 'SQLSTATE[HY000] [2002]') ||
            str_contains($e->getMessage(), 'SQLSTATE[HY000] [1045]')) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً',
                'error_code' => 'DATABASE_CONNECTION_ERROR',
                'status_code' => 503
            ], 503);
        }

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ في قاعدة البيانات. يرجى المحاولة لاحقاً',
            'error_code' => 'DATABASE_ERROR',
            'status_code' => 500
        ], 500);
    }

    private static function tokenExpiredError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'انتهت صلاحية التوكن. يرجى تسجيل الدخول مرة أخرى',
            'error_code' => 'TOKEN_EXPIRED',
            'status_code' => 401
        ], 401);
    }

    private static function invalidTokenError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'التوكن غير صالح',
            'error_code' => 'INVALID_TOKEN',
            'status_code' => 401
        ], 401);
    }

    private static function tokenMismatchError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'رمز التحقق من الصلاحية غير صالح. يرجى تحديث الصفحة والمحاولة مرة أخرى',
            'error_code' => 'TOKEN_MISMATCH',
            'status_code' => 419
        ], 419);
    }

    private static function csrfTokenError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'رمز الحماية غير صالح. يرجى تحديث الصفحة والمحاولة مرة أخرى',
            'error_code' => 'CSRF_TOKEN_MISMATCH',
            'status_code' => 419
        ], 419);
    }

    private static function genericError(Throwable $e): JsonResponse
    {
        $isDebug = env('APP_DEBUG', false);

        // Log the error
        Log::error('Unhandled API Exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'url' => request()->fullUrl(),
        ]);

        if ($isDebug) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'INTERNAL_SERVER_ERROR',
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->map(fn($trace) => [
                    'file' => $trace['file'] ?? null,
                    'line' => $trace['line'] ?? null,
                    'function' => $trace['function'] ?? null,
                    'class' => $trace['class'] ?? null,
                ])->take(5),
                'status_code' => 500
            ], 500);
        }

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ داخلي في الخادم. يرجى المحاولة لاحقاً',
            'error_code' => 'INTERNAL_SERVER_ERROR',
            'status_code' => 500
        ], 500);
    }
}
