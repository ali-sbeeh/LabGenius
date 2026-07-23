<?php

// app/Http/Controllers/Api/Seller/RecommendationController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecommendationController extends Controller
{
    /**
     * نظام التوصية للبائع - اقتراحات لتحسين المبيعات
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $limit = $request->get('limit', 10);

        // 1. اقتراح منتجات مشابهة للمنتجات الأكثر مبيعاً
        $topProducts = $user->products()
            ->withCount(['items as total_sold'])
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get();

        $topProductIds = $topProducts->pluck('id')->toArray();
        $topCategoryIds = $topProducts->pluck('category_id')->toArray();

        // اقتراح منتجات في نفس الفئات
        $recommendedProducts = Product::with(['category', 'productImages'])
            ->where('seller_id', $user->id)
            ->whereNotIn('id', $topProductIds)
            ->whereIn('category_id', $topCategoryIds)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // 2. اقتراح تحسينات للمنتجات ذات المبيعات المنخفضة
        $lowPerformanceProducts = $user->products()
            ->with(['category', 'productImages'])
            ->withCount(['items as total_sold'])
            ->where('stock_quantity', '>', 0)
            ->having('total_sold', '<', 5)
            ->orderBy('total_sold', 'asc')
            ->limit(5)
            ->get()
            ->map(function($product) {
                // حساب متوسط سعر المنتجات المشابهة
                $similarAvgPrice = Product::where('category_id', $product->category_id)
                    ->where('id', '!=', $product->id)
                    ->avg('price') ?? 0;

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'current_price' => $product->price,
                    'total_sold' => $product->total_sold,
                    'recommendation' => $product->price > $similarAvgPrice * 1.2
                        ? 'نقترح تخفيض السعر لزيادة المبيعات'
                        : 'نقترح تحسين وصف المنتج أو إضافة صور أفضل',
                    'suggested_price' => $similarAvgPrice > 0 ? round($similarAvgPrice, 2) : null
                ];
            });

        // 3. تحليل الموسمية
        $monthlySales = OrderItem::whereHas('product', function($query) use ($user) {
                $query->where('seller_id', $user->id);
            })
            ->select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(quantity) as total_sold'),
                DB::raw('SUM(quantity * price_at_purchase) as total_revenue')
            )
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'top_products' => $topProducts->map(function($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'total_sold' => $product->total_sold
                    ];
                }),
                'recommended_products' => $recommendedProducts,
                'low_performance_products' => $lowPerformanceProducts,
                'seasonal_analysis' => $monthlySales,
                'summary' => [
                    'total_views_needed' => 'قم بتحسين SEO لمنتجاتك لزيادة الظهور',
                    'best_selling_category' => $topCategoryIds[0] ?? null,
                    'recommended_action' => count($lowPerformanceProducts) > 0
                        ? 'يوجد ' . count($lowPerformanceProducts) . ' منتجات ذات أداء منخفض. نقترح مراجعتها.'
                        : 'أداء منتجاتك ممتاز. استمر في نفس النهج!'
                ]
            ]
        ]);
    }
}
