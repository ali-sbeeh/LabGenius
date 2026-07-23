<?php

// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;


// Controllers العامة

use App\Http\Controllers\Api\Public\ProductController;
use App\Http\Controllers\Api\Public\CategoryController;
use App\Http\Controllers\Api\Public\ReviewController;
use App\Http\Controllers\Api\Public\ShippingCompanyController;
use App\Http\Controllers\Api\Public\ProvinceController;
use App\Http\Controllers\Api\Public\UserProfileController;

// Controllers الخاصة بالزبون
use App\Http\Controllers\Api\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Api\Customer\CartController;
use App\Http\Controllers\Api\Customer\WishlistController;
use App\Http\Controllers\Api\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Api\Customer\PaymentController;
use App\Http\Controllers\Api\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\Api\Customer\RecommendationController as CustomerRecommendationController;
use App\Http\Controllers\Api\Customer\PriceEstimateController as CustomerPriceEstimateController;
use App\Http\Controllers\Api\Customer\NotificationController as CustomerNotificationController;
use App\Http\Controllers\Api\Customer\ChatController as CustomerChatController;

// Controllers الخاصة بالبائع
use App\Http\Controllers\Api\Seller\ProfileController as SellerProfileController;
use App\Http\Controllers\Api\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Api\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Api\Seller\DiscountController;
use App\Http\Controllers\Api\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Api\Seller\FavoriteCustomerController;
use App\Http\Controllers\Api\Seller\RecommendationController as SellerRecommendationController;
use App\Http\Controllers\Api\Seller\PriceEstimateController as SellerPriceEstimateController;
use App\Http\Controllers\Api\Seller\NotificationController as SellerNotificationController;
use App\Http\Controllers\Api\Seller\ChatController as SellerChatController;

// Controllers الخاصة بالأدمن
use App\Http\Controllers\Api\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\Admin\SellerController as AdminSellerController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\FavoriteController as AdminFavoriteController;
use App\Http\Controllers\Api\Admin\LogController;
use App\Http\Controllers\Api\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\Admin\RecommendationController as AdminRecommendationController;
use App\Http\Controllers\Api\Admin\PriceEstimateController as AdminPriceEstimateController;


RateLimiter::for('public', function (Request $request) {
    return Limit::perMinute(60)->by($request->ip());
});

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});

