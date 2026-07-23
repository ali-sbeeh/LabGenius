<?php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * عرض المنتجات مع دعم البحث والفلترة [cite: 25, 28]
     */
    public function index(Request $request)
    {
        // استخدام Query Builder لتجميع الفلاتر
        $query = Product::with(['category', 'productImages' => function($q) {
            $q->where('is_primary', true); // جلب الصورة الأساسية فقط للسرعة
        }]);

        // 1. البحث بالاسم أو الوصف [cite: 28, 54]
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // 2. الفلترة حسب الفئة [cite: 177]
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // 3. الفلترة حسب الميزانية (السعر) [cite: 96]
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // 4. الفلترة حسب الحالة (جديد، مستعمل، مجدد) [cite: 122]
        if ($request->has('condition')) {
            $query->where('condition', $request->condition);
        }

        // 5. الترتيب (الفرز)
        $sortBy = $request->get('sort_by', 'featured');
        if ($sortBy === 'price-asc') {
            $query->orderBy('price', 'asc');
        } elseif ($sortBy === 'price-desc') {
            $query->orderBy('price', 'desc');
        } elseif ($sortBy === 'name') {
            $query->orderBy('name', 'asc');
        } elseif ($sortBy === 'most-sold') {
            $query->withCount(['items as sales_count' => function($q) {
                $q->select(\DB::raw('COALESCE(sum(quantity), 0)'));
            }])->orderBy('sales_count', 'desc');
        } elseif ($sortBy === 'popular') {
            $query->withAvg('reviews', 'rating')
                  ->orderBy('reviews_avg_rating', 'desc');
        }

        // إرجاع النتائج مع Pagination لضمان أداء النظام [cite: 61, 74]
        $products = $query->paginate(12);

        // حساب التقييم والمبيعات لكل منتج
        $products->getCollection()->transform(function($product) {
            $product->total_sold = (int) $product->items()->sum('quantity');
            $product->average_rating = (float) round($product->reviews()->avg('rating') ?? 0, 1);
            return $product;
        });

        return response()->json([
            'status' => 'success',
            'data' => $products
        ], 200);
    }

    /**
     * عرض تفاصيل منتج محدد مع كافة الصور والمراجعات [cite: 168, 174]
     */
    public function show($id)
    {
        $product = Product::with(['category', 'productImages', 'seller', 'reviews.user'])
                          ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $product
        ], 200);
    }
}
