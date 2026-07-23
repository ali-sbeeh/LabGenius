<?php

// app/Http/Controllers/Api/Seller/ProductController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Category;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * عرض جميع منتجات البائع
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Product::with(['category', 'productImages', 'discount'])
            ->where('seller_id', $user->id);

        // فلترة حسب الفئة
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // فلترة حسب الحالة (متاح/غير متاح)
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // فلترة حسب حالة المخزون
        if ($request->has('stock_status')) {
            match($request->stock_status) {
                'low' => $query->whereBetween('stock_quantity', [1, 10]),
                'out' => $query->where('stock_quantity', 0),
                'in' => $query->where('stock_quantity', '>', 10),
                default => null
            };
        }

        // البحث
        if ($request->has('search')) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['price', 'name', 'created_at', 'stock_quantity'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        // إضافة إحصائيات لكل منتج
        $products->getCollection()->transform(function($product) {
            $product->total_sold = $product->items()->sum('quantity');
            $product->average_rating = round($product->reviews()->avg('rating') ?? 0, 1);
            $product->reviews_count = $product->reviews()->count();

            // السعر بعد الخصم
            $activeDiscount = $product->discount ? $product->discount->first(function($d) {
                $now = now();
                return $d->start_date <= $now && $d->end_date >= $now;
            }) : null;

            if ($activeDiscount) {
                $product->discounted_price = $product->price - ($product->price * $activeDiscount->discount_percent / 100);
            }

            return $product;
        });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * عرض منتج محدد
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        $product = Product::with(['category', 'productImages', 'discount', 'reviews.user'])
            ->where('seller_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود أو لا يخصك'
            ], 404);
        }

        // إحصائيات إضافية
        $product->total_sold = $product->items()->sum('quantity');
        $product->total_revenue = $product->items()->sum(DB::raw('quantity * price_at_purchase'));
        $product->average_rating = round($product->reviews()->avg('rating') ?? 0, 1);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * إضافة منتج جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'brand' => 'required|string|max:100',
            'stock_quantity' => 'required|integer|min:0',
            'condition' => 'required|in:new,used,refurbished',
            'description' => 'nullable|string',
            'cpu_type' => 'required|string|max:100',
            'ram_size' => 'required|string|max:50',
            'gpu_type' => 'nullable|string|max:100',
            'igpu' => 'nullable|string|max:100',
            'storage_size' => 'required|string|max:50',
            'screen_size' => 'required|string|max:50',
            'battery_capacity' => 'nullable|string|max:50',
            'os' => 'required|string|max:100',
            'weight' => 'required|numeric|min:0',
            'recommended_usage' => 'nullable|string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'primary_image_index' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        DB::beginTransaction();

        try {
            // إنشاء المنتج
            $product = Product::create([
                'seller_id' => $user->id,
                'category_id' => $request->category_id,
                'name' => $request->name,
                'price' => $request->price,
                'brand' => $request->brand,
                'stock_quantity' => $request->stock_quantity,
                'condition' => $request->condition,
                'description' => $request->description ?? '',
                'cpu_type' => $request->cpu_type,
                'ram_size' => $request->ram_size,
                'gpu_type' => $request->gpu_type ?? '',
                'igpu' => $request->igpu,
                'storage_size' => $request->storage_size,
                'screen_size' => $request->screen_size,
                'battery_capacity' => $request->battery_capacity ?? '',
                'os' => $request->os,
                'weight' => $request->weight,
                'recommended_usage' => $request->recommended_usage ?? '',
                'is_active' => true
            ]);

            // معالجة الصور
            $primaryImageIndex = $request->get('primary_image_index', 0);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products/' . $product->id, 'public');

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => Storage::url($path),
                        'is_primary' => ($index == $primaryImageIndex)
                    ]);
                }
            } else {
                // صورة افتراضية
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => '/storage/default-product.png',
                    'is_primary' => true
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة المنتج بنجاح',
                'data' => $product->load('productImages')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error adding product: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة المنتج',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث منتج
     */
    public function update($id, Request $request)
    {
        $user = $request->user();

        $product = Product::where('seller_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود أو لا يخصك'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'brand' => 'sometimes|string|max:100',
            'stock_quantity' => 'sometimes|integer|min:0',
            'condition' => 'sometimes|in:new,used,refurbished',
            'description' => 'nullable|string',
            'cpu_type' => 'sometimes|string|max:100',
            'ram_size' => 'sometimes|string|max:50',
            'gpu_type' => 'nullable|string|max:100',
            'igpu' => 'nullable|string|max:100',
            'storage_size' => 'sometimes|string|max:50',
            'screen_size' => 'sometimes|string|max:50',
            'battery_capacity' => 'nullable|string|max:50',
            'os' => 'sometimes|string|max:100',
            'weight' => 'sometimes|numeric|min:0',
            'recommended_usage' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'primary_image_index' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = $request->only([
                'category_id', 'name', 'price', 'brand', 'stock_quantity',
                'condition', 'description', 'cpu_type', 'ram_size', 'gpu_type', 'igpu',
                'storage_size', 'screen_size', 'battery_capacity', 'os', 'weight', 'is_active', 'recommended_usage'
            ]);

            // منع القيمة null في battery_capacity (العمود NOT NULL)
            if (array_key_exists('battery_capacity', $updateData) && is_null($updateData['battery_capacity'])) {
                $updateData['battery_capacity'] = '';
            }
            // إذا لم يُرسَل battery_capacity أصلاً، لا تُحدّثه
            if (!array_key_exists('battery_capacity', $updateData)) {
                // تجاهل — نحتفظ بالقيمة الحالية
            }

            $product->update($updateData);

            // معالجة الصور إذا تم رفعها
            if ($request->hasFile('images')) {
                // حذف الصور القديمة
                foreach ($product->productImages as $image) {
                    $path = str_replace('/storage/', '', $image->image_path);
                    Storage::disk('public')->delete($path);
                    $image->delete();
                }

                $primaryImageIndex = $request->get('primary_image_index', 0);
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products/' . $product->id, 'public');

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => Storage::url($path),
                        'is_primary' => ($index == $primaryImageIndex)
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المنتج بنجاح',
                'data' => $product->load('productImages')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث المنتج: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();

        $product = Product::with('productImages')->where('seller_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود أو لا يخصك'
            ], 404);
        }

        try {
            // حذف عناصر السلة المرتبطة
            $product->cartItems()->delete();

            // حذف عناصر المفضلة المرتبطة
            $product->wishlistItems()->delete();

            // حذف المنتج نفسه (Soft Delete)
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المنتج بنجاح مع الاحتفاظ ببيانات الطلبات والايرادات'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف المنتج: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * تفعيل/تعطيل منتج
     */
    public function toggleActive($id, Request $request)
    {
        $user = $request->user();

        $product = Product::where('seller_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود أو لا يخصك'
            ], 404);
        }

        $product->is_active = !$product->is_active;
        $product->save();

        return response()->json([
            'success' => true,
            'message' => $product->is_active ? 'تم تفعيل المنتج بنجاح' : 'تم تعطيل المنتج بنجاح',
            'data' => [
                'product_id' => $product->id,
                'is_active' => $product->is_active
            ]
        ]);
    }

    /**
     * إضافة صور لمنتج
     */
    public function addImages($id, Request $request)
    {
        $user = $request->user();

        $product = Product::where('seller_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود أو لا يخصك'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'images' => 'required|array',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $uploadedImages = [];

        foreach ($request->file('images') as $image) {
            $path = $image->store('products/' . $product->id, 'public');

            $productImage = ProductImage::create([
                'product_id' => $product->id,
                'image_path' => Storage::url($path),
                'is_primary' => false
            ]);

            $uploadedImages[] = $productImage;
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الصور بنجاح',
            'data' => $uploadedImages
        ]);
    }

    /**
     * حذف صورة من منتج
     */
    public function deleteImage($productId, $imageId, Request $request)
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

        $image = ProductImage::where('product_id', $product->id)
            ->where('id', $imageId)
            ->first();

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'الصورة غير موجودة'
            ], 404);
        }

        // حذف الملف
        $path = str_replace('/storage/', '', $image->image_path);
        Storage::disk('public')->delete($path);

        if ($image->is_primary) {
            $newPrimary = ProductImage::where('product_id', $product->id)
                ->where('id', '!=', $imageId)
                ->first();

            if ($newPrimary) {
                $newPrimary->is_primary = true;
                $newPrimary->save();
            }
        }

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الصورة بنجاح'
        ]);
    }

    /**
     * تعيين صورة كأساسية
     */
    public function setPrimaryImage($productId, $imageId, Request $request)
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

        // إلغاء تعيين الصورة الأساسية الحالية
        ProductImage::where('product_id', $product->id)
            ->update(['is_primary' => false]);

        // تعيين الصورة الجديدة كأساسية
        $image = ProductImage::where('product_id', $product->id)
            ->where('id', $imageId)
            ->first();

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'الصورة غير موجودة'
            ], 404);
        }

        $image->is_primary = true;
        $image->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تعيين الصورة كأساسية بنجاح'
        ]);
    }
}
