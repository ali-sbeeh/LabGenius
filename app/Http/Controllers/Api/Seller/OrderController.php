<?php

// app/Http/Controllers/Api/Seller/OrderController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * عرض جميع الطلبات التي تحتوي على منتجات البائع
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // جلب جميع منتجات البائع
        $productIds = $user->products()->withTrashed()->pluck('id');

        // جلب عناصر الطلبات التي تحتوي على منتجات البائع
        $orderItems = OrderItem::whereIn('product_id', $productIds)
            ->with(['order.user', 'order.shippingCompany', 'order.payment', 'product.productImages'])
            ->get();

        // تجميع الطلبات
        $orders = [];
        foreach ($orderItems as $item) {
            $orderId = $item->order_id;
            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'order_id' => $item->order->id,
                    'order_date' => $item->order->created_at,
                    'status' => $item->order->status,
                    'shipping_address' => $item->order->shipping_address,
                    'shipping_company' => $item->order->shippingCompany->name ?? null,
                    'customer' => [
                        'id' => $item->order->user->id,
                        'name' => $item->order->user->full_name,
                        'email' => $item->order->user->email,
                        'phone' => $item->order->user->phone,
                        'location' => $item->order->user->location,
                        'deleted_at' => $item->order->user->deleted_at
                    ],
                    'payment' => $item->order->payment,
                    'items' => [],
                    'total_amount' => 0
                ];
            }

            $orders[$orderId]['items'][] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'price_at_purchase' => $item->price_at_purchase,
                'subtotal' => $item->quantity * $item->price_at_purchase,
                'image_url' => $this->getProductImageUrl($item->product),
            ];

            $orders[$orderId]['total_amount'] += $item->quantity * $item->price_at_purchase;
        }

        // تحويل المجموعة إلى مجموعة وترتيبها حسب التاريخ
        $orders = collect($orders)->sortByDesc('order_date')->values();

        // فلترة حسب حالة الطلب
        if ($request->has('status')) {
            $orders = $orders->where('status', $request->status);
        }

        // الصفحات
        $perPage = $request->get('per_page', 15);
        $currentPage = $request->get('page', 1);
        $paginatedOrders = $orders->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => $paginatedOrders,
            'pagination' => [
                'total' => $orders->count(),
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => ceil($orders->count() / $perPage)
            ]
        ]);
    }

    /**
     * عرض تفاصيل طلب محدد
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        // جلب منتجات البائع
        $productIds = $user->products()->withTrashed()->pluck('id');

        // جلب الطلب مع عناصره التي تخص البائع فقط
        $order = Order::with(['user', 'shippingCompany', 'payment'])
            ->where('id', $id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        // جلب عناصر الطلب التي تخص البائع
        $orderItems = OrderItem::with(['product', 'product.productImages'])
            ->where('order_id', $id)
            ->whereIn('product_id', $productIds)
            ->get();

        if ($orderItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الطلب لا يحتوي على منتجات تخصك'
            ], 404);
        }

        $orderData = [
            'order_id' => $order->id,
            'order_date' => $order->created_at,
            'status' => $order->status,
            'shipping_address' => $order->shipping_address,
            'shipping_company' => $order->shippingCompany->name ?? null,
            'customer' => [
                'id' => $order->user->id,
                'name' => $order->user->full_name,
                'email' => $order->user->email,
                'phone' => $order->user->phone,
                'location' => $order->user->location,
                'deleted_at' => $order->user->deleted_at
            ],
            'payment' => $order->payment,
            'items' => [],
            'total_amount' => 0
        ];

        foreach ($orderItems as $item) {
            $orderData['items'][] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'price_at_purchase' => $item->price_at_purchase,
                'subtotal' => $item->quantity * $item->price_at_purchase,
                'image_url' => $this->getProductImageUrl($item->product),
            ];
            $orderData['total_amount'] += $item->quantity * $item->price_at_purchase;
        }

        return response()->json([
            'success' => true,
            'data' => $orderData
        ]);
    }

    /**
     * تحديث حالة الطلب إلى "تم الشحن"
     */
    public function ship($id, Request $request)
    {
        $user = $request->user();

        // جلب منتجات البائع
        $productIds = $user->products()->withTrashed()->pluck('id');

        // التحقق من أن الطلب يحتوي على منتجات البائع
        $hasSellerProducts = OrderItem::where('order_id', $id)
            ->whereIn('product_id', $productIds)
            ->exists();

        if (!$hasSellerProducts) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الطلب لا يحتوي على منتجات تخصك'
            ], 404);
        }

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

        $order->status = 'shipped';
        $order->save();

        // إضافة إشعار للزبون
        $order->user->notifications()->create([
            'title' => 'تم شحن طلبك',
            'message' => 'تم شحن طلبك رقم #' . $order->id . ' وهو الآن في طريقه إليك',
            'type' => 'order_shipped'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الطلب إلى "تم الشحن"',
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status
            ]
        ]);
    }

    /**
     * قبول الطلب — بواسطة البائع
     *
     * COD: ينقص المخزون هنا عند القبول
     * Sham Cash: المخزون انخفض بالفعل عند إنشاء الطلب — لا يخصم مجدداً
     */
    public function accept($id, Request $request)
    {
        $user = $request->user();
        $productIds = $user->products()->withTrashed()->pluck('id');

        // التحقق من أن الطلب يحتوي على منتجات البائع
        $hasSellerProducts = \App\Models\OrderItem::where('order_id', $id)
            ->whereIn('product_id', $productIds)
            ->exists();

        if (!$hasSellerProducts) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الطلب لا يحتوي على منتجات تخصك'
            ], 404);
        }

        $order = Order::with(['payment', 'items.product'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن قبول طلب بحالة: ' . $order->status
            ], 422);
        }

        // الدفع عند الاستلام (COD): نقص المخزون عند القبول
        $paymentMethod = $order->payment?->method ?? 'cash_on_delivery';

        if ($paymentMethod === 'cash_on_delivery') {
            // نقص المخزون لمنتجات البائع فقط
            $orderItems = \App\Models\OrderItem::where('order_id', $id)
                ->whereIn('product_id', $productIds)
                ->with('product')
                ->get();

            foreach ($orderItems as $item) {
                if ($item->product) {
                    $item->product->stock_quantity = max(0, $item->product->stock_quantity - $item->quantity);
                    $item->product->save();
                }
            }
        }
        // Sham Cash: المخزون انخفض فوراً عند الطلب — لا حاجة لفعل شيء

        $order->status = 'confirmed';
        $order->save();

        // إشعار الزبون
        $order->user->notifications()->create([
            'title' => 'تم قبول طلبك',
            'message' => 'تم قبول طلبك رقم #' . $order->id . ' من قبل البائع',
            'type' => 'order_confirmed'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الطلب بنجاح',
            'data' => [
                'order_id' => $order->id,
                'status'   => $order->status,
                'stock_deducted' => $paymentMethod === 'cash_on_delivery'
            ]
        ]);
    }

    /**
     * رفض الطلب — بواسطة البائع
     *
     * Sham Cash: يجب مراجعة العربون يدوياً + إعادة المخزون يدوياً
     * COD: المخزون لم يتغير — لا حاجة لإعادة
     */
    public function reject($id, Request $request)
    {
        $user = $request->user();
        $productIds = $user->products()->withTrashed()->pluck('id');

        $hasSellerProducts = \App\Models\OrderItem::where('order_id', $id)
            ->whereIn('product_id', $productIds)
            ->exists();

        if (!$hasSellerProducts) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الطلب لا يحتوي على منتجات تخصك'
            ], 404);
        }

        $order = Order::with(['payment', 'user'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن رفض طلب بحالة: ' . $order->status
            ], 422);
        }

        $paymentMethod = $order->payment?->method ?? 'cash_on_delivery';
        $note = $request->input('note', '');
        $reason = $request->input('reason', $note); // accept either key

        // Clean reason text (the seller's typed reason)
        $sellerReason = trim($reason ?: $note) ?: 'لم يتم تحديد سبب';

        // شام كاش: يجب إعادة المخزون ومراجعة العربون يدوياً
        if ($paymentMethod === 'sham_cash') {
            $orderItems = \App\Models\OrderItem::where('order_id', $id)
                ->whereIn('product_id', $productIds)
                ->with('product')
                ->get();

            foreach ($orderItems as $item) {
                if ($item->product) {
                    // إعادة المخزون (كان خصم فوراً)
                    $item->product->stock_quantity += $item->quantity;
                    $item->product->save();
                }
            }

            // Build clean Sham Cash rejection message
            $notificationMessage = 'تم رفض طلبك رقم #' . $order->id . "\n"
                . 'سوف يتم إرجاع العربون خلال مدة أقصاها 8 ساعات.' . "\n"
                . 'سبب الرفض: ' . $sellerReason;

            $orderNote = 'تم رفض الطلب (شام كاش) - سبب: ' . $sellerReason;
        } else {
            // Build clean COD rejection message
            $notificationMessage = 'تم رفض طلبك رقم #' . $order->id . "\n"
                . 'سبب الرفض: ' . $sellerReason;

            $orderNote = 'تم رفض الطلب - سبب: ' . $sellerReason;
        }

        $order->status = 'cancelled';
        $order->note = $orderNote;
        $order->save();

        // إشعار الزبون — رسالة نظيفة
        $order->user->notifications()->create([
            'title'   => 'تم رفض طلبك',
            'message' => $notificationMessage,
            'type'    => 'order_rejected'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الطلب',
            'data' => [
                'order_id'       => $order->id,
                'status'         => $order->status,
                'note'           => $orderNote,
                'stock_restored' => $paymentMethod === 'sham_cash'
            ]
        ]);
    }

    /**
     * فلترة الطلبات حسب الحالة
     */
    public function filterByStatus($status, Request $request)
    {
        $user = $request->user();

        $validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'حالة الطلب غير صالحة'
            ], 422);
        }

        $productIds = $user->products()->withTrashed()->pluck('id');

        $orderItems = OrderItem::whereIn('product_id', $productIds)
            ->with(['order' => function($query) use ($status) {
                $query->where('status', $status);
            }, 'order.user', 'product'])
            ->get()
            ->filter(function($item) {
                return $item->order !== null;
            });

        $orders = [];
        foreach ($orderItems as $item) {
            $orderId = $item->order_id;
            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'order_id' => $item->order->id,
                    'order_date' => $item->order->created_at,
                    'status' => $item->order->status,
                    'customer_name' => $item->order->user->full_name,
                    'items' => []
                ];
            }

            $orders[$orderId]['items'][] = [
                'product_name' => $item->product->name,
                'quantity' => $item->quantity
            ];
        }

        return response()->json([
            'success' => true,
            'data' => collect($orders)->values(),
            'status' => $status,
            'count' => count($orders)
        ]);
    }

    /**
     * إحصائيات الطلبات للبائع
     */
    public function orderStats(Request $request)
    {
        $user = $request->user();
        $productIds = $user->products()->withTrashed()->pluck('id');

        $orderItems = OrderItem::whereIn('product_id', $productIds)
            ->with('order')
            ->get();

        $stats = [
            'pending' => 0,
            'confirmed' => 0,
            'processing' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'cancelled' => 0
        ];

        $ordersProcessed = [];

        foreach ($orderItems as $item) {
            $orderStatus = $item->order->status;
            if (!isset($ordersProcessed[$item->order_id])) {
                $ordersProcessed[$item->order_id] = true;
                if (isset($stats[$orderStatus])) {
                    $stats[$orderStatus]++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
    /**
     * مساعد: استخراج رابط صورة المنتج الأساسية
     */
    private function getProductImageUrl($product): ?string
    {
        if (!$product) return null;
        $images = $product->productImages ?? collect();
        $primary = $images->firstWhere('is_primary', true) ?? $images->first();
        if (!$primary) return null;
        $path = $primary->image_path;
        if (!$path) return null;
        return str_starts_with($path, 'http') ? $path : url($path);
    }
}
