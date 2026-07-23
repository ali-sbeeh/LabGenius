<?php

// app/Traits/ApiResponseTrait.php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

trait ApiResponseTrait
{
    /**
     * Send success response
     */
    protected function successResponse($data = null, string $message = 'success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'status_code' => $code
        ], $code);
    }

    /**
     * Send error response
     */
    protected function errorResponse(string $message, int $code = 400, $errors = null, string $errorCode = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'status_code' => $code
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        if ($errorCode) {
            $response['error_code'] = $errorCode;
        }

        return response()->json($response, $code);
    }

    /**
     * Send validation error response
     */
    protected function validationErrorResponse($errors, string $message = 'خطأ في بيانات الإدخال'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'error_code' => 'VALIDATION_ERROR',
            'status_code' => 422
        ], 422);
    }

    /**
     * Send not found response
     */
    protected function notFoundResponse(string $resource = 'العنصر'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "{$resource} غير موجود",
            'error_code' => 'NOT_FOUND',
            'status_code' => 404
        ], 404);
    }

    /**
     * Send unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'غير مصرح بالوصول. يرجى تسجيل الدخول'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED',
            'status_code' => 401
        ], 401);
    }

    /**
     * Send forbidden response
     */
    protected function forbiddenResponse(string $message = 'ليس لديك صلاحية للوصول إلى هذا المورد'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'FORBIDDEN',
            'status_code' => 403
        ], 403);
    }

    /**
     * Send paginated response
     */
    protected function paginatedResponse($paginator, $dataKey = 'data'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
            'status_code' => 200
        ]);
    }

    /**
     * Send created response
     */
    protected function createdResponse($data, string $message = 'تم الإنشاء بنجاح'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Send deleted response
     */
    protected function deletedResponse(string $message = 'تم الحذف بنجاح'): JsonResponse
    {
        return $this->successResponse(null, $message, 200);
    }

    /**
     * Handle validation exception and return formatted response
     */
    protected function handleValidationException(ValidationException $e): JsonResponse
    {
        return $this->validationErrorResponse($e->errors());
    }

    /**
 * Send no content response (for DELETE operations)
 */
protected function noContentResponse(string $message = 'تم الحذف بنجاح'): JsonResponse
{
    return response()->json([
        'success' => true,
        'message' => $message,
        'status_code' => 204
    ], 204);
}

/**
 * Send accepted response (for async operations)
 */
protected function acceptedResponse($data = null, string $message = 'تم قبول الطلب وجاري المعالجة'): JsonResponse
{
    return response()->json([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'status_code' => 202
    ], 202);
}

/**
 * Send conflict response (for duplicate entries)
 */
protected function conflictResponse(string $message, $errors = null): JsonResponse
{
    return response()->json([
        'success' => false,
        'message' => $message,
        'errors' => $errors,
        'error_code' => 'CONFLICT',
        'status_code' => 409
    ], 409);
}

/**
 * Send unprocessable entity response
 */
protected function unprocessableResponse(string $message, $errors = null): JsonResponse
{
    return response()->json([
        'success' => false,
        'message' => $message,
        'errors' => $errors,
        'error_code' => 'UNPROCESSABLE_ENTITY',
        'status_code' => 422
    ], 422);
}

/**
 * Send service unavailable response
 */
protected function serviceUnavailableResponse(string $message = 'الخدمة غير متاحة حالياً. يرجى المحاولة لاحقاً'): JsonResponse
{
    return response()->json([
        'success' => false,
        'message' => $message,
        'error_code' => 'SERVICE_UNAVAILABLE',
        'status_code' => 503
    ], 503);
}


}