RateLimiter::for('checkout', function (Request $request) {
    return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('admin', function (Request $request) {
    return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// =============================================
// 1. ENDPOINTS العامة (بدون مصادقة)
// =============================================

Route::prefix('v1')->group(function () {


      // =============================================
    // 1. OAUTH2 ENDPOINTS (Passport)
    // =============================================

    // OAuth2 Token endpoints (للتطبيقات الخارجية)
    Route::post('/oauth/token', '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken')
        ->middleware('throttle:auth')
        ->name('passport.token');

    // OAuth2 Authorize endpoint (لـ OAuth2 flows)
    Route::get('/oauth/authorize', function () {
        return response()->json([
            'message' => 'OAuth2 authorization endpoint. Please use OAuth2 client.',
        ]);
    })->middleware('auth:api');

    // OAuth2 Clients management
    Route::middleware(['auth:api'])->prefix('oauth')->group(function () {
        Route::get('/clients', '\Laravel\Passport\Http\Controllers\ClientController@forUser');
        Route::post('/clients', '\Laravel\Passport\Http\Controllers\ClientController@store');
        Route::put('/clients/{client_id}', '\Laravel\Passport\Http\Controllers\ClientController@update');
        Route::delete('/clients/{client_id}', '\Laravel\Passport\Http\Controllers\ClientController@destroy');

        // Personal access tokens
        Route::get('/personal-access-tokens', '\Laravel\Passport\Http\Controllers\PersonalAccessTokenController@forUser');
        Route::post('/personal-access-tokens', '\Laravel\Passport\Http\Controllers\PersonalAccessTokenController@store');
        Route::delete('/personal-access-tokens/{token_id}', '\Laravel\Passport\Http\Controllers\PersonalAccessTokenController@destroy');
    });




     // =============================================
    // 1. AUTHENTICATION ENDPOINTS (Public - No Authentication)
    // =============================================
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {

        // Registration
       Route::post('/register', [AuthController::class, 'register']);

        // Login
        Route::post('/login', [AuthController::class, 'login']);

        // Password Reset
          Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::get('/reset-password/check', [AuthController::class, 'showResetForm']);  // للتحقق من صحة الرابط
        Route::post('/reset-password/resend', [AuthController::class, 'resendResetLink']);  // إعادة إرسال الرابط

        // Email Verification (public but requires hash)
        Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

        // =============================================
        // Protected Authentication Routes (Requires Token)
        // =============================================
        Route::middleware(['auth:api'])->group(function () {

            // Logout & Session Management
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAllDevices']);

            // Token Management
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
            Route::get('/tokens', [AuthController::class, 'getTokens']);
            Route::delete('/tokens/{tokenId}', [AuthController::class, 'revokeToken']);

            // Password Management
            Route::post('/change-password', [AuthController::class, 'changePassword']);

            // Email Verification
            Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail']);
        });

    });


    // ------------------------------
    // Routes المصادقة (عامة - للجميع)
    // ------------------------------
   /* Route::prefix('auth')->group(function () {
        Route::post('/register/customer', [AuthController::class, 'customerRegister']);
        Route::post('/register/seller', [AuthController::class, 'sellerRegister']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken'])->middleware('auth:sanctum');
    });
*/
    // ------------------------------
    // Routes المنتجات (عامة)
    // ------------------------------

 Route::middleware('throttle:public')->group(function () {
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);                    // عرض جميع المنتجات
        Route::get('/search', [ProductController::class, 'search']);             // البحث عن المنتجات
        Route::get('/filter', [ProductController::class, 'filter']);             // فلترة المنتجات
        Route::get('/latest', [ProductController::class, 'latest']);             // أحدث المنتجات
        Route::get('/popular', [ProductController::class, 'popular']);           // الأكثر مبيعاً
        Route::get('/{id}', [ProductController::class, 'show']);                 // عرض منتج محدد
        Route::get('/{id}/reviews', [ProductController::class, 'reviews']);      // مراجعات منتج محدد
    });

    // ------------------------------
    // Routes الفئات (عامة)
    // ------------------------------
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);                   // عرض جميع الفئات
        Route::get('/{id}', [CategoryController::class, 'show']);                // عرض فئة محددة
        Route::get('/{id}/products', [CategoryController::class, 'products']);   // منتجات فئة محددة
    });

    // ------------------------------
    // Routes المراجعات (عامة)
    // ------------------------------
    Route::prefix('reviews')->group(function () {
        Route::get('/product/{product_id}', [ReviewController::class, 'productReviews']);
        Route::get('/product/{product_id}/ratings-summary', [ReviewController::class, 'ratingsSummary']);
    });

    // ------------------------------
    // Routes شركات الشحن والمحافظات (عامة)
    // ------------------------------
    Route::prefix('shipping')->group(function () {
        Route::get('/companies', [ShippingCompanyController::class, 'companies']);
        Route::get('/companies/{id}', [ShippingCompanyController::class, 'companyDetails']);
        Route::get('/companies/{id}/branches', [ShippingCompanyController::class, 'companyBranches']);
    });

    Route::prefix('provinces')->group(function () {
        Route::get('/', [ProvinceController::class, 'index']);
        Route::get('/{id}', [ProvinceController::class, 'show']);
    });

    // ✅ Public User Profiles (no auth required)
    Route::get('/users/{id}/profile', [UserProfileController::class, 'show']);
 });

    /////////////////////////// here we begin the endpoints that related with customer ///////////
