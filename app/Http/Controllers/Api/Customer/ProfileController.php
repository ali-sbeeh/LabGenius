<?php

// app/Http/Controllers/Api/Customer/ProfileController.php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * عرض الملف الشخصي للزبون
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // تحميل العلاقات الإضافية
        $user->load(['cart', 'wishlist']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'location' => $user->location,
                'role' => $user->role,
                'created_at' => $user->created_at,
                'image' => $user->image,
                'cart' => $user->cart,
                'wishlist' => $user->wishlist
            ]
        ]);
    }

    /**
     * تحديث الملف الشخصي (يدعم تحديث البيانات الأساسية وتغيير كلمة المرور)
     *
     * يمكن إرسال:
     * 1. بيانات الملف الشخصي فقط (full_name, phone, location, email)
     * 2. كلمة المرور فقط (current_password, new_password, new_password_confirmation)
     * 3. الاثنين معاً
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $errors = [];
        $hasUpdate = false;

        // ============================================
        // الجزء الأول: التحقق من صحة البيانات
        // ============================================

        // قواعد التحقق الأساسية
        $rules = [];
        $messages = [];

        // التحقق من بيانات الملف الشخصي
        if ($request->has('full_name') || $request->has('phone') || $request->has('location') || $request->has('email') || $request->hasFile('image')) {
            $rules['full_name'] = 'sometimes|string|max:255';
            $rules['phone'] = 'sometimes|string|max:20';
            $rules['location'] = 'sometimes|string|max:255';
            $rules['email'] = 'sometimes|string|email|max:255|unique:users,email,' . $user->id;
            $rules['image'] = 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048';
        }

        // التحقق من كلمة المرور
        if ($request->has('current_password') || $request->has('new_password')) {
            $rules['current_password'] = 'required_with:new_password|string';
            $rules['new_password'] = 'required_with:current_password|string|min:8';

            $messages['current_password.required_with'] = 'كلمة المرور الحالية مطلوبة لتغيير كلمة المرور';
            $messages['new_password.required_with'] = 'كلمة المرور الجديدة مطلوبة';
            $messages['new_password.min'] = 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل';
            $messages['new_password.confirmed'] = 'تأكيد كلمة المرور الجديدة غير متطابق';
        }

        // إذا لم يتم إرسال أي بيانات
        if (empty($rules)) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم إرسال أي بيانات للتحديث',
                'error_code' => 'NO_DATA_PROVIDED',
                'status_code' => 422
            ], 422);
        }

        // تنفيذ التحقق
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors(),
                'error_code' => 'VALIDATION_ERROR',
                'status_code' => 422
            ], 422);
        }

        // ============================================
        // الجزء الثاني: تحديث الملف الشخصي
        // ============================================

        $profileData = [];

        if ($request->has('full_name')) {
            $profileData['full_name'] = $request->full_name;
            $hasUpdate = true;
        }

        if ($request->has('phone')) {
            $profileData['phone'] = $request->phone;
            $hasUpdate = true;
        }

        if ($request->has('location')) {
            $profileData['location'] = $request->location;
            $hasUpdate = true;
        }

        if ($request->has('email')) {
            $profileData['email'] = $request->email;
            $hasUpdate = true;
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('users', 'public');
            $profileData['image'] = \Illuminate\Support\Facades\Storage::url($path);
            $hasUpdate = true;
        }

        if (!empty($profileData)) {
            $user->update($profileData);
        }

        // ============================================
        // الجزء الثالث: تغيير كلمة المرور
        // ============================================

        $passwordChanged = false;

        if ($request->has('current_password') && $request->has('new_password')) {
            // التحقق من كلمة المرور الحالية
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'كلمة المرور الحالية غير صحيحة',
                    'error_code' => 'INVALID_CURRENT_PASSWORD',
                    'status_code' => 422
                ], 422);
            }

            // تحديث كلمة المرور
            $user->password = Hash::make($request->new_password);
            $user->save();
            $passwordChanged = true;
            $hasUpdate = true;
        }

        // ============================================
        // الجزء الرابع: إرسال الرد
        // ============================================

        if (!$hasUpdate) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم تحديث أي بيانات',
                'error_code' => 'NO_UPDATE',
                'status_code' => 422
            ], 422);
        }

        // بناء رسالة النجاح
        $message = [];
        if (!empty($profileData)) {
            $message[] = 'تم تحديث بيانات الملف الشخصي بنجاح';
        }
        if ($passwordChanged) {
            $message[] = 'تم تغيير كلمة المرور بنجاح';
        }

        // إعادة تحميل بيانات المستخدم للتأكد من حصولنا على أحدث البيانات
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => implode(' و ', $message),
            'data' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'location' => $user->location,
                'role' => $user->role,
                'image' => $user->image,
                'password_changed' => $passwordChanged,
                'profile_updated' => !empty($profileData)
            ],
            'status_code' => 200
        ]);
    }
}
