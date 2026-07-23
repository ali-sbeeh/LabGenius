<?php

// app/Http/Controllers/Api/Public/ReviewController.php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;

class ReviewController extends Controller
{
    /**
     * عرض مراجعات منتج محدد
     */
    public function productReviews($productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);
        }

        $reviews = Review::with('user')
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    /**
     * ملخص التقييمات لمنتج محدد
     */
    public function ratingsSummary($productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);
        }

        $summary = [
            'average_rating' => round($product->reviews()->avg('rating') ?? 0, 1),
            'total_reviews' => $product->reviews()->count(),
            'ratings_count' => [
                1 => $product->reviews()->where('rating', 1)->count(),
                2 => $product->reviews()->where('rating', 2)->count(),
                3 => $product->reviews()->where('rating', 3)->count(),
                4 => $product->reviews()->where('rating', 4)->count(),
                5 => $product->reviews()->where('rating', 5)->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}
