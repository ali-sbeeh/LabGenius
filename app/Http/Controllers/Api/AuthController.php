<?php

// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiBaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Cart;
use App\Models\Wishlist;
use App\Traits\ApiResponseTrait;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Laravel\Passport\Token;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;


class AuthController extends ApiBaseController
{
    use ApiResponseTrait;

    /**
     * Register a new customer
     */
    /*
    public function customerRegister(RegisterCustomerRequest $request)
    {
        try {
            $user = User::create([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'location' => $request->location,
                'role' => 'customer',
                'terms_accepted' => true,
                'is_active' => true
            ]);

            // Create cart and wishlist for the customer
            Cart::create(['user_id' => $user->id]);
            Wishlist::create(['user_id' => $user->id]);

            // Create Passport token
            $tokenResult = $user->createToken('Personal Access Token', ['customer']);
            $token = $tokenResult->accessToken;

            // Set token expiration (optional)
            $tokenResult->token->expires_at = Carbon::now()->addDays(30);
            $tokenResult->token->save();

            $data = [
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'location' => $user->location,
                    'role' => $user->role
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => Carbon::now()->addDays(30)->toDateTimeString(),
                'scopes' => ['customer']
            ];

            return $this->successResponse($data, 'تم إنشاء حساب الزبون بنجاح', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء إنشاء الحساب', 500);
        }
    }
        */
    public function register(RegisterRequest $request)
{


    try{
        // تحديد الدور بناءً على البيانات أو طلب العميل
        $role = $request->input('role', 'customer');

        // للبائعين، قد تحتاج لموافقة إدارية
      //  $isActive = ($role === 'customer') ? true : false;
        $isActive = true;
        $user = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'location' => $request->location,
            'role' => $role,
            'terms_accepted' => true,
            'is_active' => $isActive
        ]);

         // ========== أضف هذا السطر ==========
    event(new Registered($user));  // هذا السطر يرسل رابط التحقق تلقائياً
    // =================================

        // Send Welcome Email
        try {
            Mail::to($user->email)->send(new WelcomeEmail($user));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send welcome email: ' . $e->getMessage());
        }

        // فقط الزبائن يحتاجون سلة ومفضلة
        if ($role === 'customer') {
            Cart::create(['user_id' => $user->id]);
            Wishlist::create(['user_id' => $user->id]);
        }

        // إنشاء التوكن
        $tokenResult = $user->createToken('Personal Access Token', [$role]);
        $token = $tokenResult->accessToken;
        $tokenResult->token->expires_at = Carbon::now()->addDays(30);
        $tokenResult->token->save();


