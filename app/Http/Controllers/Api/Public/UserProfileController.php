<?php

// app/Http/Controllers/Api/Public/UserProfileController.php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;

class UserProfileController extends Controller
{
  
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        // بيانات عامة فقط (بدون كلمة المرور أو بيانات حساسة)
        $profile = [
            'id'       => $user->id,
            'name'     => $user->full_name,
            'role'     => $user->role,
            'joined'   => $user->created_at?->toDateString(),
            'image'    => $user->image,
            'phone'    => $user->phone,
            'email'    => $user->email,
        ];

        // معلومات إضافية للبائع
        if ($user->role === 'seller') {
            $profile['total_products'] = $user->products()->where('is_active', true)->count();
            $profile['rating'] = round(
                $user->products()
                    ->with('reviews')
                    ->get()
                    ->flatMap(fn($p) => $p->reviews)
                    ->avg('rating') ?? 0,
                1
            );
        }

        return response()->json([
            'success' => true,
            'data'    => $profile
        ]);
    }
}
