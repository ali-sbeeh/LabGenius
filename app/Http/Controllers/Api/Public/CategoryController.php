<?php

// app/Http/Controllers/Api/Public/CategoryController.php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * عرض جميع الفئات
     */
    public function index()
    {
        $categories = Category::withCount('product') // product هي العلاقة في Model Category
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * عرض فئة محددة
     */
    public function show($id)
    {
        $category = Category::withCount('product')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'الفئة غير موجودة'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * منتجات فئة محددة
     */
    public function products($id, Request $request)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'الفئة غير موجودة'
            ], 404);
        }

        $query = Product::with(['category', 'productImages'])
            ->where('category_id', $id)
            ->where('stock_quantity', '>', 0);

        // فلترة حسب السعر
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['price', 'name', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'category' => $category,
            'data' => $products
        ]);
    }
}
