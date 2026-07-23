<?php

// app/Http/Controllers/Api/Seller/PriceEstimateController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class PriceEstimateController extends Controller
{
    /**
     * تقدير سعر مناسب لمنتج معين بناءً على السوق
     */
    public function estimate($productId, Request $request)
    {
        $user = $request->user();

        $product = Product::where('seller_id', $user->id)
            ->where('id', $productId)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود أو لا يخصك'
            ], 404);
        }

        // جلب منتجات مشابهة من نفس الفئة
        $similarProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->whereBetween('price', [$product->price * 0.5, $product->price * 1.5])
            ->limit(10)
            ->get();

        // حساب المتوسطات
        $averagePrice = $similarProducts->avg('price') ?? 0;
        $minPrice = $similarProducts->min('price') ?? 0;
        $maxPrice = $similarProducts->max('price') ?? 0;
        $medianPrice = $similarProducts->pluck('price')->median() ?? 0;

        // تحليل المواصفات
        $similarWithSameSpecs = Product::where('category_id', $product->category_id)
            ->where('cpu_type', $product->cpu_type)
            ->where('ram_size', $product->ram_size)
            ->where('storage_size', $product->storage_size)
            ->where('id', '!=', $product->id)
            ->get();

        $specsAveragePrice = $similarWithSameSpecs->avg('price') ?? 0;

        // تحديد موقع السعر في السوق
        $pricePosition = 'متوسط';
        if ($product->price < $averagePrice * 0.8) {
            $pricePosition = 'أقل من السوق';
        } elseif ($product->price > $averagePrice * 1.2) {
            $pricePosition = 'أعلى من السوق';
        }

        // اقتراح السعر الأمثل
        $optimalPrice = $specsAveragePrice > 0 ? $specsAveragePrice : $averagePrice;

        // عامل تعديل حسب حالة المنتج
        $conditionMultiplier = match($product->condition) {
            'new' => 1,
            'refurbished' => 0.85,
            'used' => 0.7,
            default => 1
        };

        $suggestedPrice = $optimalPrice * $conditionMultiplier;

        return response()->json([
            'success' => true,
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'current_price' => $product->price,
                    'condition' => $product->condition
                ],
                'market_analysis' => [
                    'average_price' => round($averagePrice, 2),
                    'min_price' => round($minPrice, 2),
                    'max_price' => round($maxPrice, 2),
                    'median_price' => round($medianPrice, 2),
                    'similar_products_count' => $similarProducts->count(),
                    'same_specs_count' => $similarWithSameSpecs->count(),
                    'same_specs_average' => round($specsAveragePrice, 2)
                ],
                'price_position' => $pricePosition,
                'recommendations' => [
                    'suggested_price' => round($suggestedPrice, 2),
                    'min_suggested' => round($suggestedPrice * 0.9, 2),
                    'max_suggested' => round($suggestedPrice * 1.1, 2),
                    'potential_improvement' => $product->price > $suggestedPrice
                        ? 'نقترح تخفيض السعر بنسبة ' . round((($product->price - $suggestedPrice) / $product->price) * 100, 1) . '% لزيادة المبيعات'
                        : 'سعر المنتج مناسب مقارنة بالسوق'
                ]
            ]
        ]);
    }
}
