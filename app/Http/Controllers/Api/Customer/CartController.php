<?php

// app/Http/Controllers/Api/Customer/CartController.php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * عرض محتويات السلة
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // جلب السلة مع عناصرها والمنتجات
        $cart = $user->cart()->with(['items.product' => function($query) {
            $query->with(['category', 'productImages']);
        }])->first();

        if (!$cart) {
            return response()->json([
                'success' => true,
                'data' => [
                    'items' => [],
                    'subtotal' => 0,
                    'discount' => 0,
                    'total' => 0,
                    'items_count' => 0
                ]
            ]);
        }

        $subtotal = 0;
        $items = [];

        foreach ($cart->items as $item) {
            $product = $item->product;

            // حساب السعر بعد الخصم
            $activeDiscount = Discount::where('product_id', $product->id)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();

            $discountPercent = $activeDiscount ? $activeDiscount->discount_percent : 0;
            $priceAfterDiscount = $product->price - ($product->price * $discountPercent / 100);
            $itemTotal = $priceAfterDiscount * $item->quantity;

            $items[] = [
                'id' => $item->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price' => $product->price,
                'discount_percent' => $discountPercent,
                'price_after_discount' => $priceAfterDiscount,
                'quantity' => $item->quantity,
                'total' => $itemTotal,
                'image' => $product->productImages()->where('is_primary', true)->first()?->image_path,
                'brand' => $product->brand,
                'stock_quantity' => $product->stock_quantity
            ];

            $subtotal += $itemTotal;
        }

        // حساب الخصم الإجمالي (يمكن إضافة خصومات إضافية على مستوى السلة)
        $totalDiscount = 0;
        $total = $subtotal - $totalDiscount;

        return response()->json([
            'success' => true,
            'data' => [
                'cart_id' => $cart->id,
                'items' => $items,
                'subtotal' => round($subtotal, 2),
                'discount' => round($totalDiscount, 2),
                'total' => round($total, 2),
                'items_count' => $cart->items->count(),
                'total_quantity' => $cart->items->sum('quantity')
            ]
        ]);
    }

    /**
     * إضافة منتج إلى السلة
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $cart = $user->cart()->firstOrCreate(['user_id' => $user->id]);

        $product = Product::findOrFail($request->product_id);

        // التحقق من أن المنتج نشط (غير معطل من قبل الإدارة)
        if (!$product->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'هذا المنتج غير متاح حالياً ولا يمكن إضافته إلى السلة',
            ], 422);
        }

        // التحقق من توفر المخزون
        if ($product->stock_quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'الكمية المطلوبة غير متوفرة في المخزون',
                'available_quantity' => $product->stock_quantity
            ], 422);
        }

        // البحث عن المنتج في السلة
        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($cartItem) {
            // تحديث الكمية إذا كان المنتج موجوداً
            $newQuantity = $cartItem->quantity + $request->quantity;

            if ($product->stock_quantity < $newQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'الكمية الإجمالية المطلوبة غير متوفرة في المخزون',
                    'available_quantity' => $product->stock_quantity
                ], 422);
            }

            $cartItem->quantity = $newQuantity;
            $cartItem->save();
        } else {
            // إضافة منتج جديد إلى السلة
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المنتج إلى السلة بنجاح',
            'data' => [
                'cart_item_id' => $cartItem->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $cartItem->quantity
            ]
        ]);
    }

    /**
     * تحديث كمية منتج في السلة
     */
    public function update(Request $request, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $cart = $user->cart()->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'السلة غير موجودة'
            ], 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('id', $itemId)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'العنصر غير موجود في السلة'
            ], 404);
        }

        $product = Product::find($cartItem->product_id);

        // التحقق من توفر المخزون
        if ($product->stock_quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'الكمية المطلوبة غير متوفرة في المخزون',
                'available_quantity' => $product->stock_quantity
            ], 422);
        }

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الكمية بنجاح',
            'data' => [
                'cart_item_id' => $cartItem->id,
                'quantity' => $cartItem->quantity
            ]
        ]);
    }

    /**
     * حذف منتج من السلة
     */
    public function remove($itemId, Request $request)
    {
        $user = $request->user();
        $cart = $user->cart()->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'السلة غير موجودة'
            ], 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('id', $itemId)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'العنصر غير موجود في السلة'
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المنتج من السلة بنجاح'
        ]);
    }

    /**
 * تفريغ السلة بالكامل
 */
public function clear(Request $request)
{
    $user = $request->user();

    // جلب السلة الخاصة بالمستخدم
    $cart = $user->cart()->first();

    if ($cart) {
        // حذف جميع عناصر السلة
        $deletedCount = CartItem::where('cart_id', $cart->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تفريغ السلة بنجاح',
            'data' => [
                'deleted_items_count' => $deletedCount
            ]
        ]);
    }

    // إذا ما كان في سلة أصلاً
    return response()->json([
        'success' => true,
        'message' => 'السلة فارغة بالفعل',
        'data' => [
            'deleted_items_count' => 0
        ]
    ]);
    }
}
