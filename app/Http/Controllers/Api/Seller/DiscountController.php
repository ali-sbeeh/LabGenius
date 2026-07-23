<?php

// app/Http/Controllers/Api/Seller/DiscountController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiscountController extends Controller
{
    /**
     * عرض جميع الخصومات للبائع
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $discounts = Discount::with('product')
            ->whereHas('product', function($query) use ($user) {
                $query->where('seller_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // إضافة حالة الخصم (نشط/منتهي/قادم)
        $discounts->getCollection()->transform(function($discount) {
            $now = now();
            if ($discount->start_date <= $now && $discount->end_date >= $now) {
                $discount->status = 'active';
            } elseif ($discount->start_date > $now) {
                $discount->status = 'upcoming';
            } else {
                $discount->status = 'expired';
            }
            return $discount;
        });

        return response()->json([
            'success' => true,
            'data' => $discounts
        ]);
    }

    /**
     * إضافة خصم على منتج
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'discount_percent' => 'required|numeric|min:0|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // التحقق من أن المنتج يخص البائع
        $product = Product::where('seller_id', $user->id)
            ->where('id', $request->product_id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود أو لا يخصك'
            ], 404);
        }

        // التحقق من وجود خصم نشط على نفس المنتج
        $existingDiscount = Discount::where('product_id', $request->product_id)
            ->where(function($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                    ->orWhereBetween('end_date', [$request->start_date, $request->end_date]);
            })
            ->first();

        if ($existingDiscount) {
            return response()->json([
                'success' => false,
                'message' => 'يوجد خصم بالفعل في هذه الفترة لهذا المنتج'
            ], 422);
        }

        $discount = Discount::create([
            'product_id' => $request->product_id,
            'discount_percent' => $request->discount_percent,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description
        ]);

        // Notify all users about the discount without firing events to avoid freezing
        $users = \App\Models\User::pluck('id');
        $notifications = [];
        $now = now();
        foreach ($users as $userId) {
            $notifications[] = [
                'user_id' => $userId,
                'title' => 'خصم جديد!',
                'message' => 'تم إضافة خصم ' . $request->discount_percent . '% على المنتج ' . $product->name,
                'type' => 'product_discount:' . $product->id,
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        
        // chunk inserts for large datasets
        foreach (array_chunk($notifications, 500) as $chunk) {
            \App\Models\Notification::insert($chunk);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الخصم بنجاح',
            'data' => $discount
        ], 201);
    }

    /**
     * تحديث خصم
     */
    public function update($id, Request $request)
    {
        $user = $request->user();

        $discount = Discount::with('product')
            ->whereHas('product', function($query) use ($user) {
                $query->where('seller_id', $user->id);
            })
            ->where('id', $id)
            ->first();

        if (!$discount) {
            return response()->json([
                'success' => false,
                'message' => 'الخصم غير موجود أو لا يخصك'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'discount_percent' => 'sometimes|numeric|min:0|max:100',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $discount->update($request->only([
            'discount_percent', 'start_date', 'end_date', 'description'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الخصم بنجاح',
            'data' => $discount
        ]);
    }

    /**
     * حذف خصم
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        $discount = Discount::with('product')
            ->whereHas('product', function($query) use ($user) {
                $query->where('seller_id', $user->id);
            })
            ->where('id', $id)
            ->first();

        if (!$discount) {
            return response()->json([
                'success' => false,
                'message' => 'الخصم غير موجود أو لا يخصك'
            ], 404);
        }

        $discount->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الخصم بنجاح'
        ]);
    }

    /**
     * تفعيل/تعطيل خصم (بتحديث التاريخ)
     */
    public function toggleActive($id, Request $request)
    {
        $user = $request->user();

        $discount = Discount::with('product')
            ->whereHas('product', function($query) use ($user) {
                $query->where('seller_id', $user->id);
            })
            ->where('id', $id)
            ->first();

        if (!$discount) {
            return response()->json([
                'success' => false,
                'message' => 'الخصم غير موجود أو لا يخصك'
            ], 404);
        }

        $now = now();

        if ($discount->start_date <= $now && $discount->end_date >= $now) {
            // إنهاء الخصم الحالي
            $discount->end_date = $now->subDay();
            $message = 'تم تعطيل الخصم';
        } else {
            // تفعيل الخصم لمدة 7 أيام من الآن
            $discount->start_date = $now;
            $discount->end_date = $now->addDays(7);
            $message = 'تم تفعيل الخصم';
        }

        $discount->save();

        if ($message === 'تم تفعيل الخصم') {
            $users = \App\Models\User::pluck('id');
            $notifications = [];
            $now = now();
            foreach ($users as $userId) {
                $notifications[] = [
                    'user_id' => $userId,
                    'title' => 'تم تفعيل خصم!',
                    'message' => 'تم إعادة تفعيل خصم ' . $discount->discount_percent . '% على المنتج ' . $discount->product->name,
                    'type' => 'product_discount:' . $discount->product->id,
                    'is_read' => false,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
            
            // chunk inserts
            foreach (array_chunk($notifications, 500) as $chunk) {
                \App\Models\Notification::insert($chunk);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $discount
        ]);
    }
}