        $message = ($role === 'customer')
            ? 'تم إنشاء حساب الزبون بنجاح'
            : 'تم إنشاء حساب البائع بنجاح، يرجى انتظار الموافقة';

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'location' => $user->location,
                'role' => $user->role,
                'image' => $user->image
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::now()->addDays(30)->toDateTimeString(),
            'scopes' => [$role]
        ], $message, 201);

   }catch (\Exception $e) {
        return $this->errorResponse('حدث خطأ أثناء إنشاء الحساب', 500);
    }



}


    /**
     * Register a new seller
     */

    /**
     * Login user and create token
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('البريد الإلكتروني أو كلمة المرور غير صحيحة', 401);
        }

        if (!$user->is_active) {
            return $this->errorResponse('الحساب غير نشط. الرجاء التواصل مع الدعم الفني', 403);
        }

        // Determine scopes based on user role
        $defaultScopes = $request->input('scopes', [$user->role]);
        $scopes = array_intersect($defaultScopes, [$user->role]);

        if (empty($scopes)) {
            $scopes = [$user->role];
        }

        // Revoke old tokens (optional)
        // $user->tokens()->delete();

        // Create new token
        $tokenResult = $user->createToken($request->device_name ?? 'Web Client', $scopes);
        $token = $tokenResult->accessToken;

        // Set token expiration (30 days)
        $tokenResult->token->expires_at = Carbon::now()->addDays(30);
        $tokenResult->token->save();

        $data = [
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'location' => $user->location,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'image' => $user->image
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::now()->addDays(30)->toDateTimeString(),
            'scopes' => $scopes
        ];

        return $this->successResponse($data, 'تم تسجيل الدخول بنجاح');
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        try {
            // Revoke the current user's token
            $request->user()->token()->revoke();

            // Optionally delete the token completely
            // $request->user()->token()->delete();

            return $this->successResponse(null, 'تم تسجيل الخروج بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء تسجيل الخروج', 500);
        }
    }

    /**
     * Logout from all devices (revoke all tokens)
     */
    public function logoutAllDevices(Request $request)
    {
        try {
            // Revoke all tokens for the user
            $request->user()->tokens()->each(function ($token) {
                $token->revoke();
            });

            return $this->successResponse(null, 'تم تسجيل الخروج من جميع الأجهزة بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء تسجيل الخروج من جميع الأجهزة', 500);
        }
    }

    /**
     * Get the authenticated user details
     */
    public function me(Request $request)
    {
        $user = $request->user();

        $data = [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'location' => $user->location,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
            'image' => $user->image,
            'token_scopes' => $request->user()->token()->scopes
        ];

        return $this->successResponse($data, 'تم جلب بيانات المستخدم بنجاح');
    }

    /**
     * Refresh token
     */
    public function refreshToken(Request $request)
    {
        try {
            $oldToken = $request->user()->token();

            // Get the old token scopes
            $scopes = $oldToken->scopes;

            // Revoke old token
            $oldToken->revoke();

            // Create new token with same scopes
            $newTokenResult = $request->user()->createToken('Refreshed Token', $scopes);
            $newToken = $newTokenResult->accessToken;
            $newTokenResult->token->expires_at = Carbon::now()->addDays(30);
            $newTokenResult->token->save();

            return $this->successResponse([
                'access_token' => $newToken,
                'token_type' => 'Bearer',
                'expires_at' => Carbon::now()->addDays(30)->toDateTimeString()
            ], 'تم تجديد التوكن بنجاح');

        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء تجديد التوكن', 500);
        }
    }

    /**
     * Get all tokens for the authenticated user
     */
    public function getTokens(Request $request)
    {
        $tokens = $request->user()->tokens()->where('revoked', false)->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'scopes' => $token->scopes,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
                'last_used_at' => $token->last_used_at
            ];
        });

        return $this->successResponse($tokens, 'تم جلب التوكنات بنجاح');
    }

    /**
     * Revoke a specific token by ID
     */
    public function revokeToken($tokenId, Request $request)
    {
        try {
            $token = $request->user()->tokens()->where('id', $tokenId)->first();

            if (!$token) {
                return $this->errorResponse('التوكن غير موجود', 404);
            }

            $token->revoke();

            return $this->successResponse(null, 'تم إبطال التوكن بنجاح');

        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء إبطال التوكن', 500);
        }
    }

    /**
     * Send password reset link to user's email
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح',
            'email.exists' => 'لا يوجد حساب مرتبط بهذا البريد الإلكتروني'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
        // إرسال رابط إعادة التعيين عبر الإيميل
        $status = Password::sendResetLink(
            $request->only('email')
        );

            if ($status === Password::RESET_LINK_SENT) {
                // تسجيل العملية في السجلات
                \Illuminate\Support\Facades\Log::info('Password reset link sent', [
                    'email' => $request->email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                return $this->successResponse(
                    null,
                    'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني. الرابط صالح لمدة 60 دقيقة'
                );
            }

            if ($status === Password::RESET_THROTTLED) {
                return $this->errorResponse(
                    __($status),
                    429,
                    null,
                    'RESET_LINK_THROTTLED'
                );
            }

            // إذا فشل الإرسال
            return $this->errorResponse(
                'حدث خطأ أثناء إرسال رابط إعادة التعيين. يرجى المحاولة مرة أخرى لاحقاً',
                500,
                null,
                'RESET_LINK_SEND_FAILED'
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Password reset error', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'حدث خطأ في الخادم. يرجى المحاولة مرة أخرى لاحقاً',
                500
            );
        }
    }
     /**
     * Reset user password using token
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8'
        ], [
            'token.required' => 'رمز إعادة التعيين مطلوب',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.exists' => 'البريد الإلكتروني غير موجود',
            'password.required' => 'كلمة المرور الجديدة مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }


        try {
            // محاولة إعادة تعيين كلمة المرور
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->setRememberToken(Str::random(60));

                    $user->save();

                    // إبطال جميع التوكنات القديمة (اختياري)
                    $user->tokens()->delete();

                    event(new PasswordReset($user));

                    // إرسال إشعار نجاح إعادة التعيين (اختياري)
                    // $user->notify(new PasswordResetSuccessNotification());
                }
            );



            if ($status === Password::PASSWORD_RESET) {
                // تسجيل العملية
                \Illuminate\Support\Facades\Log::info('Password reset successful', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);

                return $this->successResponse(
                    null,
                    'تم إعادة تعيين كلمة المرور بنجاح. يرجى تسجيل الدخول باستخدام كلمة المرور الجديدة'
                );
            }

            // تحديد الرسالة المناسبة حسب نوع الخطأ
            $errorMessage = match($status) {
                Password::INVALID_TOKEN => 'رمز إعادة التعيين غير صالح أو منتهي الصلاحية',
                Password::INVALID_USER => 'المستخدم غير موجود',
                default => 'حدث خطأ أثناء إعادة تعيين كلمة المرور. يرجى المحاولة مرة أخرى'
            };

            return $this->errorResponse($errorMessage, 400, null, 'RESET_PASSWORD_FAILED');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Password reset error', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'حدث خطأ في الخادم. يرجى المحاولة مرة أخرى لاحقاً',
                500
            );
        }
    }

     /**
     * Alternative: Reset password using token (GET method for frontend)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showResetForm(Request $request)
    {
        $token = $request->query('token');
        $email = $request->query('email');

        if (!$token || !$email) {
            return $this->errorResponse('معلمات غير كاملة', 400);
        }

        // التحقق من صحة التوكن
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$resetRecord || !Hash::check($token, $resetRecord->token)) {
            return $this->errorResponse('رمز إعادة التعيين غير صالح أو منتهي الصلاحية', 400);
        }

        // التحقق من صلاحية التوكن (60 دقيقة)
        $createdAt = Carbon::parse($resetRecord->created_at);
        if ($createdAt->diffInMinutes(now()) > config('auth.passwords.users.expire', 60)) {
            return $this->errorResponse('انتهت صلاحية رابط إعادة التعيين. يرجى طلب رابط جديد', 400);
        }

        return $this->successResponse([
            'token' => $token,
            'email' => $email,
            'valid' => true
        ], 'الرابط صالح');
    }

     /**
     * Resend password reset link
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // حذف التوكن القديم
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // إرسال رابط جديد
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return $this->successResponse(null, 'تم إعادة إرسال رابط إعادة تعيين كلمة المرور');
        }

        return $this->errorResponse('حدث خطأ. يرجى المحاولة مرة أخرى', 500);
    }



    /**
     * Change password (authenticated user)
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('كلمة المرور الحالية غير صحيحة', 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        // Optional: Revoke all tokens except current one after password change
        $user->tokens()->where('id', '!=', $user->token()->id)->delete();

        return $this->successResponse(null, 'تم تغيير كلمة المرور بنجاح');
    }

    /**
     * Verify email (optional)
     */
    public function verifyEmail($id, $hash, Request $request)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->errorResponse('المستخدم غير موجود', 404);
        }

        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return $this->errorResponse('رابط التحقق غير صالح', 400);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->successResponse(null, 'البريد الإلكتروني مؤكد بالفعل');
        }

        $user->markEmailAsVerified();

        return $this->successResponse(null, 'تم تأكيد البريد الإلكتروني بنجاح');


    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->errorResponse('البريد الإلكتروني مؤكد بالفعل', 422);
        }

        $user->sendEmailVerificationNotification();

        return $this->successResponse(null, 'تم إعادة إرسال رابط التأكيد إلى بريدك الإلكتروني');
    }
}
