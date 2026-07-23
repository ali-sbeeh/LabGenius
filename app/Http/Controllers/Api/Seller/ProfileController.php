<?php

// app/Http/Controllers/Api/Seller/ProfileController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * عرض الملف الشخصي للبائع
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // تحميل العلاقات الإضافية للبائع
        $user->load(['products' => function($query) {
            $query->withCount('items'); // عدد المبيعات
        }]);

        // إحصائيات سريعة للبائع
        $totalProducts = $user->products->count();
        $totalSales = $user->products->sum('items_count');
        $totalRevenue = $user->products->sum(function($product) {
            return $product->items->sum(function($item) {
                return $item->price_at_purchase * $item->quantity;
            });
        });

        // حساب تقييم البائع (متوسط تقييمات منتجاته)
        $averageRating = 0;
        $reviewsCount = 0;
        foreach ($user->products as $product) {
            $reviewsCount += $product->reviews()->count();
            $averageRating += $product->reviews()->avg('rating') ?? 0;
        }
        $averageRating = $reviewsCount > 0 ? round($averageRating / $user->products->count(), 1) : 0;

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
                'statistics' => [
                    'total_products' => $totalProducts,
                    'total_sales' => $totalSales,
                    'total_revenue' => round($totalRevenue, 2),
                    'average_rating' => $averageRating,
                    'total_reviews' => $reviewsCount
                ]
            ]
        ]);
    }

    /**
     * تحديث الملف الشخصي للبائع
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'location' => 'sometimes|string|max:255',
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

        $profileData = $request->only(['full_name', 'phone', 'location', 'email']);

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
                'location' => $user->location,
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
