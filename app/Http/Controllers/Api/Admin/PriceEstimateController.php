<?php

// app/Http/Controllers/Api/Admin/PriceEstimateController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class PriceEstimateController extends Controller
{
    /**
     * تقدير سعر مناسب لمنتج معين (تحليل شامل للسوق)
     */
    public function estimate($productId, Request $request)
    {
        $product = Product::with(['category', 'seller'])->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);
        }

        // تحليل المنافسين - منتجات مشابهة في نفس الفئة
        $competitors = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->whereBetween('price', [$product->price * 0.5, $product->price * 2])
            ->limit(20)
            ->get();

        // إحصائيات السوق للفئة
        $categoryStats = [
            'min_price' => Product::where('category_id', $product->category_id)->min('price') ?? 0,
            'max_price' => Product::where('category_id', $product->category_id)->max('price') ?? 0,
            'avg_price' => Product::where('category_id', $product->category_id)->avg('price') ?? 0,
            'median_price' => Product::where('category_id', $product->category_id)->pluck('price')->median() ?? 0,
            'total_products' => Product::where('category_id', $product->category_id)->count()
        ];

        // تحليل حسب المواصفات المتطابقة
        $sameSpecsProducts = Product::where('category_id', $product->category_id)
            ->where('cpu_type', $product->cpu_type)
            ->where('ram_size', $product->ram_size)
            ->where('storage_size', $product->storage_size)
            ->where('id', '!=', $product->id)
            ->get();

        $sameSpecsStats = [
            'count' => $sameSpecsProducts->count(),
            'avg_price' => $sameSpecsProducts->avg('price') ?? 0,
            'min_price' => $sameSpecsProducts->min('price') ?? 0,
            'max_price' => $sameSpecsProducts->max('price') ?? 0
        ];

        // تحديد موقع السعر في السوق
        $percentile = 0;
        if ($categoryStats['total_products'] > 0) {
            $lowerCount = Product::where('category_id', $product->category_id)
                ->where('price', '<', $product->price)
                ->count();
            $percentile = ($lowerCount / $categoryStats['total_products']) * 100;
        }

        // التوصية النهائية
        $recommendedPrice = $sameSpecsStats['avg_price'] > 0
            ? $sameSpecsStats['avg_price']
            : $categoryStats['avg_price'];

        // تعديل حسب حالة المنتج
        $conditionMultiplier = match($product->condition) {
            'new' => 1,
            'refurbished' => 0.85,
            'used' => 0.7,
            default => 1
        };

        $adjustedRecommendedPrice = $recommendedPrice * $conditionMultiplier;

        $pricePosition = 'متوسط';
        if ($percentile <= 25) {
            $pricePosition = 'منخفض (أقل من 75% من المنتجات)';
        } elseif ($percentile >= 75) {
            $pricePosition = 'مرتفع (أعلى من 75% من المنتجات)';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'current_price' => $product->price,
                    'condition' => $product->condition,
                    'seller' => $product->seller->full_name
                ],
                'market_analysis' => [
                    'category' => $product->category->name,
                    'category_stats' => $categoryStats,
                    'same_specs_stats' => $sameSpecsStats,
                    'competitors_count' => $competitors->count()
                ],
                'price_analysis' => [
                    'percentile' => round($percentile, 1),
                    'price_position' => $pricePosition,
                    'is_competitive' => $product->price <= $categoryStats['avg_price'] * 1.1
                ],
                'recommendations' => [
                    'recommended_price' => round($adjustedRecommendedPrice, 2),
                    'min_suggested' => round($adjustedRecommendedPrice * 0.85, 2),
                    'max_suggested' => round($adjustedRecommendedPrice * 1.15, 2),
                    'action' => $product->price > $adjustedRecommendedPrice * 1.1
                        ? 'نقترح تخفيض السعر ليكون أكثر تنافسية'
                        : ($product->price < $adjustedRecommendedPrice * 0.9
                            ? 'السعر مناسب جداً. يمكنك رفعه قليلاً لزيادة الأرباح'
                            : 'السعر مناسب ومتوافق مع السوق'),
                    'potential_profit_impact' => $product->price > $adjustedRecommendedPrice
                        ? 'قد يؤدي تخفيض السعر إلى زيادة المبيعات بنسبة تقديرية 20-30%'
                        : 'السعر الحالي جيد، حافظ على استراتيجيتك التسويقية'
                ]
            ]
        ]);
    }

    /**
     * تحليل أسعار فئة كاملة
     */
    public function categoryAnalysis($categoryId, Request $request)
    {
        $category = Category::find($categoryId);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'الفئة غير موجودة'
            ], 404);
        }

        $products = Product::where('category_id', $categoryId)->get();

        $priceDistribution = [
            'under_500' => $products->where('price', '<', 500)->count(),
            '500_1000' => $products->whereBetween('price', [500, 1000])->count(),
            '1000_2000' => $products->whereBetween('price', [1000, 2000])->count(),
            '2000_3000' => $products->whereBetween('price', [2000, 3000])->count(),
            'above_3000' => $products->where('price', '>', 3000)->count()
        ];

        $brands = $products->groupBy('brand')->map(function($group) {
            return [
                'count' => $group->count(),
                'avg_price' => round($group->avg('price'), 2),
                'min_price' => $group->min('price'),
                'max_price' => $group->max('price')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $category->name,
                'total_products' => $products->count(),
                'overall_stats' => [
                    'avg_price' => round($products->avg('price'), 2),
                    'min_price' => $products->min('price'),
                    'max_price' => $products->max('price'),
                    'median_price' => $products->pluck('price')->median()
                ],
                'price_distribution' => $priceDistribution,
                'brands_analysis' => $brands
            ]
        ]);
    }
}
