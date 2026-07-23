<?php

// app/Http/Controllers/Api/Admin/RecommendationController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecommendationController extends Controller
{
    /**
     * نظام التوصية للأدمن - تحليلات وتوصيات لإدارة النظام
     */
    public function index(Request $request)
    {
        // 1. تحليل المنتجات الراكدة (لم تباع منذ فترة طويلة)
        $staleProducts = Product::whereDoesntHave('items', function($query) {
                $query->whereHas('order', function($q) {
                    $q->where('created_at', '>=', now()->subMonths(3));
                });
            })
            ->where('created_at', '<=', now()->subMonths(3))
            ->with('seller')
            ->limit(10)
            ->get()
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'seller' => $product->seller->full_name,
                    'price' => $product->price,
                    'stock' => $product->stock_quantity,
                    'days_without_sales' => now()->diffInDays($product->created_at->max($product->created_at))
                ];
            });

        // 2. تحليل البائعين ذوي الأداء الضعيف
        $underperformingSellers = User::where('role', 'seller')
            ->withCount(['products', 'products as total_sales' => function($query) {
                $query->join('order_items', 'products.id', '=', 'order_items.product_id')
                    ->selectRaw('COALESCE(SUM(order_items.quantity), 0)');
            }])
            ->having('total_sales', '<', 10)
            ->having('products_count', '>', 0)
            ->limit(10)
            ->get()
            ->map(function($seller) {
                return [
                    'id' => $seller->id,
                    'name' => $seller->full_name,
                    'products_count' => $seller->products_count,
                    'total_sales' => $seller->total_sales ?? 0,
                    'joined_days' => now()->diffInDays($seller->created_at)
                ];
            });

        // 3. تحليل الفئات الأقل مبيعاً
        $lowPerformingCategories = Category::select('categories.id', 'categories.name')
            ->withCount(['product as sales_count' => function($query) {
                $query->join('order_items', 'products.id', '=', 'order_items.product_id');
            }])
            ->orderBy('sales_count', 'asc')
            ->limit(5)
            ->get();

        // 4. توقعات المبيعات للشهر القادم
        $lastMonthSales = Order::whereMonth('created_at', now()->subMonth()->month)
            ->sum('total_price');

        $twoMonthsAgoSales = Order::whereMonth('created_at', now()->subMonths(2)->month)
            ->sum('total_price');

        $growthRate = $twoMonthsAgoSales > 0
            ? (($lastMonthSales - $twoMonthsAgoSales) / $twoMonthsAgoSales) * 100
            : 0;

        $nextMonthPrediction = $lastMonthSales * (1 + ($growthRate / 100));

        return response()->json([
            'success' => true,
            'data' => [
                'stale_products' => $staleProducts,
                'underperforming_sellers' => $underperformingSellers,
                'low_performing_categories' => $lowPerformingCategories,
                'sales_forecast' => [
                    'last_month_sales' => round($lastMonthSales, 2),
                    'predicted_next_month' => round($nextMonthPrediction, 2),
                    'growth_rate' => round($growthRate, 1),
                    'recommendation' => $growthRate < 0
                        ? 'نقترح إطلاق حملات تسويقية لتحفيز المبيعات'
                        : 'الأداء جيد. استمر في الاستراتيجيات الحالية'
                ],
                'system_health' => $this->getSystemHealth()
            ]
        ]);
    }

    private function getSystemHealth()
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $lastWeekOrders = Order::where('created_at', '>=', now()->subWeek())->count();
        $totalProducts = Product::count();
        $outOfStock = Product::where('stock_quantity', 0)->count();

        $healthScore = 100;

        if ($outOfStock > $totalProducts * 0.3) {
            $healthScore -= 20;
        }

        if ($lastWeekOrders < 10) {
            $healthScore -= 15;
        }

        if ($activeUsers < $totalUsers * 0.5) {
            $healthScore -= 10;
        }

        return [
            'score' => max(0, $healthScore),
            'status' => $healthScore >= 80 ? 'excellent' : ($healthScore >= 60 ? 'good' : 'needs_attention'),
            'metrics' => [
                'total_users' => $totalUsers,
                'active_users_percentage' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0,
                'last_week_orders' => $lastWeekOrders,
                'out_of_stock_percentage' => $totalProducts > 0 ? round(($outOfStock / $totalProducts) * 100, 1) : 0
            ]
        ];
    }
}
