<?php

// app/Http/Controllers/Api/Public/ProvinceController.php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    /**
     * عرض جميع المحافظات
     */
    public function index()
    {
        $provinces = Province::withCount('branches')->get();

        return response()->json([
            'success' => true,
            'data' => $provinces
        ]);
    }

    /**
     * عرض محافظة محددة
     */
    public function show($id)
    {
        $province = Province::with('branches.shippingCompany')->find($id);

        if (!$province) {
            return response()->json([
                'success' => false,
                'message' => 'المحافظة غير موجودة'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $province
        ]);
    }
}
