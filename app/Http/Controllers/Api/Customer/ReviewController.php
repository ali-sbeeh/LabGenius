<?php

// app/Http/Controllers/Api/Customer/ReviewController.php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Notification;

class ReviewController extends Controller
{
    /**
     * إضافة مراجعة على منتج
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // التحقق من أن المستخدم قد اشترى هذا المنتج
        $hasPurchased = OrderItem::whereHas('order', function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('status', '!=', 'cancelled');
            })
            ->where('product_id', $request->product_id)
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'success' => false,
                'message' => 'يمكنك فقط مراجعة المنتجات التي قمت بشرائها'
            ], 403);
        }

        // التحقق من عدم وجود مراجعة سابقة
        $existingReview = Review::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'لقد قمت بإضافة مراجعة لهذا المنتج مسبقاً. يمكنك تعديلها بدلاً من ذلك'
            ], 422);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'rating' => $request->rating,
            'comment' => $request->comment
        ]);

        // إرسال إشعار للبائع
        $product = Product::find($request->product_id);
        if ($product && $product->seller_id) {
            Notification::create([
                'user_id' => $product->seller_id,
                'title' => 'تقييم جديد لمنتجك',
                'message' => "قام العميل {$user->full_name} بإضافة تقييم ({$request->rating} نجوم) لمنتجك '{$product->name}'",
                'type' => 'new_review',
                'is_read' => false
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المراجعة بنجاح',
            'data' => $review
        ], 201);
    }

    /**
     * تعديل مراجعة
     */
    public function update($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        $review = Review::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'المراجعة غير موجودة أو لا تملك صلاحية تعديلها'
            ], 404);
        }

        $review->update($request->only(['rating', 'comment']));

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل المراجعة بنجاح',
            'data' => $review
        ]);
    }

    /**
     * حذف مراجعة
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        $review = Review::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'المراجعة غير موجودة أو لا تملك صلاحية حذفها'
            ], 404);
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المراجعة بنجاح'
        ]);
    }
}