// Route::prefix('customer')->middleware(['auth:sanctum', 'role:customer'])->group(function () {

    Route::prefix('customer')->middleware(['auth:api', 'scope:customer', 'throttle:api'])->group(function () {

    // Profile Management
    Route::get('/profile', [CustomerProfileController::class, 'show']);
    Route::put('/profile', [CustomerProfileController::class, 'updateProfile']);

    // Cart Management
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'add']);
     Route::delete('/cart/clear', [CartController::class, 'clear']);
    Route::put('/cart/{item_id}', [CartController::class, 'update']);
    Route::delete('/cart/{item_id}', [CartController::class, 'remove']);

    // Wishlist Management
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{product_id}', [WishlistController::class, 'add']);
    Route::delete('/wishlist/{product_id}', [WishlistController::class, 'remove']);

    // Orders Management
    Route::get('/orders', [CustomerOrderController::class, 'index']);
    Route::get('/orders/{id}', [CustomerOrderController::class, 'show']);
    Route::post('/orders', [CustomerOrderController::class, 'store']);
    Route::put('/orders/{id}/confirm', [CustomerOrderController::class, 'confirm']);
    Route::put('/orders/{id}/cancel', [CustomerOrderController::class, 'cancel']);

    // Payment Management
    Route::post('/payment/process', [PaymentController::class, 'process']);
    Route::get('/payment/status/{order_id}', [PaymentController::class, 'status']);

    // Reviews Management
    Route::post('/reviews', [CustomerReviewController::class, 'store']);
    Route::put('/reviews/{id}', [CustomerReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [CustomerReviewController::class, 'destroy']);

    // Recommendation System
    //Route::get('/recommendations', [CustomerRecommendationController::class, 'index']);

    // Price Estimate System
    //Route::get('/price-estimate/{product_id}', [CustomerPriceEstimateController::class, 'estimate']);

    // Notifications
    Route::get('/notifications', [CustomerNotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [CustomerNotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [CustomerNotificationController::class, 'markAllAsRead']);

    // Chat
    Route::prefix('chat')->group(function () {
        Route::get('/sellers', [CustomerChatController::class, 'sellers']);
        Route::get('/conversations', [CustomerChatController::class, 'conversations']);
        Route::post('/conversations', [CustomerChatController::class, 'startConversation']);
        Route::get('/conversations/{id}', [CustomerChatController::class, 'show']);
        Route::post('/conversations/{id}/messages', [CustomerChatController::class, 'sendMessage']);
    });
});

////////////////////// here we begin the routes that are related to seller /////////////////

// Seller Routes (requires auth + role:seller)
Route::prefix('seller')->middleware(['auth:api', 'scope:seller', 'throttle:api'])->group(function () {

    // Profile Management
    Route::get('/profile', [SellerProfileController::class, 'show']);
    Route::put('/profile', [SellerProfileController::class, 'update']);
    Route::put('/change-password', [SellerProfileController::class, 'changePassword']);

    // Dashboard & Statistics
    Route::get('/dashboard/stats', [SellerDashboardController::class, 'stats']);
    Route::get('/orders/stats', [SellerOrderController::class, 'orderStats']);

    // Product Management
    Route::get('/products', [SellerProductController::class, 'index']);
    Route::post('/products', [SellerProductController::class, 'store']);
    Route::get('/products/{id}', [SellerProductController::class, 'show']);
    Route::put('/products/{id}', [SellerProductController::class, 'update']);
    Route::delete('/products/{id}', [SellerProductController::class, 'destroy']);
    Route::put('/products/{id}/toggle-active', [SellerProductController::class, 'toggleActive']);

    // Product Images Management
    Route::post('/products/{id}/images', [SellerProductController::class, 'addImages']);
    Route::delete('/products/{productId}/images/{imageId}', [SellerProductController::class, 'deleteImage']);
    Route::put('/products/{productId}/images/{imageId}/primary', [SellerProductController::class, 'setPrimaryImage']);

    // Discount Management
    Route::get('/discounts', [DiscountController::class, 'index']);
    Route::post('/discounts', [DiscountController::class, 'store']);
    Route::put('/discounts/{id}', [DiscountController::class, 'update']);
    Route::delete('/discounts/{id}', [DiscountController::class, 'destroy']);
    Route::put('/discounts/{id}/toggle-active', [DiscountController::class, 'toggleActive']);

    // Orders Monitoring
    Route::get('/orders', [SellerOrderController::class, 'index']);
    Route::get('/orders/{id}', [SellerOrderController::class, 'show']);
    Route::put('/orders/{id}/accept', [SellerOrderController::class, 'accept']);   // ✅ قبول الطلب
    Route::put('/orders/{id}/reject', [SellerOrderController::class, 'reject']);   // ✅ رفض الطلب
    Route::put('/orders/{id}/ship', [SellerOrderController::class, 'ship']);
    Route::get('/orders/status/{status}', [SellerOrderController::class, 'filterByStatus']);

    // Favorite Customers
    Route::get('/favorite-customers', [FavoriteCustomerController::class, 'index']);
    Route::post('/favorite-customers/{customer_id}', [FavoriteCustomerController::class, 'add']);
    Route::delete('/favorite-customers/{customer_id}', [FavoriteCustomerController::class, 'remove']);

    // Recommendation System
    Route::get('/recommendations', [SellerRecommendationController::class, 'index']);

    // Price Estimate System
    Route::get('/price-estimate/{product_id}', [SellerPriceEstimateController::class, 'estimate']);

    // Notifications
    Route::get('/notifications', [SellerNotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [SellerNotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [SellerNotificationController::class, 'markAllAsRead']);

    // Chat
    Route::prefix('chat')->group(function () {
        Route::get('/conversations', [SellerChatController::class, 'conversations']);
        Route::get('/conversations/{id}', [SellerChatController::class, 'show']);
        Route::post('/conversations/{id}/messages', [SellerChatController::class, 'sendMessage']);
    });
});


////////////// here admin endpoints begin ///////////////////////////////////////////

// Admin Routes (requires auth + role:admin)
Route::prefix('admin')->middleware(['auth:api', 'scope:admin', 'throttle:admin'])->group(function () {

    // Profile Management
    Route::get('/profile', [AdminProfileController::class, 'show']);
    Route::put('/profile', [AdminProfileController::class, 'update']);
    Route::put('/change-password', [AdminProfileController::class, 'changePassword']);

    // Dashboard & Analytics
    Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);
    Route::get('/dashboard/quick-stats', [AdminDashboardController::class, 'quickStats']);

    // Product Management (Admin)
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/products/{id}', [AdminProductController::class, 'show']);
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);
    Route::put('/products/{id}/toggle-active', [AdminProductController::class, 'toggleActive']);
    Route::put('/products/{id}/stock', [AdminProductController::class, 'updateStock']);

    // Category Management
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::get('/categories/{id}', [AdminCategoryController::class, 'show']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

    // Customer Management
    Route::get('/customers', [AdminCustomerController::class, 'index']);
    Route::get('/customers/{id}', [AdminCustomerController::class, 'show']);
    Route::post('/customers', [AdminCustomerController::class, 'store']);
    Route::put('/customers/{id}', [AdminCustomerController::class, 'update']);
    Route::delete('/customers/{id}', [AdminCustomerController::class, 'destroy']);
    Route::put('/customers/{id}/toggle-block', [AdminCustomerController::class, 'toggleBlock']);

    // Seller Management
    Route::get('/sellers', [AdminSellerController::class, 'index']);
    Route::get('/sellers/{id}', [AdminSellerController::class, 'show']);
    Route::post('/sellers', [AdminSellerController::class, 'store']);
    Route::put('/sellers/{id}', [AdminSellerController::class, 'update']);
    Route::delete('/sellers/{id}', [AdminSellerController::class, 'destroy']);
    Route::put('/sellers/{id}/toggle-active', [AdminSellerController::class, 'toggleActive']);

    // Order Management
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::put('/orders/{id}/receive', [AdminOrderController::class, 'receive']);
    Route::put('/orders/{id}/ship', [AdminOrderController::class, 'ship']);

    // Favorites Management
    Route::get('/favorites', [AdminFavoriteController::class, 'index']);
    Route::post('/favorites/customer/{id}', [AdminFavoriteController::class, 'addCustomer']);
    Route::post('/favorites/seller/{id}', [AdminFavoriteController::class, 'addSeller']);
    Route::delete('/favorites/{type}/{id}', [AdminFavoriteController::class, 'remove']);

    // System Logs
    Route::get('/logs', [LogController::class, 'index']);
    Route::get('/logs/actions', [LogController::class, 'actions']);
    Route::get('/logs/filter/{type}', [LogController::class, 'filterByType']);

    // Notifications
    Route::get('/notifications', [AdminNotificationController::class, 'index']);
    Route::post('/notifications/send', [AdminNotificationController::class, 'send']);
    Route::put('/notifications/{id}/read', [AdminNotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [AdminNotificationController::class, 'markAllAsRead']);

    // Recommendations & Analytics
    Route::get('/recommendations', [AdminRecommendationController::class, 'index']);
    Route::get('/price-estimate/{product_id}', [AdminPriceEstimateController::class, 'estimate']);
    Route::get('/price-estimate/category/{category_id}', [AdminPriceEstimateController::class, 'categoryAnalysis']);

    // Shipping Management


    // Provinces Management

});


}); // end of v1 prefix

