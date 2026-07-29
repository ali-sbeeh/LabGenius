<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Product::observe(ProductObserver::class);

        // الـ Rate Limiter الخاص بالـ API
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // ========== التعديل الجديد لحل مشكلة الـ Auth ==========
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
        // ====================================================

        // ========== التعديل السحري والأخير لتسجيل محرك Brevo ==========
        Mail::extend('brevo', function (array $config) {
            return app('brevosuite.manager')->transport();
        });
        // ============================================================
    }
}
