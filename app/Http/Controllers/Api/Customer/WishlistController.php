<?php

// app/Http/Controllers/Api/Customer/WishlistController.php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    /**
     * عرض القائمة المفضلة
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $wishlist = $user->wishlist()->with(['items.product' => function($query) {
            $query->with(['category', 'productImages']);
        }])->first();

        if (!$wishlist) {
            return response()->json([
                'success' => true,
                'data' => [],
                'count' => 0
            ]);
        }

        $items = [];

        foreach ($wishlist->items as $item) {
            $product = $item->product;
            $items[] = [
                'id' => $item->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price' => $product->price,
                'image' => $product->productImages()->where('is_primary', true)->first()?->image_path,
                'brand' => $product->brand,
                'in_stock' => $product->stock_quantity > 0,
                'created_at' => $item->created_at
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $items,
            'count' => count($items)
        ]);
    }

    /**
     * إضافة منتج إلى القائمة المفضلة
     */
    public function add($productId, Request $request)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);
        }

        $user = $request->user();
        $wishlist = $user->wishlist()->firstOrCreate(['user_id' => $user->id]);

        // التحقق من أن المنتج ليس موجوداً بالفعل
        $existingItem = WishlistItem::where('wishlist_id', $wishlist->id)
            ->where('product_id', $productId)
            ->first();

        if ($existingItem) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج موجود بالفعل في القائمة المفضلة'
            ], 422);
        }

        $wishlistItem = WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $productId
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المنتج إلى القائمة المفضلة بنجاح',
            'data' => [
                'wishlist_item_id' => $wishlistItem->id,
                'product_id' => $productId,
                'product_name' => $product->name
            ]
        ]);
    }

    /**
     * حذف منتج من القائمة المفضلة
     */
    public function remove($productId, Request $request)
    {
        $user = $request->user();
        $wishlist = $user->wishlist()->first();

        if (!$wishlist) {
            return response()->json([
                'success' => false,
                'message' => 'القائمة المفضلة غير موجودة'
            ], 404);
        }

        $deleted = WishlistItem::where('wishlist_id', $wishlist->id)
            ->where('product_id', $productId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود في القائمة المفضلة'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المنتج من القائمة المفضلة بنجاح'
        ]);
    }
}
