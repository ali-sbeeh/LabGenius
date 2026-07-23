<?php

// app/Http/Controllers/Api/Customer/OrderController.php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Discount;
use App\Models\ShippingCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * عرض جميع طلبات الزبون
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $backendBase = config('app.url', 'http://localhost:8000');

        $orders = Order::with(['items.product.productImages', 'items.product.seller', 'payment', 'shippingCompany'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // تحويل البيانات لصيغة موحدة للفرونتند
        $normalized = $orders->getCollection()->map(function ($order) use ($backendBase) {
            return $this->formatOrder($order, $backendBase);
        });
        $orders->setCollection($normalized);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * عرض تفاصيل طلب محدد
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        $backendBase = config('app.url', 'http://localhost:8000');

        $order = Order::with(['items.product.category', 'items.product.productImages', 'items.product.seller', 'payment', 'shippingCompany'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order, $backendBase)
        ]);
    }

    /**
     * تنسيق بيانات الطلب بصيغة موحدة
     */
    private function formatOrder(Order $order, string $backendBase): array
    {
        $items = $order->items->map(function ($item) use ($backendBase) {
            $product = $item->product;
            $images = $product?->productImages ?? collect();
            $primaryImg = $images->firstWhere('is_primary', true) ?? $images->first();
            $imgPath = $primaryImg?->image_path;
            $imgUrl = $imgPath
                ? (str_starts_with($imgPath, 'http') ? $imgPath : $backendBase . $imgPath)
                : null;

            return [
                'product_id'       => $item->product_id,
                'product_name'     => $product?->name ?? '',
                'seller_name'      => $product?->seller?->full_name ?? $product?->seller?->name ?? '',
                'seller_id'        => $product?->seller_id ?? null,
                'quantity'         => $item->quantity,
                'price_at_purchase'=> $item->price_at_purchase,
                'subtotal'         => $item->quantity * $item->price_at_purchase,
                'image_url'        => $imgUrl,
            ];
        });

        $proofUrl = $order->payment?->proof_url;
        $resolvedProof = $proofUrl
            ? (str_starts_with($proofUrl, 'http') ? $proofUrl : $backendBase . $proofUrl)
            : null;

        return [
            'id'               => $order->id,
            'date'             => $order->created_at?->toDateString(),
            'status'           => $order->status,
            'total'            => $order->total_price,
            'shipping_address' => $order->shipping_address,
            'note'             => $order->note,
            'payment_method'   => $order->payment?->payment_method ?? 'cash_on_delivery',
            'payment_status'   => $order->payment?->status ?? 'pending',
            'payment_proof'    => $resolvedProof,
            'items'            => $items,
        ];
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:20',
            'payment_method' => 'required|in:cash_on_delivery,sham_cash',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_proof' => 'required_if:payment_method,sham_cash|file|mimes:jpeg,png,jpg,pdf|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // التحقق من المخزون وحساب المجموع
        $totalPrice = 0;
        $orderItems = [];

        foreach ($request->items as $itemReq) {
            $product = Product::find($itemReq['product_id']);
            $qty = $itemReq['quantity'];

            // التحقق من أن المنتج نشط (غير معطل من قبل الإدارة)
            if (!$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => "المنتج '{$product->name}' غير متاح حالياً ولا يمكن شراؤه."
                ], 422);
            }

            if ($product->stock_quantity < $qty) {
                return response()->json([
                    'success' => false,
                    'message' => "المنتج '{$product->name}' غير متوفر بالكمية المطلوبة. المتوفر: {$product->stock_quantity}"
                ], 422);
            }

            // حساب السعر بعد الخصم
            $activeDiscount = Discount::where('product_id', $product->id)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();

            $priceAfterDiscount = $activeDiscount
                ? $product->price - ($product->price * $activeDiscount->discount_percent / 100)
                : $product->price;

            $itemTotal = $priceAfterDiscount * $qty;
            $totalPrice += $itemTotal;

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $qty,
                'price_at_purchase' => $priceAfterDiscount
            ];
        }

        // إنشاء الطلب
        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id' => $user->id,
                'shipping_company_id' => null, // لم يعد إجبارياً من الـ frontend
                'total_price' => $totalPrice,
                'status' => 'pending',
                'shipping_address' => $request->shipping_address,
                'order_date' => now(),
                'note' => $request->note ?? null
            ]);

            // إضافة بيانات الدفع
            $paymentProofUrl = null;
            if ($request->payment_method === 'sham_cash' && $request->hasFile('payment_proof')) {
                $path = $request->file('payment_proof')->store('payments', 'public');
                $paymentProofUrl = \Illuminate\Support\Facades\Storage::url($path);
            }

            \App\Models\Payment::create([
                'order_id' => $order->id,
                'payment_method' => $request->payment_method,
                'amount' => $totalPrice,
                'status' => 'pending',
                'proof_url' => $paymentProofUrl
            ]);

            // تحديث الهاتف إذا تم إرساله
            if ($request->phone && !$user->phone) {
                $user->phone = $request->phone;
                $user->save();
            }

            // إنشاء عناصر الطلب
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price_at_purchase' => $item['price_at_purchase']
                ]);

                // تحديث المخزون فقط لـ sham_cash
                if ($request->payment_method === 'sham_cash') {
                    $product = Product::find($item['product_id']);
                    $product->stock_quantity -= $item['quantity'];
                    $product->save();
                }
            }

            // تفريغ السلة إذا كانت موجودة
            $cart = $user->cart()->first();
            if ($cart) {
                $cart->items()->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب بنجاح',
                'data' => [
                    'order_id' => $order->id,
                    'total_price' => $totalPrice,
                    'status' => $order->status
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الطلب',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تأكيد طلب الشراء
     */
    public function confirm($id, Request $request)
    {
        $user = $request->user();

        $order = Order::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تأكيد هذا الطلب لأنه في حالة: ' . $order->status
            ], 422);
        }

        $order->status = 'confirmed';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد الطلب بنجاح',
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status
            ]
        ]);
    }

    /**
     * إلغاء طلب
     */
    public function cancel($id, Request $request)
    {
        $user = $request->user();

        $order = Order::with('items')->where('user_id', $user->id)->where('id', $id)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء هذا الطلب لأنه في حالة: ' . $order->status
            ], 422);
        }

        DB::beginTransaction();

        try {
            // إعادة المنتجات إلى المخزون
            foreach ($order->items as $item) {
                $product = Product::withTrashed()->find($item->product_id);
                if ($product) {
                    $product->stock_quantity += $item->quantity;
                    $product->save();
                }
            }

            $order->status = 'cancelled';
            $order->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الطلب بنجاح',
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء الطلب'
            ], 500);
        }
    }
}
