<?php

// app/Http/Controllers/Api/Admin/ProductController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;


class ProductController extends Controller
{
    /**
     * عرض جميع المنتجات (مع إمكانية الفلترة)
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'seller', 'productImages', 'discount']);

        // فلترة حسب البائع
        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }

        // فلترة حسب الفئة
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // فلترة حسب الحالة
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // فلترة حسب حالة المخزون
        if ($request->has('stock_status')) {
            match($request->stock_status) {
                'low' => $query->whereBetween('stock_quantity', [1, 10]),
                'out' => $query->where('stock_quantity', 0),
                'in' => $query->where('stock_quantity', '>', 10),
                default => null
            };

        }

        // البحث
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('brand', 'LIKE', "%{$search}%");
            });
        }

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['price', 'name', 'created_at', 'stock_quantity'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        // إضافة إحصائيات لكل منتج
        $products->getCollection()->transform(function($product) {
            $product->total_sold = $product->items()->sum('quantity');
            $product->average_rating = round($product->reviews()->avg('rating') ?? 0, 1);
            return $product;
        });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * عرض تفاصيل منتج محدد
     */
    public function show($id)
    {
        $product = Product::with([
            'category', 'seller', 'productImages', 'discount',
            'reviews.user', 'items.order'
        ])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);
        }

        // إحصائيات إضافية
        $product->total_sold = $product->items()->sum('quantity');
        $product->total_revenue = $product->items()->sum(DB::raw('quantity * price_at_purchase'));
        $product->average_rating = round($product->reviews()->avg('rating') ?? 0, 1);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * حذف منتج (كأدمن)
     */


   public function destroy($id, Request $request)
{
    $product = Product::with(['items', 'cartItems', 'wishlistItems', 'reviews', 'discount', 'productImages'])->find($id);

    if (!$product) {
        return response()->json([
            'success' => false,
            'message' => 'المنتج غير موجود'
        ], 404);
    }

    $reason = $request->get('reason', 'تم الحذف بواسطة المشرف');

    // تسجيل عملية الحذف في السجلات
    \App\Models\AdminLog::create([
        'admin_id' => $request->user()->id,
        'action' => 'delete_product',
        'target_id' => $product->id,
        'target_type' => 'product',
        'details' => "تم حذف المنتج '{$product->name}' بواسطة المشرف. السبب: {$reason}"
    ]);

    // حذف عناصر السلة المرتبطة
    $product->cartItems()->delete();

    // حذف عناصر المفضلة المرتبطة
    $product->wishlistItems()->delete();

    // حذف المنتج نفسه (Soft Delete)
    $product->delete();

    // إرسال إشعار للبائع
    if ($product->seller) {
        $product->seller->notifications()->create([
            'title' => 'تم حذف منتجك',
            'message' => "تم حذف منتجك '{$product->name}' بواسطة إدارة الموقع. السبب: {$reason}",
            'type' => 'product_deleted'
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'تم حذف المنتج بنجاح مع الاحتفاظ ببيانات الطلبات والايرادات'
    ]);
}


    /**
     * تفعيل/تعطيل منتج
     */
    public function toggleActive($id, Request $request)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);
        }

        $product->is_active = !$product->is_active;
        $product->save();

        // قراءة السبب (فقط عند التعطيل)
        $reason = trim($request->input('reason', ''));

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id'    => $request->user()->id,
            'action'      => $product->is_active ? 'activate_product' : 'deactivate_product',
            'target_id'   => $product->id,
            'target_type' => 'product',
            'details'     => "تم " . ($product->is_active ? 'تفعيل' : 'تعطيل') . " المنتج '{$product->name}'"
                . ($reason ? ". السبب: {$reason}" : '')
        ]);

        // إرسال إشعار للبائع
        if ($product->is_active) {
            // تفعيل — رسالة بسيطة بدون سبب
            $notificationMessage = "تم تفعيل منتجك '{$product->name}' وهو الآن متاح للبيع.";
        } else {
            // تعطيل — تضمين السبب إذا تم إدخاله
            $notificationMessage = "تم تعطيل منتجك '{$product->name}' بواسطة إدارة الموقع.";
            if ($reason) {
                $notificationMessage .= "\nسبب التعطيل: {$reason}";
            }
        }

        $product->seller->notifications()->create([
            'title'   => $product->is_active ? 'تم تفعيل منتجك' : 'تم تعطيل منتجك',
            'message' => $notificationMessage,
            'type'    => $product->is_active ? 'product_activated' : 'product_deactivated'
        ]);

        return response()->json([
            'success' => true,
            'message' => $product->is_active ? 'تم تفعيل المنتج بنجاح' : 'تم تعطيل المنتج بنجاح',
            'data'    => [
                'product_id' => $product->id,
                'is_active'  => $product->is_active
            ]
        ]);
    }

    /**
     * تحديث مخزون منتج
     */
    public function updateStock($id, Request $request)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'stock_quantity' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStock = $product->stock_quantity;
        $product->stock_quantity = $request->stock_quantity;
        $product->save();

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'update_stock',
            'target_id' => $product->id,
            'target_type' => 'product',
            'details' => "تم تحديث مخزون المنتج '{$product->name}' من {$oldStock} إلى {$product->stock_quantity}"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المخزون بنجاح',
            'data' => [
                'product_id' => $product->id,
                'stock_quantity' => $product->stock_quantity
            ]
        ]);
    }
}
