<?php

// app/Http/Controllers/Api/Admin/DashboardController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * إحصائيات النظام الشاملة
     */
    public function stats(Request $request)
    {
        $period = $request->get('period', 'month');

        $startDate = match($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => null
        };

        // إحصائيات المستخدمين
        $usersStats = [
            'total' => User::count(),
            'customers' => User::where('role', 'customer')->count(),
            'sellers' => User::where('role', 'seller')->count(),
            'admins' => User::where('role', 'admin')->count(),
            'new_this_period' => $startDate ? User::where('created_at', '>=', $startDate)->count() : 0
        ];

        // إحصائيات المنتجات
        $productsStats = [
            'total' => Product::count(),
            'active' => Product::where('is_active', true)->count(),
            'inactive' => Product::where('is_active', false)->count(),
            'out_of_stock' => Product::where('stock_quantity', 0)->count(),
            'low_stock' => Product::whereBetween('stock_quantity', [1, 10])->count(),
            'new_this_period' => $startDate ? Product::where('created_at', '>=', $startDate)->count() : 0
        ];

        // إحصائيات الطلبات
        $ordersQuery = Order::query();
        if ($startDate) {
            $ordersQuery->where('created_at', '>=', $startDate);
        }

        $ordersStats = [
            'total' => Order::count(),
            'pending' => Order::where('status', 'pending')->count(),
            'confirmed' => Order::where('status', 'confirmed')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'shipped' => Order::where('status', 'shipped')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'this_period' => $startDate ? $ordersQuery->count() : 0
        ];

        // الإيرادات (تزيد فقط عند موافقة البائع وتأكيد الطلب)
        $revenueQuery = Order::whereNotIn('status', ['pending', 'cancelled']);
        if ($startDate) {
            $revenueQuery->where('created_at', '>=', $startDate);
        }

        $revenueStats = [
            'total' => Order::whereNotIn('status', ['pending', 'cancelled'])->sum('total_price'),
            'this_period' => $startDate ? $revenueQuery->sum('total_price') : 0,
            'average_order_value' => Order::whereNotIn('status', ['pending', 'cancelled'])->avg('total_price') ?? 0
        ];

        // المبيعات الشهرية (آخر 12 شهر)
        $monthlySales = Order::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total_price) as revenue'),
                DB::raw('COUNT(*) as orders_count')
            )
            ->whereNotIn('status', ['pending', 'cancelled'])
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'month' => date('F Y', mktime(0, 0, 0, $item->month, 1, $item->year)),
                    'revenue' => round($item->revenue, 2),
                    'orders_count' => $item->orders_count
                ];
            });

        // التصنيفات الأكثر مبيعاً
        $topCategories = Product::withTrashed()->select('categories.name', DB::raw('COUNT(order_items.id) as total_sold'))
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get();

        // أفضل البائعين
        $topSellers = User::where('role', 'seller')
            ->withCount(['products', 'products as total_sales' => function($query) {
                $query->withTrashed()->join('order_items', 'products.id', '=', 'order_items.product_id')
                    ->select(DB::raw('SUM(order_items.quantity)'));
            }])
            ->orderBy('total_sales', 'desc')
            ->limit(5)
            ->get()
            ->map(function($seller) {
                return [
                    'id' => $seller->id,
                    'name' => $seller->full_name,
                    'email' => $seller->email,
                    'products_count' => $seller->products_count,
                    'total_sales' => $seller->total_sales ?? 0
                ];
            });

        // التقييمات
        $reviewsStats = [
            'total' => Review::count(),
            'average_rating' => round(Review::avg('rating') ?? 0, 1),
            '5_stars' => Review::where('rating', 5)->count(),
            '4_stars' => Review::where('rating', 4)->count(),
            '3_stars' => Review::where('rating', 3)->count(),
            '2_stars' => Review::where('rating', 2)->count(),
            '1_stars' => Review::where('rating', 1)->count()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'users' => $usersStats,
                'products' => $productsStats,
                'orders' => $ordersStats,
                'revenue' => $revenueStats,
                'monthly_sales' => $monthlySales,
                'top_categories' => $topCategories,
                'top_sellers' => $topSellers,
                'reviews' => $reviewsStats
            ]
        ]);
    }

    /**
     * إحصائيات سريعة لبطاقات لوحة التحكم
     */
    public function quickStats()
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $stats = [
            'total_users' => User::count(),
            'users_growth' => $this->calculateGrowth(User::class, $thisMonth),
            'total_products' => Product::count(),
            'products_growth' => $this->calculateGrowth(Product::class, $thisMonth),
            'total_orders' => Order::count(),
            'orders_growth' => $this->calculateGrowth(Order::class, $thisMonth),
            'total_revenue' => round(Order::whereNotIn('status', ['pending', 'cancelled'])->sum('total_price'), 2),
            'revenue_growth' => $this->calculateRevenueGrowth($thisMonth),
            'today_orders' => Order::whereDate('created_at', $today)->count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'low_stock_products' => Product::whereBetween('stock_quantity', [1, 10])->count(),
            'out_of_stock_products' => Product::where('stock_quantity', 0)->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    private function calculateGrowth($model, $currentPeriodStart)
    {
        $currentCount = $model::where('created_at', '>=', $currentPeriodStart)->count();
        $previousCount = $model::whereBetween('created_at', [
            $currentPeriodStart->copy()->subMonth(),
            $currentPeriodStart->copy()->subSecond()
        ])->count();

        if ($previousCount == 0) {
            return $currentCount > 0 ? 100 : 0;
        }

        return round((($currentCount - $previousCount) / $previousCount) * 100, 1);
    }

    private function calculateRevenueGrowth($currentPeriodStart)
    {
        $currentRevenue = Order::whereNotIn('status', ['pending', 'cancelled'])
            ->where('created_at', '>=', $currentPeriodStart)
            ->sum('total_price');

        $previousRevenue = Order::whereNotIn('status', ['pending', 'cancelled'])
            ->whereBetween('created_at', [
                $currentPeriodStart->copy()->subMonth(),
                $currentPeriodStart->copy()->subSecond()
            ])
            ->sum('total_price');

        if ($previousRevenue == 0) {
            return $currentRevenue > 0 ? 100 : 0;
        }

        return round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1);
    }
}
