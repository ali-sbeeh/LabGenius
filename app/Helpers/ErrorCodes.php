<?php

// app/Helpers/ErrorCodes.php

namespace App\Helpers;

class ErrorCodes
{
    // Authentication errors (1000-1099)
    const UNAUTHENTICATED = 'UNAUTHENTICATED';
    const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    const TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    const INVALID_TOKEN = 'INVALID_TOKEN';
    const ACCOUNT_INACTIVE = 'ACCOUNT_INACTIVE';
    const ACCOUNT_BLOCKED = 'ACCOUNT_BLOCKED';

    // Authorization errors (1100-1199)
    const FORBIDDEN = 'FORBIDDEN';
    const INSUFFICIENT_PERMISSIONS = 'INSUFFICIENT_PERMISSIONS';
    const INVALID_SCOPE = 'INVALID_SCOPE';

    // Validation errors (1200-1299)
    const VALIDATION_ERROR = 'VALIDATION_ERROR';
    const INVALID_INPUT = 'INVALID_INPUT';

    // Resource errors (1300-1399)
    const NOT_FOUND = 'NOT_FOUND';
    const MODEL_NOT_FOUND = 'MODEL_NOT_FOUND';
    const ROUTE_NOT_FOUND = 'ROUTE_NOT_FOUND';

    // Request errors (1400-1499)
    const METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    const TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';
    const BAD_REQUEST = 'BAD_REQUEST';
    const FILE_TOO_LARGE = 'FILE_TOO_LARGE';

    // Database errors (1500-1599)
    const DATABASE_ERROR = 'DATABASE_ERROR';
    const DATABASE_CONNECTION_ERROR = 'DATABASE_CONNECTION_ERROR';
    const DUPLICATE_ENTRY = 'DUPLICATE_ENTRY';
    const FOREIGN_KEY_CONSTRAINT = 'FOREIGN_KEY_CONSTRAINT';
    const QUERY_ERROR = 'QUERY_ERROR';

    // Business logic errors (1600-1699)
    const INSUFFICIENT_STOCK = 'INSUFFICIENT_STOCK';
    const CART_EMPTY = 'CART_EMPTY';
    const INVALID_ORDER_STATUS = 'INVALID_ORDER_STATUS';
    const PAYMENT_FAILED = 'PAYMENT_FAILED';

    // Server errors (5000-5099)
    const INTERNAL_SERVER_ERROR = 'INTERNAL_SERVER_ERROR';
    const STORAGE_ERROR = 'STORAGE_ERROR';
    const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';

    // OAuth errors (6000-6099)
    const OAUTH_ERROR = 'OAUTH_ERROR';
    const INVALID_CLIENT = 'INVALID_CLIENT';
    const INVALID_GRANT = 'INVALID_GRANT';
    const UNSUPPORTED_GRANT_TYPE = 'UNSUPPORTED_GRANT_TYPE';
    const INVALID_SCOPE_OAUTH = 'INVALID_SCOPE_OAUTH';

    /**
     * Get user-friendly message for error code
     */
    public static function getMessage(string $errorCode): string
    {
        $messages = [
            self::UNAUTHENTICATED => 'غير مصرح بالوصول. يرجى تسجيل الدخول',
            self::INVALID_CREDENTIALS => 'البريد الإلكتروني أو كلمة المرور غير صحيحة',
            self::TOKEN_EXPIRED => 'انتهت صلاحية التوكن. يرجى تسجيل الدخول مرة أخرى',
            self::INVALID_TOKEN => 'التوكن غير صالح',
            self::ACCOUNT_INACTIVE => 'الحساب غير نشط. يرجى التواصل مع الدعم الفني',
            self::ACCOUNT_BLOCKED => 'الحساب محظور. يرجى التواصل مع الدعم الفني',
            self::FORBIDDEN => 'ليس لديك صلاحية للوصول إلى هذا المورد',
            self::INSUFFICIENT_PERMISSIONS => 'لا تمتلك الصلاحيات المطلوبة',
            self::INVALID_SCOPE => 'لا تمتلك الصلاحيات المطلوبة للوصول إلى هذا المورد',
            self::VALIDATION_ERROR => 'خطأ في بيانات الإدخال',
            self::NOT_FOUND => 'العنصر المطلوب غير موجود',
            self::METHOD_NOT_ALLOWED => 'طريقة الطلب غير مسموحة',
            self::TOO_MANY_REQUESTS => 'لقد تجاوزت الحد الأقصى للطلبات. يرجى المحاولة لاحقاً',
            self::INTERNAL_SERVER_ERROR => 'حدث خطأ داخلي في الخادم. يرجى المحاولة لاحقاً',
            self::DATABASE_ERROR => 'حدث خطأ في قاعدة البيانات',
            self::DUPLICATE_ENTRY => 'هذه البيانات موجودة مسبقاً في النظام',
            self::FOREIGN_KEY_CONSTRAINT => 'لا يمكن حذف هذا العنصر لأنه مرتبط بعناصر أخرى',
            self::INSUFFICIENT_STOCK => 'الكمية المطلوبة غير متوفرة في المخزون',
            self::CART_EMPTY => 'السلة فارغة. يرجى إضافة منتجات إلى السلة أولاً',
            self::INVALID_ORDER_STATUS => 'حالة الطلب غير صالحة للإجراء المطلوب',
            self::PAYMENT_FAILED => 'فشلت عملية الدفع. يرجى المحاولة مرة أخرى',
        ];

        return $messages[$errorCode] ?? 'حدث خطأ غير متوقع';
    }
}
