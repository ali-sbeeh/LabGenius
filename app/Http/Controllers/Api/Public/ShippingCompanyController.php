<?php

// app/Http/Controllers/Api/Public/ShippingController.php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\ShippingCompany;
use App\Models\CompanyBranch;
use Illuminate\Http\Request;

class ShippingCompanyController extends Controller
{
    /**
     * عرض جميع شركات الشحن
     */
    public function companies()
    {
        $companies = ShippingCompany::withCount('branches')->get();

        return response()->json([
            'success' => true,
            'data' => $companies
        ]);
    }

    /**
     * عرض تفاصيل شركة شحن محددة
     */
    public function companyDetails($id)
    {
        $company = ShippingCompany::with('branches.province')->find($id);

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'شركة الشحن غير موجودة'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $company
        ]);
    }

    /**
     * عرض فروع شركة شحن محددة
     */
    public function companyBranches($id)
    {
        $company = ShippingCompany::find($id);

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'شركة الشحن غير موجودة'
            ], 404);
        }

        $branches = CompanyBranch::with('province')
            ->where('shipping_company_id', $id)
            ->get();

        return response()->json([
            'success' => true,
            'company' => $company,
            'data' => $branches
        ]);
    }
}
