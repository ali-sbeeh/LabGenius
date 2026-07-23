<?php

// app/Http/Controllers/Api/Admin/CategoryController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * عرض جميع الفئات
     */
    public function index(Request $request)
    {
        $categories = Category::withCount('product')
            ->orderBy('name')
            ->get();

        // بناء هيكل الشجرة
        $tree = $this->buildTree($categories);

        return response()->json([
            'success' => true,
            'data' => $tree,
            'flat' => $categories
        ]);
    }

    /**
     * عرض فئة محددة
     */
    public function show($id)
    {
        $category = Category::with('parent')->withCount('product')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'الفئة غير موجودة'
            ], 404);
        }

        $subcategories = Category::where('parent_id', $id)->withCount('product')->get();

        return response()->json([
            'success' => true,
            'data' => $category,
            'subcategories' => $subcategories
        ]);
    }

    /**
     * إضافة فئة جديدة
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:categories',
            'parent_id' => 'nullable|exists:categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $category = Category::create([
            'name' => $request->name,
            'parent_id' => $request->parent_id
        ]);

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'create_category',
            'target_id' => $category->id,
            'target_type' => 'category',
            'details' => "تم إضافة فئة جديدة: '{$category->name}'"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الفئة بنجاح',
            'data' => $category
        ], 201);
    }

    /**
     * تحديث فئة
     */
    public function update($id, Request $request)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'الفئة غير موجودة'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100|unique:categories,name,' . $id,
            'parent_id' => 'nullable|exists:categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldName = $category->name;
        $category->update($request->only(['name', 'parent_id']));

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'update_category',
            'target_id' => $category->id,
            'target_type' => 'category',
            'details' => "تم تحديث الفئة من '{$oldName}' إلى '{$category->name}'"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الفئة بنجاح',
            'data' => $category
        ]);
    }

    /**
     * حذف فئة
     */
    public function destroy($id, Request $request)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'الفئة غير موجودة'
            ], 404);
        }

        // التحقق من وجود منتجات في هذه الفئة
        $productsCount = Product::where('category_id', $id)->count();

        if ($productsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "لا يمكن حذف هذه الفئة لأنها تحتوي على {$productsCount} منتج. قم بنقل المنتجات إلى فئة أخرى أولاً"
            ], 422);
        }

        // التحقق من وجود فئات فرعية
        $subcategoriesCount = Category::where('parent_id', $id)->count();

        if ($subcategoriesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "لا يمكن حذف هذه الفئة لأنها تحتوي على {$subcategoriesCount} فئة فرعية"
            ], 422);
        }

           // التحقق من وجود فئات فرعية (children)
    $subcategoriesCount = Category::where('parent_id', $id)->count();  // هذا صحيح


        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'delete_category',
            'target_id' => $category->id,
            'target_type' => 'category',
            'details' => "تم حذف الفئة '{$category->name}'"
        ]);

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الفئة بنجاح'
        ]);
    }

    /**
     * بناء شجرة الفئات
     */
    private function buildTree($categories, $parentId = null)
    {
        $tree = [];

        foreach ($categories as $category) {
            if ($category->parent_id == $parentId) {
                $children = $this->buildTree($categories, $category->id);
                if ($children) {
                    $category->children = $children;
                }
                $tree[] = $category;
            }
        }

        return $tree;
    }
}
