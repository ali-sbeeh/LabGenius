<?php

// app/Http/Controllers/Api/Seller/FavoriteCustomerController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class FavoriteCustomerController extends Controller
{
    /**
     * عرض الزبائن المفضلين للبائع
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // جلب بيانات الزبائن المفضلين
        $customers = $user->favoriteUsers()
            ->where('role', 'customer')
            ->withCount(['orders' => function($query) use ($user) {
                $query->whereHas('items.product', function($q) use ($user) {
                    $q->where('seller_id', $user->id);
                });
            }])
            ->get()
            ->map(function($customer) use ($user) {
                // حساب إجمالي المشتريات من هذا البائع
                $totalSpent = $customer->orders()
                    ->whereHas('items.product', function($q) use ($user) {
                        $q->where('seller_id', $user->id);
                    })
                    ->sum('total_price');

                return [
                    'id' => $customer->id,
                    'full_name' => $customer->full_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'location' => $customer->location,
                    'total_orders' => $customer->orders_count,
                    'total_spent' => round($totalSpent, 2),
                    'added_at' => $customer->pivot->created_at->format('Y-m-d H:i:s')
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $customers,
            'count' => $customers->count()
        ]);
    }

    /**
     * إضافة زبون إلى القائمة المفضلة
     */
    public function add($customerId, Request $request)
    {
        $user = $request->user();

        // التحقق من وجود الزبون
        $customer = User::where('id', $customerId)
            ->where('role', 'customer')
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'الزبون غير موجود'
            ], 404);
        }

        if ($user->favoriteUsers()->where('favorite_user_id', $customerId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'الزبون موجود بالفعل في القائمة المفضلة'
            ], 422);
        }

        $user->favoriteUsers()->attach($customerId);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الزبون إلى القائمة المفضلة بنجاح',
            'data' => [
                'customer_id' => $customerId,
                'customer_name' => $customer->full_name
            ]
        ]);
    }

    /**
     * حذف زبون من القائمة المفضلة
     */
    public function remove($customerId, Request $request)
    {
        $user = $request->user();

        if (!$user->favoriteUsers()->where('favorite_user_id', $customerId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'الزبون غير موجود في القائمة المفضلة'
            ], 404);
        }

        $user->favoriteUsers()->detach($customerId);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الزبون من القائمة المفضلة بنجاح'
        ]);
    }
}
