<?php

// app/Http/Controllers/Api/Customer/PaymentController.php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * معالجة عملية الدفع
     */
    public function process(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|string|in:cash_on_delivery,credit_card,bank_transfer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        $order = Order::where('user_id', $user->id)
            ->where('id', $request->order_id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        if ($order->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إتمام الدفع. يجب تأكيد الطلب أولاً'
            ], 422);
        }

        // التحقق من وجود عملية دفع سابقة
        $existingPayment = Payment::where('order_id', $order->id)->first();
        if ($existingPayment && $existingPayment->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'تم الدفع لهذا الطلب مسبقاً'
            ], 422);
        }

        // إنشاء سجل الدفع
        $payment = Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'amount' => $order->total_price,
                'payment_method' => $request->payment_method,
                'status' => 'pending',
                'transaction_id' => 'TXN_' . uniqid() . '_' . time(),
                'payment_date' => null
            ]
        );

        // محاكاة معالجة الدفع (في الواقع سيتم الاتصال ببوابة الدفع)
        // هنا نفترض أن الدفع ناجح لطريقة الدفع عند الاستلام

        if ($request->payment_method === 'cash_on_delivery') {
            $payment->status = 'completed';
            $payment->payment_date = now();
            $payment->save();

            $order->status = 'processing';
            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'تم إتمام عملية الدفع بنجاح (الدفع عند الاستلام)',
                'data' => [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method
                ]
            ]);
        }

        // للطرق الأخرى (بطاقة ائتمان، تحويل بنكي)
        // يمكن إعادة توجيه إلى بوابة دفع خارجية أو معالجة webhook

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء طلب الدفع. يرجى إكمال عملية الدفع.',
            'data' => [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'transaction_id' => $payment->transaction_id
            ]
        ]);
    }

    /**
     * التحقق من حالة الدفع
     */
    public function status($orderId, Request $request)
    {
        $user = $request->user();

        $order = Order::where('user_id', $user->id)
            ->where('id', $orderId)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        $payment = Payment::where('order_id', $order->id)->first();

        if (!$payment) {
            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'status' => 'not_initiated',
                    'message' => 'لم يتم بدء عملية الدفع لهذا الطلب'
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'transaction_id' => $payment->transaction_id,
                'payment_date' => $payment->payment_date
            ]
        ]);
    }
}
