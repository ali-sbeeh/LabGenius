<?php

// app/Http/Controllers/Api/Admin/LogController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * عرض سجلات النظام
     */
    public function index(Request $request)
    {
        $query = AdminLog::with('admin')
            ->orderBy('created_at', 'desc');

        // فلترة حسب نوع العملية
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // فلترة حسب المستخدم الذي قام بالعملية
        if ($request->has('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }

        // فلترة حسب التاريخ
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $perPage = $request->get('per_page', 30);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * عرض أنواع العمليات المتاحة
     */
    public function actions()
    {
        $actions = [
            'create_customer', 'update_customer', 'delete_customer', 'block_customer', 'unblock_customer',
            'create_seller', 'update_seller', 'delete_seller', 'activate_seller', 'deactivate_seller',
            'delete_product', 'activate_product', 'deactivate_product', 'update_stock',
            'create_category', 'update_category', 'delete_category',
            'update_order_status', 'receive_order', 'ship_order',
            'send_notification'
        ];

        return response()->json([
            'success' => true,
            'data' => $actions
        ]);
    }

    /**
     * فلترة السجلات حسب النوع
     */
    public function filterByType($type, Request $request)
    {
        $query = AdminLog::with('admin')
            ->where('action', 'LIKE', "%{$type}%")
            ->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 30);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
            'filter_type' => $type
        ]);
    }
}
