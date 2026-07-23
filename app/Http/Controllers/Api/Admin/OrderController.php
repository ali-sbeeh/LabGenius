<?php

// app/Http/Controllers/Api/Admin/OrderController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * عرض جميع الطلبات
     */
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product', 'payment', 'shippingCompany']);

        // فلترة حسب الحالة
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // فلترة حسب الزبون
        if ($request->has('customer_id')) {
            $query->where('user_id', $request->customer_id);
        }

        // فلترة حسب البائع
        if ($request->has('seller_id')) {
            $query->whereHas('items.product', function($q) use ($request) {
                $q->where('seller_id', $request->seller_id);
            });
        }

        // فلترة حسب نطاق السعر
        if ($request->has('min_price')) {
            $query->where('total_price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('total_price', '<=', $request->max_price);
        }

        // فلترة حسب التاريخ
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * عرض تفاصيل طلب محدد
     */
    public function show($id)
    {
        $order = Order::with([
            'user',
            'items.product.category',
            'items.product.seller',
            'payment',
            'shippingCompany'
        ])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        // تجميع المنتجات حسب البائع
        $itemsBySeller = [];
        foreach ($order->items as $item) {
            $sellerId = $item->product->seller_id;
            $sellerName = $item->product->seller->full_name;

            if (!isset($itemsBySeller[$sellerId])) {
                $itemsBySeller[$sellerId] = [
                    'seller_id' => $sellerId,
                    'seller_name' => $sellerName,
                    'items' => [],
                    'subtotal' => 0
                ];
            }

            $itemsBySeller[$sellerId]['items'][] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'price' => $item->price_at_purchase,
                'subtotal' => $item->quantity * $item->price_at_purchase
            ];

            $itemsBySeller[$sellerId]['subtotal'] += $item->quantity * $item->price_at_purchase;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order,
                'items_by_seller' => array_values($itemsBySeller)
            ]
        ]);
    }

    /**
     * تحديث حالة الطلب
     */
    public function updateStatus($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        $oldStatus = $order->status;
        $newStatus = $request->status;

        $order->status = $newStatus;
        $order->save();

        // إذا تم تسليم الطلب، قم بتحديث حالة الدفع
        if ($newStatus === 'delivered') {
            $payment = $order->payment;
            if ($payment && $payment->status !== 'completed') {
                $payment->status = 'completed';
                $payment->payment_date = now();
                $payment->save();
            }
        }

        // إذا تم إلغاء الطلب، قم بإعادة المنتجات إلى المخزون
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->stock_quantity += $item->quantity;
                    $product->save();
                }
            }
        }

        // إرسال إشعار للزبون
        $order->user->notifications()->create([
            'title' => 'تحديث حالة الطلب',
            'message' => "تم تحديث حالة طلبك رقم #{$order->id} إلى: " . $this->getStatusName($newStatus),
            'type' => 'order_status_updated'
        ]);

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'update_order_status',
            'target_id' => $order->id,
            'target_type' => 'order',
            'details' => "تم تحديث حالة الطلب #{$order->id} من {$oldStatus} إلى {$newStatus}"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => [
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]
        ]);
    }

    /**
     * تأكيد استلام الطلب (تسليم)
     */
    public function receive($id, Request $request)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        if ($order->status !== 'shipped') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن استلام هذا الطلب لأنه في حالة: ' . $order->status
            ], 422);
        }

        $order->status = 'delivered';
        $order->save();

        // تحديث الدفع
        $payment = $order->payment;
        if ($payment && $payment->status !== 'completed') {
            $payment->status = 'completed';
            $payment->payment_date = now();
            $payment->save();
        }

        // إرسال إشعار للزبون
        $order->user->notifications()->create([
            'title' => 'تم استلام طلبك',
            'message' => "تم استلام طلبك رقم #{$order->id} بنجاح. شكراً لثقتك بنا!",
            'type' => 'order_delivered'
        ]);

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'receive_order',
            'target_id' => $order->id,
            'target_type' => 'order',
            'details' => "تم تأكيد استلام الطلب #{$order->id}"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد استلام الطلب بنجاح',
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status
            ]
        ]);
    }

    /**
     * تأكيد شحن الطلب
     */
    public function ship($id, Request $request)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        if ($order->status !== 'processing') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن شحن هذا الطلب لأنه في حالة: ' . $order->status
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'tracking_number' => 'nullable|string|max:100',
            'shipping_notes' => 'nullable|string'
        ]);

        $order->status = 'shipped';

        if ($request->has('tracking_number')) {
            $order->tracking_number = $request->tracking_number;
        }

        $order->save();

        // إرسال إشعار للزبون
        $order->user->notifications()->create([
            'title' => 'تم شحن طلبك',
            'message' => "تم شحن طلبك رقم #{$order->id}" .
                ($request->tracking_number ? " رقم التتبع: {$request->tracking_number}" : ""),
            'type' => 'order_shipped'
        ]);

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'ship_order',
            'target_id' => $order->id,
            'target_type' => 'order',
            'details' => "تم تأكيد شحن الطلب #{$order->id}"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد شحن الطلب بنجاح',
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status,
                'tracking_number' => $order->tracking_number ?? null
            ]
        ]);
    }

    private function getStatusName($status)
    {
        $statuses = [
            'pending' => 'قيد الانتظار',
            'confirmed' => 'تم التأكيد',
            'processing' => 'قيد التجهيز',
            'shipped' => 'تم الشحن',
            'delivered' => 'تم التسليم',
            'cancelled' => 'ملغي'
        ];

        return $statuses[$status] ?? $status;
    }
}
