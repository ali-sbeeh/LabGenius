<?php

// app/Http/Controllers/Api/Admin/NotificationController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * عرض جميع إشعارات الأدمن
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * إرسال إشعار للمستخدمين
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'user_type' => 'required|in:all,customers,sellers,admin,specific',
            'user_ids' => 'required_if:user_type,specific|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في بيانات الإدخال',
                'errors' => $validator->errors()
            ], 422);
        }

        // تحديد المستخدمين المستهدفين
        $users = collect();

        switch ($request->user_type) {
            case 'all':
                $users = User::all();
                break;
            case 'customers':
                $users = User::where('role', 'customer')->get();
                break;
            case 'sellers':
                $users = User::where('role', 'seller')->get();
                break;
            case 'admin':
                $users = User::where('role', 'admin')->get();
                break;
            case 'specific':
                $users = User::whereIn('id', $request->user_ids)->get();
                break;
        }

        // إرسال الإشعارات
        $sentCount = 0;
        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'message' => $request->message,
                'type' => 'admin_broadcast',
                'is_read' => false
            ]);
            $sentCount++;
        }

        // تسجيل العملية
        \App\Models\AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'send_notification',
            'target_id' => null,
            'target_type' => 'broadcast',
            'details' => "تم إرسال إشعار إلى {$sentCount} مستخدم. العنوان: {$request->title}"
        ]);

        return response()->json([
            'success' => true,
            'message' => "تم إرسال الإشعار بنجاح إلى {$sentCount} مستخدم",
            'data' => [
                'sent_to' => $sentCount,
                'user_type' => $request->user_type
            ]
        ]);
    }

    /**
     * تعيين إشعار كمقروء
     */
    public function markAsRead($id, Request $request)
    {
        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'الإشعار غير موجود'
            ], 404);
        }

        $notification->is_read = true;
        $notification->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تعيين الإشعار كمقروء'
        ]);
    }

    /**
     * تعيين جميع الإشعارات كمقروءة
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'تم تعيين جميع الإشعارات كمقروءة'
        ]);
    }
}
