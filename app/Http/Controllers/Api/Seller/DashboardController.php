<?php

// app/Http/Controllers/Api/Seller/DashboardController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * إحصائيات لوحة التحكم للبائع
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        $period = $request->get('period', 'month'); // week, month, year, all

        // تحديد تاريخ البداية بناءً على الفترة
        $startDate = match($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => null
        };

        // جلب جميع منتجات البائع
        $productIds = $user->products()->withTrashed()->pluck('id');

        // إحصائيات الطلبات
        $ordersQuery = OrderItem::whereIn('product_id', $productIds)
            ->with('order');

        if ($startDate) {
            $ordersQuery->whereHas('order', function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            });
        }

        $orderItems = $ordersQuery->get();

        // استبعاد الطلبات المعلقة والملغاة والمرفوضة من حساب الإيرادات
        $revenueItems = $orderItems->filter(function($item) {
            return !in_array($item->order?->status, ['pending', 'cancelled', 'rejected']);
        });

        $totalOrders = $orderItems->groupBy('order_id')->count();
        $totalItemsSold = $revenueItems->sum('quantity');
        $totalRevenue = $revenueItems->sum(function($item) {
            return $item->price_at_purchase * $item->quantity;
        });

        // 1. حساب الطلبات المعلقة (pending أو processing)
        $pendingOrders = OrderItem::whereIn('product_id', $productIds)
            ->whereHas('order', function($query) {
                $query->whereIn('status', ['pending', 'processing']);
            })
            ->get()
            ->groupBy('order_id')
            ->count();

        // 2. المبيعات الشهرية (آخر 6 أشهر) لـ sales()
        $monthlySales = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();
            $monthName = now()->subMonths($i)->format('F'); // e.g. "January"

            $ordersInMonth = OrderItem::whereIn('product_id', $productIds)
                ->whereHas('order', function($query) use ($monthStart, $monthEnd) {
                    $query->whereBetween('created_at', [$monthStart, $monthEnd])
                          ->whereNotIn('status', ['pending', 'cancelled', 'rejected']);
                })
                ->get();

            $ordersCount = $ordersInMonth->groupBy('order_id')->count();
            $revenue = $ordersInMonth->sum(function($item) {
                return $item->price_at_purchase * $item->quantity;
            });

            $monthlySales[] = [
                'month' => $monthName,
                'orders_count' => $ordersCount,
                'revenue' => round($revenue, 2)
            ];
        }

        // 3. توزيع المنتجات حسب الفئات لـ productMix()
        $categoriesMix = DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name as name', DB::raw('count(products.id) as value'))
            ->where('products.seller_id', $user->id)
            ->groupBy('categories.id', 'categories.name')
            ->get();

        // المنتجات الأكثر مبيعاً
        $topProducts = $user->products()->withTrashed()
            ->withCount(['items as total_sold' => function($query) use ($startDate) {
                if ($startDate) {
                    $query->whereHas('order', function($q) use ($startDate) {
                        $q->where('created_at', '>=', $startDate)
                          ->whereNotIn('status', ['pending', 'cancelled', 'rejected']);
                    });
                } else {
                    $query->whereHas('order', function($q) {
                        $q->whereNotIn('status', ['pending', 'cancelled', 'rejected']);
                    });
                }
            }])
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get()
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'total_sold' => $product->total_sold,
                    'revenue' => $product->total_sold * $product->price
                ];
            });

        // المبيعات اليومية (آخر 7 أيام)
        $dailySales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $sales = OrderItem::whereIn('product_id', $productIds)
                ->whereHas('order', function($query) use ($date) {
                    $query->whereDate('created_at', $date)
                          ->whereNotIn('status', ['pending', 'cancelled', 'rejected']);
                })
                ->sum(DB::raw('quantity * price_at_purchase'));

            $dailySales[] = [
                'date' => $date,
                'sales' => round($sales, 2)
            ];
        }

        // إحصائيات المخزون
        $lowStockProducts = $user->products()
            ->where('stock_quantity', '<=', 10)
            ->where('stock_quantity', '>', 0)
            ->count();

        $outOfStockProducts = $user->products()
            ->where('stock_quantity', 0)
            ->count();

        // تقييمات المنتجات
        $totalReviews = 0;
        $averageRating = 0;
        foreach ($user->products as $product) {
            $productReviews = $product->reviews()->count();
            $totalReviews += $productReviews;
            $averageRating += $product->reviews()->avg('rating') ?? 0;
        }
        $averageRating = $totalReviews > 0 ? round($averageRating / $user->products->count(), 1) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'total_products' => $user->products()->count(),
                'total_orders' => $totalOrders,
                'total_items_sold' => $totalItemsSold,
                'total_revenue' => round($totalRevenue, 2),
                'pending_orders' => $pendingOrders,
                'summary' => [
                    'total_products' => $user->products()->count(),
                    'total_orders' => $totalOrders,
                    'total_items_sold' => $totalItemsSold,
                    'total_revenue' => round($totalRevenue, 2),
                    'average_rating' => $averageRating,
                    'total_reviews' => $totalReviews,
                    'pending_orders' => $pendingOrders
                ],
                'inventory_status' => [
                    'low_stock' => $lowStockProducts,
                    'out_of_stock' => $outOfStockProducts,
                    'in_stock' => $user->products()->where('stock_quantity', '>', 10)->count()
                ],
                'top_products' => $topProducts,
                'daily_sales' => $dailySales,
                'monthly_sales' => $monthlySales,
                'categories_mix' => $categoriesMix
            ]
        ]);
    }
}
