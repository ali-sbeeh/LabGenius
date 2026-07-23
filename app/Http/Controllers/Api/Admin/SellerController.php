<?php

// app/Http/Controllers/Api/Admin/SellerController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
{
    /**
     * عرض جميع البائعين
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'seller')
            ->withCount(['products', 'products as total_sales' => function($query) {
                $query->join('order_items', 'products.id', '=', 'order_items.product_id')
                    ->selectRaw('COALESCE(SUM(order_items.quantity), 0)');
            }]);

        // البحث
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // فلترة حسب الحالة (نشط/معطل)
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // فلترة حسب التقييم
        if ($request->has('min_rating')) {
            // يمكن إضافة منطق التقييم هنا
        }

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $sellers = $query->paginate($perPage);

        // إضافة إحصائيات إضافية
        $sellers->getCollection()->transform(function($seller) {
            $seller->total_revenue = OrderItem::whereHas('product', function($q) use ($seller) {
                    $q->where('seller_id', $seller->id);
                })->sum(DB::raw('quantity * price_at_purchase'));

            $seller->products_count = $seller->products->count();
            return $seller;
        });

        return response()->json([
            'success' => true,
            'data' => $sellers
        ]);
    }

    /**
     * عرض تفاصيل بائع محدد
     */
    public function show($id)
    {
        $seller = User::where('role', 'seller')
            ->with(['products' => function($query) {
                $query->withCount(['items', 'reviews']);
            }])
            ->find($id);

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'البائع غير موجود'
            ], 404);
        }

        // إحصائيات البائع
        $stats = [
            'total_products' => $seller->products->count(),
            'total_sales' => $seller->products->sum('items_count'),
            'total_revenue' => OrderItem::whereHas('product', function($q) use ($id) {
                $q->where('seller_id', $id);
            })->sum(DB::raw('quantity * price_at_purchase')),
            'total_reviews' => $seller->products->sum('reviews_count'),
            'average_rating' => 0,
            'featured_products' => $seller->products->sortByDesc('items_count')->take(5)->values()
        ];

        $totalRating = $seller->products->sum(function($product) {
            return $product->reviews()->avg('rating') ?? 0;
        });
        $stats['average_rating'] = $stats['total_products'] > 0
            ? round($totalRating / $stats['total_products'], 1)
            : 0;

        return response()->json([
            'success' => true,
            'data' => $seller,
            'statistics' => $stats
        ]);
    }

    /**
     * إضافة بائع جديد (بواسطة الأدمن)
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

        $seller = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'location' => $request->location,
            'role' => 'seller',
            'is_active' => true,
            'terms_accepted' => true
        ]);

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'create_seller',
            'target_id' => $seller->id,
            'target_type' => 'user',
            'details' => "تم إضافة بائع جديد: '{$seller->full_name}'"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة البائع بنجاح',
            'data' => $seller
        ], 201);
    }

    /**
     * تحديث بيانات بائع
     */
    public function update($id, Request $request)
    {
        $seller = User::where('role', 'seller')->find($id);

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'البائع غير موجود'
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

        $seller->update($request->only(['full_name', 'email', 'phone', 'location']));

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'update_seller',
            'target_id' => $seller->id,
            'target_type' => 'user',
            'details' => "تم تحديث بيانات البائع '{$seller->full_name}'"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات البائع بنجاح',
            'data' => $seller
        ]);
    }

    /**
     * حذف بائع
     */
    public function destroy($id, Request $request)
    {
        $seller = User::where('role', 'seller')->find($id);

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'البائع غير موجود'
            ], 404);
        }

        // التحقق من وجود منتجات نشطة
        $activeProducts = Product::where('seller_id', $id)->where('is_active', true)->count();

        if ($activeProducts > 0) {
            return response()->json([
                'success' => false,
                'message' => "لا يمكن حذف البائع لأنه لديه {$activeProducts} منتج نشط"
            ], 422);
        }

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'delete_seller',
            'target_id' => $seller->id,
            'target_type' => 'user',
            'details' => "تم حذف البائع '{$seller->full_name}'"
        ]);

        \DB::transaction(function() use ($id, $seller) {
            // Get product IDs for cart/wishlist cleanup
            $productIds = Product::where('seller_id', $id)->pluck('id');
            
            \DB::table('cart_items')->whereIn('product_id', $productIds)->delete();
            \DB::table('wishlist_items')->whereIn('product_id', $productIds)->delete();
            
            // Soft-delete products
            Product::where('seller_id', $id)->delete();

            // Notifications
            \DB::table('notifications')->where('user_id', $id)->delete();

            // Soft-delete the seller
            $seller->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'تم حذف البائع بنجاح'
        ]);
    }

    /**
     * تفعيل/تعطيل بائع
     */
    public function toggleActive($id, Request $request)
    {
        $seller = User::where('role', 'seller')->find($id);

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'البائع غير موجود'
            ], 404);
        }

        $seller->is_active = !$seller->is_active;
        $seller->save();

        $action = $seller->is_active ? 'activate_seller' : 'deactivate_seller';
        $message = $seller->is_active ? 'تم تفعيل البائع بنجاح' : 'تم تعطيل البائع بنجاح';

        // إرسال إشعار للبائع
        $seller->notifications()->create([
            'title' => $seller->is_active ? 'تم تفعيل حسابك' : 'تم تعطيل حسابك',
            'message' => $seller->is_active
                ? 'تم تفعيل حساب البائع الخاص بك. يمكنك الآن إدارة منتجاتك.'
                : 'تم تعطيل حساب البائع الخاص بك. يرجى التواصل مع الدعم الفني للمزيد من المعلومات.',
            'type' => $seller->is_active ? 'account_activated' : 'account_deactivated'
        ]);

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => $action,
            'target_id' => $seller->id,
            'target_type' => 'user',
            'details' => $message . ": '{$seller->full_name}'"
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'seller_id' => $seller->id,
                'is_active' => $seller->is_active
            ]
        ]);
    }
}
