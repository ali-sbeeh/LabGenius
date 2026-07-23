<?php

// app/Http/Controllers/Api/Admin/FavoriteController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * عرض العناصر المفضلة للأدمن (زبائن وبائعين)
     */
    public function index(Request $request)
    {
        $admin = $request->user();

        // جلب بيانات الزبائن المفضلين
        $customers = $admin->favoriteUsers()
            ->where('role', 'customer')
            ->get()
            ->map(function($customer) {
                return [
                    'id' => $customer->id,
                    'full_name' => $customer->full_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'type' => 'customer'
                ];
            });

        // جلب بيانات البائعين المفضلين
        $sellers = $admin->favoriteUsers()
            ->where('role', 'seller')
            ->get()
            ->map(function($seller) {
                return [
                    'id' => $seller->id,
                    'full_name' => $seller->full_name,
                    'email' => $seller->email,
                    'phone' => $seller->phone,
                    'products_count' => $seller->products()->count(),
                    'type' => 'seller'
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'customers' => $customers,
                'sellers' => $sellers,
                'total' => count($customers) + count($sellers)
            ]
        ]);
    }

    /**
     * إضافة زبون إلى القائمة المفضلة
     */
    public function addCustomer($id, Request $request)
    {
        $customer = User::where('role', 'customer')->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'الزبون غير موجود'
            ], 404);
        }

        $admin = $request->user();

        if ($admin->favoriteUsers()->where('favorite_user_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'الزبون موجود بالفعل في القائمة المفضلة'
            ], 422);
        }

        $admin->favoriteUsers()->attach($id);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الزبون إلى القائمة المفضلة بنجاح'
        ]);
    }

    /**
     * إضافة بائع إلى القائمة المفضلة
     */
    public function addSeller($id, Request $request)
    {
        $seller = User::where('role', 'seller')->find($id);

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'البائع غير موجود'
            ], 404);
        }

        $admin = $request->user();

        if ($admin->favoriteUsers()->where('favorite_user_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'البائع موجود بالفعل في القائمة المفضلة'
            ], 422);
        }

        $admin->favoriteUsers()->attach($id);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة البائع إلى القائمة المفضلة بنجاح'
        ]);
    }

    /**
     * حذف عنصر من القائمة المفضلة
     */
    public function remove($type, $id, Request $request)
    {
        if (!in_array($type, ['customer', 'seller'])) {
            return response()->json([
                'success' => false,
                'message' => 'نوع العنصر غير صالح'
            ], 422);
        }

        $admin = $request->user();

        if (!$admin->favoriteUsers()->where('favorite_user_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'العنصر غير موجود في القائمة المفضلة'
            ], 404);
        }

        $admin->favoriteUsers()->detach($id);

        $message = $type === 'customer' ? 'تم حذف الزبون من القائمة المفضلة بنجاح' : 'تم حذف البائع من القائمة المفضلة بنجاح';

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }
}
