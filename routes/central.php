<?php

use App\Http\Controllers\ApkUploadController;
use App\Http\Controllers\BrochureController;
use App\Http\Controllers\DepoController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TaDaBillMasterController;
use App\Http\Controllers\TaDaSlabController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleTypeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\TehsilController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\TravelModeController;
use App\Http\Controllers\PurposeController;
use App\Http\Controllers\TourTypeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\MonthlyController;
use App\Http\Controllers\AchievementController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\CropCategoryController;
use App\Http\Controllers\CropSubCategoryController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PartyPaymentController;
use App\Http\Controllers\ProductCategoryController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Barryvdh\DomPDF\Facade\Pdf;


Route::get('/logs', function () {
    $accessKey = request('key'); 
    if ($accessKey !== '1234') {
        abort(403, 'Unauthorized access.');
    }

    $path = storage_path('logs/laravel.log');

    if (!File::exists($path)) {
        return "No log file found.";
    }

    $logs = File::get($path);

    return response("<pre style='background:#000;color:#0f0;padding:15px;font-size:13px;'>"
        . e($logs) . "</pre>");
});

Route::middleware(['web'])->group(function () {
    Route::get('/',function(){
        return view('frontend.home');
    });
    Route::get('/privacy-policy', function () {
        return view('frontend.privacy-policy');
    })->name('privacy-policy');
    Route::get('/contact-us', function () {
        return view('frontend.contact-us');
    })->name('contact-us');
    Route::get('/login', function () {
        return redirect()->route('admin.login');
    });
    Route::get('/sample-download', function () {
        return redirect('https://testing.trackag.in/sample-files/customers_sample_new.xlsx');
    })->name('customers.sample-download');


    // Admin (central) routes
    Route::prefix('admin')->group(function () {
        Route::get('coming-soon', function () {
            return view('coming-soon');
        })->name('coming-soon');
        // Public routes
        Route::get('login', [AdminController::class, 'create'])->name('admin.login');
        Route::post('login', [AdminController::class, 'store'])->name('auth.login.request');
        Route::get('logout', [AdminController::class, 'destroy'])->name('admin.logout');

        // Protected routes
        Route::middleware(['admin', 'last_seen'])->group(function () {
            Route::get('/upload-apk', [ApkUploadController::class, 'create'])->name('apk.create');
            Route::post('/upload-apk', [ApkUploadController::class, 'store'])->name('apk.store');
            Route::resource('users', UserController::class);
            Route::get('users-pdf', [UserController::class, 'exportPdf'])->name('users.pdf');
            Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
            Route::post('/users/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::get('get-depos', [UserController::class,'getDepos'])->name('admin.get.depos');
            Route::get('get-user-depo-access', [UserController::class, 'getUserDepoAccess']);
            Route::get('get-user-state-access', [UserController::class, 'getUserStateAccess'])->name('admin.get.user-state-access');
            Route::post('save-user-state-access', [UserController::class, 'saveUserStateAccess'])->name('admin.save.user-state-access');

            Route::post('save-depo-access', [UserController::class, 'saveDepoAccess'])->name('admin.save.depo.access');
            Route::post('save-user-slab', [UserController::class, 'saveSlab'])->name('admin.save.user.slab');
            Route::get('get-user-slab', [UserController::class, 'getUserSlab'])->name('admin.get-user-slab');

            Route::get('update-state',[AdminController::class,'updateState']);
            Route::get('update-permission',[AdminController::class,'updatePermission']);


            Route::get('dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
            Route::post('change-password', [AdminController::class, 'changePassword'])->name('change-password');
            Route::resource('companies', CompanyController::class);
            Route::patch('companies/{id}/toggle', [CompanyController::class, 'toggle'])->name('companies.toggle');
            Route::resource('states', StateController::class);
            Route::post('/states/toggle-status', [StateController::class, 'toggleStatus'])->name('states.toggle-status');
            Route::resource('districts', DistrictController::class);
            Route::resource('cities', CityController::class);
            Route::resource('tehsils', TehsilController::class);
            Route::resource('roles', RoleController::class);
            Route::post('roles/{role}/toggle-status', [RoleController::class, 'toggleStatus'])->name('roles.toggle');
            Route::resource('permissions', PermissionController::class);
            Route::resource('/hr/designations', DesignationController::class);
            Route::post('/hr/designations/toggle-status', [DesignationController::class, 'toggleStatus'])->name('designations.toggle-status');

            Route::resource('depos', DepoController::class);
            Route::post('/depos/toggle-status', [DepoController::class, 'toggleStatus'])->name('depos.toggle-status');
            Route::get('ajax/get-districts', [DepoController::class, 'getDistricts'])->name('depos.get-districts');
            Route::get('ajax/get-tehsils', [DepoController::class, 'getTehsils'])->name('depos.get-tehsils');

            Route::resource('holidays', HolidayController::class);
            Route::post('/holidays/toggle-status', [HolidayController::class, 'toggleStatus'])->name('holidays.toggle-status');

            Route::resource('leaves', LeaveController::class);
            Route::post('/leaves/toggle-status', [LeaveController::class, 'toggleStatus'])->name('leaves.toggle-status');

            Route::resource('vehicle', VehicleController::class);
            Route::post('/vehicle/toggle-status', [VehicleController::class, 'toggleStatus'])->name('vehicle.toggle-status');

            Route::get('ta-da-bill-master', [TaDaBillMasterController::class, 'index'])->name('ta-da-bill-master.index');
            Route::post('ta-da-bill-master', [TaDaBillMasterController::class, 'update'])->name('ta-da-bill-master.update');
            Route::post('/ta-da-bill-master/toggle-status', [TaDaBillMasterController::class, 'toggleStatus'])->name('ta-da-bill-master.toggle-status');

            Route::get('/hr/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::post('/hr/attendance/save', [AttendanceController::class,'save'])->name('attendance.save');
            Route::get('/hr/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
            Route::get('/get-districts/{state_id}', [UserController::class, 'getDistricts'])->name('get.districts');
            Route::get('/get-cities/{district_id}', [UserController::class, 'getCities'])->name('get.cities');
            Route::get('/get-tehsils/{city_id}', [UserController::class, 'getTehsils'])->name('get.tehsils');
            Route::get('/get-pincodes/{city_id}', [UserController::class, 'getPincodes'])->name('get.pincodes');

            Route::resource('vehicle-types', VehicleTypeController::class);
            Route::get('ta-da-slab', [TaDaSlabController::class, 'form'])->name('ta-da-slab.form');
            Route::post('ta-da-slab', [TaDaSlabController::class, 'save'])->name('ta-da-slab.save');

            Route::resource('travelmode', TravelModeController::class)->names('travelmode');
            Route::resource('tourtype', TourTypeController::class)->names('tourtype');
            Route::resource('purpose', PurposeController::class)->names('purpose');
            Route::resource('trips', TripController::class)->names('trips');
            Route::get('trips/{tripId}/logs', [TripController::class, 'getLogs'])->name('trips.logs');
            
            Route::put('trips/{trip}/update-km', [TripController::class, 'updateKm'])->name('trips.updateKm');
            Route::post('/trips/{trip}/approve', [TripController::class, 'approve'])->name('trips.approve');
            Route::post('/admin/trips/{id}/complete', [TripController::class, 'completeTrip'])->name('trips.complete');
            Route::post('/trips/{trip}/toggle-status', [TripController::class, 'toggleStatus'])->name('trips.status.toggle');

            Route::get('budget/logs', [BudgetController::class, 'getLogs'])->name('budget.logs');
            Route::get('budget-report', [BudgetController::class, 'report'])->name('budget.report');
            Route::resource('budget', BudgetController::class);
            Route::resource('monthly', MonthlyController::class);
            Route::get('monthly-state-employees', [MonthlyController::class, 'getEmployeesByState'])->name('monthly.state.employees');
            Route::resource('achievement', AchievementController::class);
            Route::resource('party', PartyController::class);
            Route::get('party-visit-report', [PartyController::class, 'partyVisitReport'])->name('party-visit-report');
            Route::get('party-visit-report-data', [PartyController::class, 'getPartyVisitReportData'])->name('party-visit-report.data');
            Route::get('party-visit-report-details', [PartyController::class, 'getPartyVisitDetails'])->name('party-visit-report.details');
            
            Route::get('/admin/get-employees-by-state', [PartyController::class, 'getEmployeesByState'])->name('admin.getEmployeesByState');
            Route::get('/admin/get-party-visits', [PartyController::class, 'getPartyVisits'])->name('admin.get-party-visits');
            Route::get('new-party', [PartyController::class, 'newPartyList'])->name('new-party.list');
            Route::post('new-party/update', [PartyController::class, 'updateParty'])->name('new-party.update');
            Route::delete('new-party/delete/{id}', [PartyController::class, 'deleteParty'])->name('new-party.delete');
            Route::get('new-party/pdf', [PartyController::class, 'newPartyPdf'])->name('new-party.pdf');
            Route::post('new-party/status-update', [PartyController::class, 'updateStatus'])->name('new-party.update-status');

            Route::get('party-payment', [PartyPaymentController::class, 'index'])->name('party-payment');
            Route::post('/party-payment/clear-return', [PartyPaymentController::class, 'clearReturn'])->name('party-payment.clear-return');
            
            Route::view('party-sync', 'admin.party.party_sync')->name('party.sync');
            Route::post('party-sync/assign', [CustomerController::class, 'assignParty'])->name('party.sync.assign');
            Route::view('party-opening-closing', 'admin.party.opening_closing')->name('party.opening.closing');
            Route::view('partywise-payment-credit', 'admin.party.partywise_payment_credit')->name('partywise.payment.credit');
            
            Route::post('order-status-update', [OrderController::class, 'updateStatus'])->name('order.status.update');
            Route::post('order/item/update', [OrderController::class, 'updateItem'])->name('order.item.update');
            Route::post('order/update-items-qty', [OrderController::class, 'updateOrderItemsQty'])->name('order.items.update_qty');
            Route::get('order/{order}/dispatch-data', [OrderController::class, 'getDispatchData'])->name('order.dispatch.data');
            Route::post('order/dispatch/store', [OrderController::class, 'storeDispatch'])->name('order.dispatch.store');
            Route::get('order-report', [OrderController::class, 'report'])->name('order.report');
            Route::resource('order', OrderController::class);
            Route::view('sales-bill-register', 'admin.order.sales_bill_register')->name('sales.bill.register');
            Route::resource('stock', StockController::class);

            Route::get('tracking/live-data', [TrackingController::class, 'liveData'])->name('tracking.liveData');
            Route::resource('tracking', TrackingController::class);
            

            
            Route::patch('expense/{id}/approve', [ExpenseController::class, 'approve'])->name('expense.approve');
            Route::patch('expense/{id}/reject', [ExpenseController::class, 'reject'])->name('expense.reject');
            Route::get('expense-report', [ExpenseController::class, 'expenseReport'])->name('expense.report');
            Route::get('/expense-pdf-list', [ExpenseController::class, 'expensePdfList'])->name('expense.pdf.list');
            Route::post('expense/bulk-approve', [ExpenseController::class, 'bulkApprove'])->name('expense.bulk.approve');
            Route::get('/expense-report/pdf', [ExpenseController::class, 'exportPDF'])->name('expense.report.pdf');
            Route::get('/expense-report/excel', [ExpenseController::class, 'exportExcel'])->name('expense.report.excel');
            Route::get('/expense/state-wise-report', [ExpenseController::class, 'stateWiseReport'])->name('expense.state-wise-report');
            Route::resource('expense', ExpenseController::class)->except(['show'])->where(['expense' => '[0-9]+']);

            Route::resource('brochure', BrochureController::class);
            Route::resource('price', PriceController::class);

            Route::resource('product-categories', ProductCategoryController::class);
            Route::resource('products', ProductController::class);
            Route::get('product-price-list', [ProductController::class, 'priceList'])->name('products.price.list');
            Route::post('product-price-store', [ProductController::class, 'priceStore'])->name('products.price.store');

            
            Route::resource('messages', MessageController::class);
             
            
            Route::patch('customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
            // Route::patch('/customers/{id}/toggle', [CustomerController::class, 'toggleStatus'])->name('customers.toggle');
            Route::post('/customers/import', [CustomerController::class, 'import'])->name('customers.import');
            Route::post('/customers/party-visit-target', [CustomerController::class, 'savePartyVisitTarget'])->name('customers.party-visit-target');
            Route::resource('customers', CustomerController::class);

            Route::resource('crop-categories', controller: CropCategoryController::class);
            Route::resource('crop-sub-categories', CropSubCategoryController::class);

            Route::get('farmers', [FarmerController::class, 'index'])->name('farmers.index');
            Route::get('daily-farm-visits', [FarmerController::class, 'dailyFarmVisits'])->name('farmers.daily-farm-visits');
            Route::get('farmers/{farmer}/farm-visits', [FarmerController::class, 'farmerWiseList'])->name('farmers.farm-visits');
            Route::post('farm-visits/{visit}/agronomist-remark', [FarmerController::class, 'saveAgronomistRemark'])->name('farm-visits.agronomist-remark');
            Route::post('farmers/farmer-visit-target', [FarmerController::class, 'saveFarmerVisitTarget'])->name('farmers.farmer-visit-target');
            Route::get('/farmers/pdf', [FarmerController::class, 'downloadPdf'])->name('farmers.pdf');
            
            // Farmer Visit Report
            Route::get('farmer-visit-report', [FarmerController::class, 'farmerVisitReport'])->name('farmer-visit-report');
            Route::get('farmer-visit-report-data', [FarmerController::class, 'getFarmerVisitReportData'])->name('farmer-visit-report.data');
            Route::get('farmer-visit-report-details', [FarmerController::class, 'getFarmerVisitDetails'])->name('farmer-visit-report.details');
            
        });
    });
});
