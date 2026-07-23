<?php

// app/Http/Controllers/Api/Customer/RecommendationController.php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\WishlistItem;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * نظام التوصية - اقتراح منتجات للزبون
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $limit = $request->get('limit', 10);

        // 1. توصيات بناءً على سجل الشراء
        $purchasedProducts = OrderItem::whereHas('order', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->pluck('product_id')->toArray();

        // 2. توصيات بناءً على القائمة المفضلة
        $wishlistProducts = WishlistItem::whereHas('wishlist', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->pluck('product_id')->toArray();

        // 3. توصيات بناءً على فئات المنتجات المشتراة
        $purchasedCategories = Product::whereIn('id', $purchasedProducts)
            ->pluck('category_id')
            ->toArray();

        $recommendations = Product::with(['category', 'productImages'])
            ->where('stock_quantity', '>', 0)
            ->whereNotIn('id', array_merge($purchasedProducts, $wishlistProducts))
            ->when(!empty($purchasedCategories), function($query) use ($purchasedCategories) {
                $query->whereIn('category_id', $purchasedCategories);
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('price', 'asc')
            ->limit($limit)
            ->get();

        // إذا لم تكن هناك توصيات كافية، أضف منتجات عشوائية
        if ($recommendations->count() < $limit) {
            $remainingCount = $limit - $recommendations->count();
            $existingIds = $recommendations->pluck('id')->toArray();

            $additionalProducts = Product::with(['category', 'productImages'])
                ->where('stock_quantity', '>', 0)
                ->whereNotIn('id', array_merge($purchasedProducts, $wishlistProducts, $existingIds))
                ->inRandomOrder()
                ->limit($remainingCount)
                ->get();

            $recommendations = $recommendations->merge($additionalProducts);
        }

        return response()->json([
            'success' => true,
            'data' => $recommendations,
            'count' => $recommendations->count(),
            'recommendation_based_on' => [
                'purchased_products' => count($purchasedProducts),
                'wishlist_products' => count($wishlistProducts),
                'favorite_categories' => array_unique($purchasedCategories)
            ]
        ]);
    }
}
