<?php

namespace Tests\Unit;

use App\Http\Controllers\ExpenseController;
use App\Models\User;
use App\Services\ExpenseReportCalculator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;

class ExpenseReportTest extends TestCase
{
    private $app;
    public static array $queries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require __DIR__.'/../../bootstrap/app.php';
        $this->app->make(Kernel::class)->bootstrap();
        restore_error_handler();
        restore_exception_handler();
        self::$queries = [];
        DB::extend('expense-report-test', fn ($config) => new class(null, 'test') extends MySqlConnection {
            public function select($query, $bindings = [], $useReadPdo = true)
            {
                ExpenseReportTest::$queries[] = [$query, $bindings];
                if (str_contains($query, 'count(*) as aggregate')) {
                    return [(object) ['aggregate' => str_contains($query, 'from `trips`') ? 10000 : 0]];
                }
                if (str_contains($query, 'from `trips`')) {
                    return array_map(fn ($id) => (object) ['id' => $id, 'user_id' => 1, 'trip_date' => '2026-10-01',
                        'tour_type' => 1, 'travel_mode' => 1, 'starting_km' => 10, 'end_km' => 30, 'trip_limit_override' => 0], [26, 27]);
                }
                if (str_contains($query, 'from `users`')) {
                    return [(object) ['id' => 1, 'name' => 'Employee', 'slab' => 'Individual']];
                }
                if (str_contains($query, 'from `ta_da_tour_slabs`')) {
                    return [(object) ['id' => 1, 'user_id' => 1, 'tour_type_id' => 1, 'da_amount' => 100]];
                }
                if (str_contains($query, 'from `ta_da_vehicle_slabs`')) {
                    return [(object) ['id' => 1, 'user_id' => 1, 'travel_mode_id' => 1, 'travelling_allow_per_km' => 5]];
                }
                if (str_contains($query, 'from `expenses`')) {
                    return [(object) ['user_id' => 1, 'bill_date' => '2026-10-01', 'total_amount' => 50]];
                }
                return [];
            }
        });
        DB::purge('mysql');
        config(['database.connections.mysql' => ['driver' => 'expense-report-test', 'database' => 'test']]);
        $user = new class extends User {
            public function getRoleNames(): \Illuminate\Support\Collection
            {
                return collect(['master_admin']);
            }
        };
        auth()->setUser($user);
    }

    protected function tearDown(): void
    {
        DB::purge('mysql');
        HandleExceptions::forgetApp();
        $this->app->flush();
        parent::tearDown();
    }

    public function test_optional_filters_paginate_trips_and_batch_calculations(): void
    {
        $request = Request::create('http://localhost/admin/expense-report', 'GET', ['month' => '2026-10', 'page' => 2]);
        $this->app->instance('request', $request);
        $view = (new ExpenseController)->expenseReport($request)->getData();
        self::assertSame(10000, $view['data']->total());
        self::assertSame(50, $view['data']->perPage());
        self::assertSame(2, $view['data']->currentPage());
        self::assertSame(250.0, $view['data'][0]->total_exp);
        self::assertSame(500.0, $view['total_total']);
        $queries = self::$queries;
        $tripQuery = array_values(array_filter($queries, fn ($q) => str_contains($q[0], 'select * from `trips`')))[0];
        self::assertStringContainsString('limit 50 offset 50', $tripQuery[0]);
        self::assertSame(['approved', '2026-10-01', '2026-10-31'], $tripQuery[1]);
        foreach (['expenses', 'ta_da_tour_slabs', 'ta_da_vehicle_slabs', 'ta_da_slabs'] as $table) {
            self::assertCount(1, array_filter($queries, fn ($q) => str_contains($q[0], 'from `'.$table.'`')));
        }
        self::assertFalse((bool) array_filter($queries, fn ($q) => str_contains($q[0], 'trip_logs') || str_contains($q[0], 'customer_trip')));
    }

    public function test_selected_state_and_employee_filters_are_applied(): void
    {
        $request = Request::create('http://localhost/admin/expense-report', 'GET', [
            'month' => '2026-10', 'state_id' => 3, 'user_id' => 1, 'search' => 'Ahmedabad', 'per_page' => 50,
        ]);
        $this->app->instance('request', $request);
        $view = (new ExpenseController)->expenseReport($request)->getData();
        self::assertSame(50, $view['data']->perPage());
        $tripQuery = array_values(array_filter(self::$queries, fn ($q) => str_contains($q[0], 'select * from `trips`')))[0];
        self::assertStringContainsString('`state_id` = ?', $tripQuery[0]);
        self::assertStringContainsString('`user_id` = ?', $tripQuery[0]);
        self::assertStringNotContainsString('`place_to_visit` like ?', $tripQuery[0]);
    }

    public function test_datatable_requests_return_requested_page_rows_and_totals(): void
    {
        $request = Request::create('http://localhost/admin/expense-report', 'GET', [
            'month' => '2026-10', 'draw' => 3, 'start' => 100, 'length' => 50,
        ]);
        $this->app->instance('request', $request);
        $result = (new ExpenseController)->expenseReport($request)->getData(true);
        self::assertSame(3, $result['draw']);
        self::assertSame(10000, $result['recordsTotal']);
        self::assertSame(10000, $result['recordsFiltered']);
        self::assertSame(500, $result['totals']['total']);
        self::assertSame(40, $result['totals']['travel_km']);
        self::assertStringContainsString('rowCheckbox', $result['rows']);
        self::assertStringContainsString('101&nbsp;', $result['rows']);
        self::assertStringContainsString('250.00', $result['rows']);
        $tripQuery = array_values(array_filter(self::$queries, fn ($q) => str_contains($q[0], 'select * from `trips`')))[0];
        self::assertStringContainsString('limit 50 offset 100', $tripQuery[0]);
    }

    public function test_empty_page_does_not_query_allowances_or_expenses(): void
    {
        (new ExpenseReportCalculator)->populate(collect());
        self::assertSame([], self::$queries);
    }

    public function test_allowance_limits_and_all_overrides_preserve_calculations(): void
    {
        foreach ([0 => [0, 0], 1 => [100, 80], 2 => [0, 80], 3 => [100, 0]] as $override => [$ta, $da]) {
            $amounts = ExpenseReportCalculator::amounts(20, 5, 80, true, 50, $override, 30);
            self::assertEquals($ta, $amounts['ta_exp']);
            self::assertEquals($da, $amounts['da_exp']);
            self::assertEquals($ta + $da + 30, $amounts['total_exp']);
        }
        self::assertSame(210.0, ExpenseReportCalculator::amounts(20, 5, 80, false, 50, 0, 30)['total_exp']);
        self::assertSame(360.0, ExpenseReportCalculator::amounts(50, 5, 80, true, 50, 0, 30)['total_exp']);
    }
}
