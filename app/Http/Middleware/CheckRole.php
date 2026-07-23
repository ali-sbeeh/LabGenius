<?php

// app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return $this->unauthorizedResponse('غير مصرح بالوصول. يرجى تسجيل الدخول');
        }

        // Check if user account is active
        if (!$user->is_active) {
            return $this->errorResponse(
                'الحساب غير نشط. يرجى التواصل مع الدعم الفني',
                403,
                null,
                'ACCOUNT_INACTIVE'
            );
        }

        // Check if user has required role
        $userRoles = [$user->role];

        // Allow admin to access all roles
        if (in_array('admin', $roles) && $user->role === 'admin') {
            return $next($request);
        }

        // Check for specific role
        $hasRequiredRole = !empty(array_intersect($userRoles, $roles));

        if (!$hasRequiredRole) {
            return $this->forbiddenResponse(
                'ليس لديك صلاحية للوصول إلى هذا المورد. الدور المطلوب: ' . implode(' أو ', $roles)
            );
        }

        // Check token scopes (if using Passport with scopes)
        if ($request->user()->token()) {
            $tokenScopes = $request->user()->token()->scopes;

            // If token has specific scopes that don't match user role
            if (!empty($tokenScopes) && !in_array($user->role, $tokenScopes)) {
                return $this->forbiddenResponse(
                    'لا تمتلك الصلاحيات المطلوبة لهذا الإجراء',
                    null,
                    'INVALID_SCOPE'
                );
            }
        }

        return $next($request);
    }
}
