<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\SalesOverviewController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\PromoCampaignController;
use App\Http\Controllers\BusinessDiscoveryController;
use App\Http\Controllers\AdminPayoutController;




/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => 'cors'], function () {
    Route::middleware('auth:api')->get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/wallet', [WalletController::class, 'index']);
        Route::post('/wallet/deposit', [WalletController::class, 'deposit']);
        Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
    });

    Route::get('/shops/{shop}/ratings', [RatingController::class, 'index']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/orders/{order}/rate', [RatingController::class, 'store']);
        Route::get('/ratings/{rating}', [RatingController::class, 'show']);
        Route::put('/ratings/{rating}', [RatingController::class, 'update']);
    });

    // Route::get('/wallet/callback', [WalletController::class, 'handlePaymentCallback']);

    Route::middleware('auth:sanctum')->post('/change-password', [UserController::class, 'changePassword']);

    Route::post('/change-password-from-token', [UserController::class, 'changePasswordFromToken']);

    // Email Notification Routes
    Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
    Route::get('/notification/send-signup-email/{userId}', [EmailController::class, 'sendSignupEmail']);
    Route::get('/notification/send-signin-email/{userId}', [EmailController::class, 'sendSigninEmail']);
    Route::post('/notification/send-transaction-email/{userId}', [EmailController::class, 'sendTransactionEmail']);
    Route::get('/notification/send-verification-email/{userId}', [EmailController::class, 'sendVerificationEmail']);
    Route::get('/notification/send-password-reset-email/{email}', [EmailController::class, 'sendPasswordResetEmail']);
    Route::get('/notification/send-order-rave-subscription/{email}', [EmailController::class, 'sendOrderRaveEmail']);
    Route::post('/notification/send-order-rave-email-update', [EmailController::class, 'sendOrderRaveEmailUpdate']);
    

    Route::post('order', [OrderController::class, 'createOrder'])->middleware('guest');

    Route::post('order-v2', [OrderController::class, 'createOrderV2'])->middleware('guest');

    Route::post('/order-qr', [OrderController::class, 'submitOrder']);

    Route::middleware('auth:sanctum', 'role:admin')->group(function () {
        Route::post('category', [MenuController::class, 'createCategory']);
        Route::post('menu-item', [MenuController::class, 'createMenuItem']);
    });

    // subscription
    Route::apiResource('subscriptions', SubscriptionController::class);

    Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
        Route::get('failed-payouts', [AdminPayoutController::class, 'index']);
        Route::post('failed-payouts/{id}/retry', [AdminPayoutController::class, 'retry']);
        Route::get('payment-history', [AdminPayoutController::class, 'paymentHistory']);
    });

    // product
    Route::prefix('products')->middleware(['auth:sanctum', 'role:admin', 'shop.active'])->group(function () {
    // Route::prefix('products')->group(function () {
        Route::get('/', [MenuController::class, 'listMenu']);
        Route::get('/view/{id}', [MenuController::class, 'showMenu']);
        Route::post('/update/{id}', [MenuController::class, 'updateMenuItem']);
        Route::delete('/delete/{id}', [MenuController::class, 'deleteMenu']);
        Route::post('/create', [MenuController::class, 'createMenuItem']);
        Route::get('/performance', [ProductController::class, 'index']);

        Route::post('/update-status', [MenuController::class, 'updateStatus']);

        Route::get('/categories', [MenuController::class, 'listCategories']);
        Route::put('/categories/update/{id}', [MenuController::class, 'updateCategory']);
        Route::delete('/categories/delete/{id}', [MenuController::class, 'deleteCategory']);
        Route::post('/categories/create', [MenuController::class, 'createCategory']);
    });

    Route::prefix('store')->middleware('auth:sanctum', 'role:admin')->group(function () {
        Route::get('/details', [ShopController::class, 'getShop']);
        Route::post('/update/{id}', [ShopController::class, 'update']);
        Route::post('/update-status', [ShopController::class, 'updateStatus']);
        Route::post('/activate-free-trial', [ShopController::class, 'activateFreeTrial']);
        Route::post('/deactivate-free-trial', [ShopController::class, 'deactivateFreeTrial']);
        Route::post('/activate-free-account', [ShopController::class, 'activateFreeSubscription']);
        // update styling
        Route::post('/update-styling', [ShopController::class, 'updateStyling']);
    });

    Route::prefix('sales-overview')->middleware(['auth:sanctum', 'shop.active']) ->group(function () {
        Route::get('/months', [SalesOverviewController::class, 'getSalesOverview'])->name('sales-overview.months');
        Route::get('/yearly-breakup', [SalesOverviewController::class, 'getYearlyBreakup']);
        Route::get('/monthly-earnings', [SalesOverviewController::class, 'getMonthlyEarnings']);
        Route::get('/', [SalesOverviewController::class, 'getSalesOverview'])->name('sales-overview.data');
    });

    Route::prefix('transactions')->middleware('auth:sanctum', 'shop.active')->group(function () {
        Route::get('/recent', [TransactionController::class, 'getRecentTransactions']);
    });

    Route::prefix('orders')->middleware('auth:sanctum')->group(function () {
        Route::get('/userOrders/{userId}', [OrderController::class, 'getUserOrders']);
    });

    Route::get('/orders/tracking/{trackingId}', [OrderController::class, 'getOrderByTrackingId']);

    Route::get('/orders/order/{orderId}', [OrderController::class, 'getOrderById']);

    Route::prefix('orders')->middleware('auth:sanctum', 'role:admin', 'shop.active')->group(function () {
        Route::get('/', [OrderController::class, 'getAllOrders']);
        Route::get('/home', [OrderController::class, 'getHomeOrders']);
        Route::get('/table', [OrderController::class, 'getTableOrders']);
        Route::get('/pending', [OrderController::class, 'getPendingOrders']);

        // confirm order
        Route::put('/update-status/{id}/{status}', [OrderController::class, 'updateOrderStatus']);

    });

    Route::prefix('auth')->group(function () {
        Route::post('register', [UserController::class, 'register']);
        Route::post('register-shop', [UserController::class, 'registerShop']);
        Route::post('/onboarding/register-shop', [ShopController::class, 'registerShopWithOnboarding']);
        Route::post('login', [UserController::class, 'login']);
    });

    Route::prefix('campaigns')->middleware('auth:sanctum', 'role:admin', 'shop.active')->group(function () {
        // get campaigns
        Route::get('/', [PromoCampaignController::class, 'index']);
        // create campaign
        Route::post('/create', [PromoCampaignController::class, 'store']);
        // update campaign
        Route::put('/update/{id}', [PromoCampaignController::class, 'update']);
        // delete campaign
        Route::delete('/delete/{promoCampaign}', [PromoCampaignController::class, 'destroy']);
        // get single campaign
        Route::get('/campaign/{id}', [PromoCampaignController::class, 'show']);
    });

    Route::post('/promo-code/validate', [PromoCampaignController::class, 'isValidPromoCode']);

    Route::post('/referrer/register', [ReferralController::class, 'registerReferrer']);
    Route::get('/referrals', [ReferralController::class, 'getReferrals']);
    Route::get('/referral/check/{code}', [ReferralController::class, 'checkReferralCode']);
    Route::get('/user-referrals/{phone}', [ReferralController::class, 'getUserReferralsByPhone']);
    Route::get('/referrals/{id}', [ReferralController::class, 'getUserRefs']);
    Route::get('/referrer/check/{phone}', [ReferralController::class, 'checkReferralExists']);


    Route::group(['middleware' => ['cors']], function() {
        Route::get('/stores', [ShopController::class, 'index']);
    });

    Route::post('/stores/{store}/menu-views', [ShopController::class, 'trackMenuView'])
    ->middleware(['throttle:60,1']); 

    Route::get('/analytics/shop/{shop}', [AnalyticsController::class, 'getShopAnalytics']);

    Route::get('/market-stores', [ShopController::class, 'markets']);

    Route::get('/all-stores', [ShopController::class, 'allStores']);

    Route::get('/stores/{slug}', [ShopController::class, 'show']);

    Route::middleware('auth:sanctum')->get('/menu/search', [MenuController::class, 'searchMenu']);

    Route::middleware('auth:sanctum')->get('/menu/categories', [MenuController::class, 'getCategoriesWithMenuItems']);

    Route::get('/recommendations/time-based', [RecommendationController::class, 'getTimeBasedRecommendations']);

});

Route::prefix('discover')->group(function () {
    Route::get('/search', [BusinessDiscoveryController::class, 'search']);

    Route::get('/business/{placeId}', [BusinessDiscoveryController::class, 'getDetails']);

    Route::post('/claim-business', [BusinessDiscoveryController::class, 'claimBusiness']);

    Route::post('/refer-business', [BusinessDiscoveryController::class, 'referBusiness']);

});

Route::post('/business-claims/{claimId}/approve', [BusinessDiscoveryController::class, 'approveClaim']);

Route::post('/webhooks/paystack', [\App\Http\Controllers\PaystackWebhookController::class, 'handle']);

// Route::get('/paystack/payment-callback', [OrderController::class, 'handlePaymentCallback'])->name('paystack.commission.callback');
