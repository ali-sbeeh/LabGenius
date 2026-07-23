<?php

// app/Http/Controllers/Api/Customer/PriceEstimateController.php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class PriceEstimateController extends Controller
{
    /**
     * تقدير سعر منتج معين
     */
    public function estimate($productId, Request $request)
    {
        $product = Product::with(['discount'])->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);
        }

        // السعر الأصلي
        $originalPrice = $product->price;

        // الخصم الحالي
        $currentDiscount = 0;
        $discountedPrice = $originalPrice;

        if ($product->discount) {
            $now = now();
            $activeDiscount = $product->discount->first(function($d) use ($now) {
                return $d->start_date <= $now && $d->end_date >= $now;
            });
            if ($activeDiscount) {
                $currentDiscount = $activeDiscount->discount_percent;
                $discountedPrice = $originalPrice - ($originalPrice * $currentDiscount / 100);
            }
        }

        // تقدير السعر بناءً على منتجات مشابهة
        $similarProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->whereBetween('price', [$originalPrice * 0.7, $originalPrice * 1.3])
            ->limit(5)
            ->get();

        $averageSimilarPrice = $similarProducts->avg('price') ?? 0;

        // تصنيف السعر (مرتفع، متوسط، منخفض)
        $allPrices = Product::where('category_id', $product->category_id)->pluck('price');
        $priceRank = 'medium';

        if ($allPrices->count() > 0) {
            $percentile = ($allPrices->filter(function($price) use ($originalPrice) {
                return $price <= $originalPrice;
            })->count() / $allPrices->count()) * 100;

            if ($percentile <= 33) {
                $priceRank = 'low';
            } elseif ($percentile >= 66) {
                $priceRank = 'high';
            } else {
                $priceRank = 'medium';
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'original_price' => $originalPrice,
                'current_discount' => $currentDiscount,
                'discounted_price' => round($discountedPrice, 2),
                'average_similar_price' => round($averageSimilarPrice, 2),
                'price_rank' => $priceRank,
                'estimated_fair_price' => round(($discountedPrice + $averageSimilarPrice) / 2, 2),
                'similar_products_count' => $similarProducts->count()
            ]
        ]);
    }
}
