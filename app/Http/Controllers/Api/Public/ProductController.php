<?php

// app/Http/Controllers/Api/Public/ProductController.php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * عرض جميع المنتجات مع إمكانية التصفية والترتيب
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'productImages', 'seller'])
            ->where('stock_quantity', '>', 0);

        // فلترة حسب الفئة
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // فلترة حسب الماركة
        if ($request->has('brand')) {
            $query->where('brand', 'LIKE', "%{$request->brand}%");
        }

        // فلترة حسب السعر
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // فلترة حسب نوع المعالج (CPU)
        if ($request->has('cpu_type')) {
            $query->where('cpu_type', 'LIKE', "%{$request->cpu_type}%");
        }

        // فلترة حسب حجم الرام
        if ($request->has('ram_size')) {
            $query->where('ram_size', $request->ram_size);
        }

        // فلترة حسب حجم التخزين
        if ($request->has('storage_size')) {
            $query->where('storage_size', $request->storage_size);
        }

        // فلترة حسب حجم الشاشة
        if ($request->has('screen_size')) {
            $query->where('screen_size', $request->screen_size);
        }

        // فلترة حسب حالة المنتج (new, used, refurbished)
        if ($request->has('condition')) {
            $query->where('condition', $request->condition);
        }

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['price', 'name', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // الصفحات
        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        // إضافة التفاصيل الإضافية (الخصم، المبيعات، التقييم) لكل منتج
        $products->getCollection()->transform(function ($product) {
            return $this->appendProductDetails($product);
        });

        return response()->json([
            'success' => true,
            'data' => $products,
            'filters' => $request->all()
        ]);
    }

    /**
     * عرض منتج محدد
     */
    public function show($id)
    {
        $product = Product::with([
            'category',
            'productImages',
            'seller',
            'reviews.user',
            'discount'
        ])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->appendProductDetails($product)
        ]);
    }

    /**
     * البحث عن المنتجات
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $query = Product::with(['category', 'productImages'])
            ->where('stock_quantity', '>', 0);

        $searchTerm = $request->q;

        $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%")
              ->orWhere('description', 'LIKE', "%{$searchTerm}%")
              ->orWhere('brand', 'LIKE', "%{$searchTerm}%")
              ->orWhere('cpu_type', 'LIKE', "%{$searchTerm}%")
              ->orWhere('gpu_type', 'LIKE', "%{$searchTerm}%");
        });

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        $products->getCollection()->transform(function ($product) {
            return $this->appendProductDetails($product);
        });

        return response()->json([
            'success' => true,
            'data' => $products,
            'search_term' => $searchTerm
        ]);
    }

    /**
     * فلترة المنتجات (فلترة متقدمة)
     */
    public function filter(Request $request)
    {
        $query = Product::where('stock_quantity', '>', 0);

        // تطبيق جميع الفلاتر المتاحة
        $filters = [
            'category_id', 'brand', 'cpu_type', 'ram_size',
            'storage_size', 'screen_size', 'gpu_type', 'condition', 'os'
        ];

        foreach ($filters as $filter) {
            if ($request->has($filter)) {
                $query->where($filter, $request->$filter);
            }
        }

        // نطاق السعر
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // نطاق الوزن
        if ($request->has('min_weight')) {
            $query->where('weight', '>=', $request->min_weight);
        }
        if ($request->has('max_weight')) {
            $query->where('weight', '<=', $request->max_weight);
        }

        $products = $query->get()->map(function ($product) {
            return $this->appendProductDetails($product);
        });

        // إحصائيات الفلتر (للعرض في واجهة المستخدم)
        $stats = [
            'price_range' => [
                'min' => Product::min('price'),
                'max' => Product::max('price')
            ],
            'brands' => Product::select('brand')->distinct()->whereNotNull('brand')->pluck('brand'),
            'cpu_types' => Product::select('cpu_type')->distinct()->whereNotNull('cpu_type')->pluck('cpu_type'),
            'ram_sizes' => Product::select('ram_size')->distinct()->whereNotNull('ram_size')->pluck('ram_size'),
            'storage_sizes' => Product::select('storage_size')->distinct()->whereNotNull('storage_size')->pluck('storage_size'),
            'screen_sizes' => Product::select('screen_size')->distinct()->whereNotNull('screen_size')->pluck('screen_size'),
            'operating_systems' => Product::select('os')->distinct()->whereNotNull('os')->pluck('os'),
            'conditions' => Product::select('condition')->distinct()->pluck('condition')
        ];

        return response()->json([
            'success' => true,
            'data' => $products,
            'count' => $products->count(),
            'filter_stats' => $stats
        ]);
    }

    /**
     * أحدث المنتجات
     */
    public function latest(Request $request)
    {
        $limit = $request->get('limit', 10);

        $products = Product::with(['category', 'productImages'])
            ->where('stock_quantity', '>', 0)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return $this->appendProductDetails($product);
            });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * الأكثر مبيعاً (حسب عدد مرات الظهور في الطلبات)
     */
    public function popular(Request $request)
    {
        $limit = $request->get('limit', 10);

        $products = Product::with(['category', 'productImages'])
            ->where('stock_quantity', '>', 0)
            ->withCount('items') // items هي العلاقة مع OrderItem
            ->orderBy('items_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return $this->appendProductDetails($product);
            });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * مراجعات منتج محدد
     */
    public function reviews($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);
        }

        $reviews = $product->reviews()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $summary = [
            'average_rating' => round($product->reviews()->avg('rating') ?? 0, 1),
            'total_reviews' => $product->reviews()->count(),
            '5_stars' => $product->reviews()->where('rating', 5)->count(),
            '4_stars' => $product->reviews()->where('rating', 4)->count(),
            '3_stars' => $product->reviews()->where('rating', 3)->count(),
            '2_stars' => $product->reviews()->where('rating', 2)->count(),
            '1_stars' => $product->reviews()->where('rating', 1)->count()
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $reviews
        ]);
    }

    /**
     * إضافة التفاصيل الإضافية (الخصم والمراجعات والمبيعات) للمنتج
     */
    private function appendProductDetails($product)
    {
        $activeDiscount = Discount::where('product_id', $product->id)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if ($activeDiscount) {
            $product->discount_percent = $activeDiscount->discount_percent;
            $product->discounted_price = $product->price - ($product->price * $activeDiscount->discount_percent / 100);
        } else {
            $product->discount_percent = 0;
            $product->discounted_price = $product->price;
        }

        $product->total_sold = (int)$product->items()->sum('quantity');
        $product->average_rating = round($product->reviews()->avg('rating') ?? 0, 1);
        $product->reviews_count = (int)$product->reviews()->count();

        return $product;
    }
}
