<?php

// app/Http/Controllers/Api/Admin/CustomerController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * عرض جميع الزبائن
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'customer')
            ->withCount(['orders', 'reviews']);

        // البحث
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // فلترة حسب الحالة (نشط/محظور)
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // فلترة حسب تاريخ التسجيل
        if ($request->has('registered_after')) {
            $query->whereDate('created_at', '>=', $request->registered_after);
        }

        if ($request->has('registered_before')) {
            $query->whereDate('created_at', '<=', $request->registered_before);
        }

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $customers = $query->paginate($perPage);

        // إضافة إحصائيات إضافية
        $customers->getCollection()->transform(function($customer) {
            $customer->total_spent = Order::where('user_id', $customer->id)
                ->where('status', '!=', 'cancelled')
                ->sum('total_price');
            $customer->average_order_value = $customer->total_spent / max($customer->orders_count, 1);
            return $customer;
        });

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    /**
     * عرض تفاصيل زبون محدد
     */
    public function show($id)
    {
        $customer = User::where('role', 'customer')
            ->with(['orders' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            }, 'reviews.product'])
            ->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'الزبون غير موجود'
            ], 404);
        }

        // إحصائيات الزبون
        $stats = [
            'total_orders' => Order::where('user_id', $customer->id)->count(),
            'total_spent' => Order::where('user_id', $customer->id)
                ->where('status', '!=', 'cancelled')
                ->sum('total_price'),
            'average_order_value' => 0,
            'total_reviews' => $customer->reviews->count(),
            'average_rating' => round($customer->reviews->avg('rating') ?? 0, 1),
            'last_order_date' => Order::where('user_id', $customer->id)->latest()->first()?->created_at
        ];

        $stats['average_order_value'] = $stats['total_orders'] > 0
            ? $stats['total_spent'] / $stats['total_orders']
            : 0;

        return response()->json([
            'success' => true,
            'data' => $customer,
            'statistics' => $stats
        ]);
    }

    /**
     * إضافة زبون جديد (بواسطة الأدمن)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'location' => $request->location,
            'role' => 'customer',
            'is_active' => true,
            'terms_accepted' => true
        ]);

        // إنشاء سلة وقائمة مفضلة
        \App\Models\Cart::create(['user_id' => $customer->id]);
        \App\Models\Wishlist::create(['user_id' => $customer->id]);

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'create_customer',
            'target_id' => $customer->id,
            'target_type' => 'user',
            'details' => "تم إضافة زبون جديد: '{$customer->full_name}'"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الزبون بنجاح',
            'data' => $customer
        ], 201);
    }

    /**
     * تحديث بيانات زبون
     */
    public function update($id, Request $request)
    {
        $customer = User::where('role', 'customer')->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'الزبون غير موجود'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer->update($request->only(['full_name', 'email', 'phone', 'location']));

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'update_customer',
            'target_id' => $customer->id,
            'target_type' => 'user',
            'details' => "تم تحديث بيانات الزبون '{$customer->full_name}'"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الزبون بنجاح',
            'data' => $customer
        ]);
    }

    /**
     * حذف زبون
     */
    public function destroy($id, Request $request)
    {
        $customer = User::where('role', 'customer')->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'الزبون غير موجود'
            ], 404);
        }

        // التحقق من وجود طلبات معلقة (لم يتم الموافقة عليها بعد من البائع)
        $pendingOrders = Order::where('user_id', $id)
            ->where('status', 'pending')
            ->count();

        if ($pendingOrders > 0) {
            return response()->json([
                'success' => false,
                'message' => "لا يمكن حذف الزبون لأن لديه {$pendingOrders} طلب معلق لم يوافق عليه البائع بعد"
            ], 422);
        }

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'delete_customer',
            'target_id' => $customer->id,
            'target_type' => 'user',
            'details' => "تم حذف الزبون '{$customer->full_name}'"
        ]);

        \DB::transaction(function() use ($id, $customer) {
            // Cart
            $cartIds = \DB::table('carts')->where('user_id', $id)->pluck('id');
            \DB::table('cart_items')->whereIn('cart_id', $cartIds)->delete();
            \DB::table('carts')->where('user_id', $id)->delete();

            // Wishlist
            $wishlistIds = \DB::table('wishlists')->where('user_id', $id)->pluck('id');
            \DB::table('wishlist_items')->whereIn('wishlist_id', $wishlistIds)->delete();
            \DB::table('wishlists')->where('user_id', $id)->delete();

            // Notifications
            \DB::table('notifications')->where('user_id', $id)->delete();

            // Note: Orders, payments, order items, and reviews are preserved for historical record.

            $customer->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الزبون بنجاح'
        ]);
    }

    /**
     * حظر/إلغاء حظر زبون
     */
    public function toggleBlock($id, Request $request)
    {
        $customer = User::where('role', 'customer')->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'الزبون غير موجود'
            ], 404);
        }

        $customer->is_active = !$customer->is_active;
        $customer->save();

        $action = $customer->is_active ? 'unblock_customer' : 'block_customer';
        $message = $customer->is_active ? 'تم إلغاء حظر الزبون بنجاح' : 'تم حظر الزبون بنجاح';

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => $action,
            'target_id' => $customer->id,
            'target_type' => 'user',
            'details' => $message . ": '{$customer->full_name}'"
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'customer_id' => $customer->id,
                'is_active' => $customer->is_active
            ]
        ]);
    }
}
