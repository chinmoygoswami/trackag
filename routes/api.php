<?php
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\ApiTripController;
use App\Http\Controllers\Api\BudgetApiController;
use App\Http\Controllers\Api\CommanController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FailedJobController;
use App\Http\Controllers\Api\FarmerController;
use App\Http\Controllers\Api\LocationApiController;
use App\Http\Controllers\Api\PartyController;
use App\Http\Controllers\Api\PartyPaymentController;
use App\Http\Controllers\Api\FarmVisitController;
use App\Http\Controllers\Api\MonthlyPlanApiController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TallyAuthController;
use App\Http\Controllers\Api\TallyController;
use App\Http\Middleware\EnsureTallyAccessToken;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\TenantAuthenticate;

// Existing Auth API routes
Route::post('/login', [ApiAuthController::class, 'login']);
Route::post('/login_new', [ApiAuthController::class, 'login_new']);
Route::get('locations', [LocationApiController::class, 'index']);
Route::post('/failedJobs', [FailedJobController::class, 'store']);
Route::get('/apk-list', [ApiAuthController::class, 'getApklist']);

Route::prefix('tally')->group(function () {
    Route::post('/login', [TallyAuthController::class, 'login']);

    Route::middleware(EnsureTallyAccessToken::class)->group(function () {
        Route::get('/party-sync', [TallyController::class, 'partySync']);
        Route::post('/party-sync', [TallyController::class, 'partySync']);
        Route::get('/sales-bill', [TallyController::class, 'salesBill']);
        Route::post('/sales-bill', [TallyController::class, 'salesBill']);
        Route::get('/opening-closing', [TallyController::class, 'openingClosing']);
        Route::post('/opening-closing', [TallyController::class, 'openingClosing']);
        Route::post('/partywise-payment-credit', [TallyController::class, 'partywisePaymentCredit']);
    });
});

Route::middleware([TenantAuthenticate::class])->group(function () {
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::post('/profile', [ApiAuthController::class, 'profile']);
    Route::post('/change-password', [ApiAuthController::class, 'changePassword']);

    Route::get('/trip/customers', [ApiTripController::class, 'fetchCustomer']);
    Route::get('/tourDetails', [ApiTripController::class, 'getTourDetails']);
    Route::get('/trips', [ApiTripController::class, 'index']);
    Route::post('/trips/store', [ApiTripController::class, 'storeTrip']);
    Route::post('/trips/log-point', [ApiTripController::class, 'logPoint']);
    Route::get('/trips/{tripId}/logs', [ApiTripController::class, 'logs']);
    Route::get('/trips/{tripId}/view-log', [ApiTripController::class, 'viewLog']);
    Route::get('/trips/{tripId}/view-map', [ApiTripController::class, 'viewMap']);
    Route::get('/trips/{tripId}/view-map-webview', [ApiTripController::class, 'viewMapWebview']);
    Route::post('/trips/{tripId}/complete', [ApiTripController::class, 'completeTrip']);
    Route::get('/trip/active', [ApiTripController::class, 'lastActive']);
    Route::get('/trip/{tripId}/detail', [ApiTripController::class, 'showTrip']);
    Route::post('/trip/close', [ApiTripController::class, 'close']);
    Route::post('/trip/gps-store', [ApiTripController::class, 'gpsStore']);
    Route::get('/trip/my-trips', [ApiTripController::class, 'getMyTrips']);
    //expense api
    Route::get('/get-expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses-store', [ExpenseController::class, 'storeOrUpdate']);
    Route::delete('/expenses-delete/{id}', [ExpenseController::class, 'destroy']);
    Route::get('/ta-da-report', [ExpenseController::class, 'taDaReport']);

    //party vist api
    Route::get('/get-party-visits', [PartyController::class, 'index']);
    Route::post('/party-visits-store', [PartyController::class, 'partyVisitsStore']);
    Route::post('/party-visit-checkout', [PartyController::class, 'partyVisitCheckout']);

    //new party api
    Route::get('/party-list', [PartyController::class, 'getPartyList']);
    Route::post('/new-party-store', [PartyController::class, 'newPartyStore']);

    Route::get('/states', [LocationApiController::class, 'getStates']);
    Route::get('/districts/{state_id}', [LocationApiController::class, 'getDistricts']);
    Route::get('/tehsils/{district_id}', [LocationApiController::class, 'getTehsils']);

    Route::get('/party-payment-list', [PartyPaymentController::class, 'index']);
    Route::post('/party-payment-store', [PartyPaymentController::class, 'store']);

    Route::get('price-list', [CommanController::class, 'priceList']);
    Route::get('brochures', [CommanController::class, 'brochures']);
    Route::get('messages', [CommanController::class, 'messages']);
    Route::get('attendance', [CommanController::class, 'myAttendance']);
    Route::get('depo-list', [CommanController::class, 'getDepoList']);

    Route::get('crop-sowing-list', [FarmerController::class, 'cropSowingList']);
    Route::get('farmer-crop-sowing/{farmer_id}', [FarmerController::class, 'farmerCropSowing']);
    Route::get('farmers-list', [FarmerController::class, 'index']);
    Route::post('farmers-store', [FarmerController::class, 'store']);
    Route::post('farmers-update/{id}', [FarmerController::class, 'update']);

    Route::get('farm-visits', [FarmVisitController::class, 'index']);
    Route::post('farm-visits-store', [FarmVisitController::class, 'store']);
    Route::post('farm-visits-update/{id}', [FarmVisitController::class, 'update']);

    Route::get('product-list', [OrderController::class, 'getProductList']);
    Route::get('product-packings', [OrderController::class, 'getProductPackings']);
    Route::get('packing-details', [OrderController::class, 'getPackingDetails']);

    Route::get('orders-list', [OrderController::class, 'index']);
    Route::post('orders-store', [OrderController::class, 'store']);
    Route::post('orders-update/{id}', [OrderController::class, 'update']);
    Route::delete('orders-delete/{id}', [OrderController::class, 'destroy']);
    Route::get('order-details/{id}', [OrderController::class, 'orderDetails']);


    Route::get('/stock/packing-list', [StockController::class, 'getProductPackingList']);
    Route::get('/stock/list', [StockController::class, 'getStockList']);
    Route::post('/stock/bulk-store', [StockController::class, 'bulkStoreStock']);
    Route::post('/stock/bulk-update', [StockController::class, 'bulkUpdateStock']);

    Route::get('/monthly-plan/packing-list', [MonthlyPlanApiController::class, 'getProductPackingList']);
    Route::get('/monthly-plan/list', [MonthlyPlanApiController::class, 'getPlanList']);
    Route::post('/monthly-plan/bulk-store', [MonthlyPlanApiController::class, 'bulkStorePlan']);

    Route::get('/budget/annual', [BudgetApiController::class, 'annualBudget']);
    Route::post('/update-fcm-token', [ApiAuthController::class, 'updateToken']);
});
