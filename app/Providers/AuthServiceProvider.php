<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        //
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // في الإصدارات الجديدة من Passport، الـ routes تُضاف تلقائياً
        // بس نحتاج نحدد التوكن types
        Passport::tokensCan([
            'customer' => 'Customer access',
            'seller' => 'Seller access',
            'admin' => 'Admin access',
        ]);

        // مدة صلاحية التوكن
        Passport::tokensExpireIn(now()->addDays(30));
        Passport::refreshTokensExpireIn(now()->addDays(60));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
    }
}
