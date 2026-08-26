<?php

use App\Http\Controllers\CompanyController;
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
use App\Http\Controllers\TripController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\TravelModeController;
use App\Http\Controllers\PurposeController;
use App\Http\Controllers\TourTypeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\MonthlyController;
use App\Http\Controllers\AchievementController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\ExpenseController;
use App\Http\Middleware\EnsureTenantDatabase;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// ---------------- Tenant Domain Routes ----------------
// These routes are automatically wrapped with tenancy middleware by RouteServiceProvider
// They handle tenant-specific database operations

Route::get('/', function () {
    return redirect()->route('admin.login');
});

// Debug route to check tenant context
Route::get('/debug-tenant', function () {
    $tenant = tenancy()->tenant;
    if ($tenant) {
        return response()->json([
            'tenant_id' => $tenant->id,
            'database' => \DB::connection('tenant')->getDatabaseName(),
            'tenant_database' => $tenant->getDatabaseName(),
            'domain' => request()->getHost(),
            'all_tenants' => \App\Models\Tenant::all()->pluck('id'),
        ]);
    }
    return response()->json([
        'error' => 'No tenant found',
        'domain' => request()->getHost(),
        'all_tenants' => \App\Models\Tenant::all()->pluck('id'),
        'all_domains' => \App\Models\Domain::all()->pluck('domain'),
    ]);
});

Route::prefix('admin')->group(function () {
    
    // Public Login for Tenant
    Route::get('login', [AdminController::class, 'create'])->name('admin.login');
    Route::post('login', [AdminController::class, 'store'])->name('auth.login.request');
    Route::get('logout', [AdminController::class, 'destroy'])->name('admin.logout');

    // Protected Tenant Routes
    Route::middleware(['admin', 'last_seen'])->group(function () {
        Route::get('dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
        Route::get('users/{userId}/sessions', [AdminController::class, 'getUserSessionHistory'])->name('admin.users.sessions');
        Route::delete('/customers/bulk-delete', [CustomerController::class, 'bulkDelete'])->name('customers.bulk-delete');

        Route::resource('roles', RoleController::class);
        Route::resource('permissions', PermissionController::class);
        Route::resource('users', UserController::class);
        Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

        // HR (tenant database)
        Route::resource('/hr/designations', DesignationController::class);
        Route::get('/hr/attendance', [AttendanceController::class, 'index'])->name('attendance.index');

        Route::resource('companies', CompanyController::class);
        Route::patch('companies/{id}/toggle', [CompanyController::class, 'toggle'])->name('companies.toggle');


        Route::resource('travelmode', TravelModeController::class)->names('travelmode');
        Route::resource('tourtype', TourTypeController::class)->names('tourtype');
        Route::resource('purpose', PurposeController::class)->names('purpose');
        Route::resource('trips', TripController::class)->names('trips');
        Route::post('/trips/{trip}/approve', [TripController::class, 'approve'])->name('trips.approve');
        Route::post('/admin/trips/{id}/complete', [TripController::class, 'completeTrip'])->name('trips.complete');
        Route::post('/trips/{trip}/toggle-status', [TripController::class, 'toggleStatus'])->name('trips.status.toggle');
        // });

        // Customers (tenant database)
        Route::resource('customers', CustomerController::class);
        Route::patch('/customers/{id}/toggle', [CustomerController::class, 'toggleStatus'])->name('customers.toggle');

        // Business modules (tenant database)
        Route::get('budget/logs', [BudgetController::class, 'getLogs'])->name('budget.logs');
        Route::get('budget-report', [BudgetController::class, 'report'])->name('budget.report');
        Route::resource('budget', BudgetController::class);
        Route::resource('monthly', MonthlyController::class);
        Route::resource('achievement', AchievementController::class);
        Route::resource('party', PartyController::class);
        Route::get('order-report', [OrderController::class, 'report'])->name('order.report');
        Route::resource('order', OrderController::class);
        Route::resource('stock', StockController::class);
        // Route::resource('tracking', TrackingController::class);
        Route::resource('expense', ExpenseController::class);
        
        // Reports
        Route::get('reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');

        // Location management (tenant database)
        Route::resource('states', StateController::class);
        Route::post('/states/toggle-status', [StateController::class, 'toggleStatus'])->name('states.toggle-status');
        Route::resource('districts', DistrictController::class);
        Route::resource('cities', CityController::class);
        Route::resource('tehsils', TehsilController::class);

        // AJAX endpoints (tenant database)
        Route::get('/get-districts/{state_id}', [UserController::class, 'getDistricts'])->name('get.districts');
        Route::get('/get-cities/{district_id}', [UserController::class, 'getCities'])->name('get.cities');
        Route::get('/get-tehsils/{city_id}', [UserController::class, 'getTehsils'])->name('get.tehsils');
        Route::get('/get-pincodes/{city_id}', [UserController::class, 'getPincodes'])->name('get.pincodes');

        // Trip logs (tenant database)
        Route::post('/trip-logs', [TripController::class, 'logPoint'])->name('trip.log');
        Route::get('/trips/{trip}/map', [TripController::class, 'showRoute'])->name('trip.map');
        Route::get('/trips/{trip}/logs', [TripController::class, 'logs'])->name('trips.logs');
    });
});
