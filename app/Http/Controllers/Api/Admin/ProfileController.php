<?php

// app/Http/Controllers/Api/Admin/ProfileController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * عرض الملف الشخصي للأدمن
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // إحصائيات النظام للإدمن
        $stats = [
            'total_users' => User::count(),
            'total_customers' => User::where('role', 'customer')->count(),
            'total_sellers' => User::where('role', 'seller')->count(),
            'total_admins' => User::where('role', 'admin')->count(),
            'total_products' => \App\Models\Product::count(),
            'total_orders' => \App\Models\Order::count(),
            'total_revenue' => \App\Models\Order::sum('total_price')
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'created_at' => $user->created_at,
                'image' => $user->image,
                'system_stats' => $stats
            ]
        ]);
    }

    /**
     * تحديث الملف الشخصي للأدمن
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $profileData = $request->only(['full_name', 'phone', 'email']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('users', 'public');
            $profileData['image'] = \Illuminate\Support\Facades\Storage::url($path);
        }

        $user->update($profileData);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الملف الشخصي بنجاح',
            'data' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'image' => $user->image
            ]
        ]);
    }

    /**
     * تغيير كلمة المرور
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة'
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
    }
}
