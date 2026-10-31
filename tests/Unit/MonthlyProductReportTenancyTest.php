<?php

namespace Tests\Unit;

use App\Http\Controllers\MonthlyProductReportController;
use App\Http\Middleware\EnsureTenantDatabase;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\State;
use App\Models\TallyPartySync;
use App\Models\TallySalesBill;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\MonthlyProductReport;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class MonthlyProductReportTenancyTest extends TestCase
{
    private $app;

    public static array $queriedTables = [];
    public static bool $manyParties = false;

    protected function setUp(): void
    {
        parent::setUp();
        self::$manyParties = false;
        $this->app = require __DIR__.'/../../bootstrap/app.php';
        $this->app->make(Kernel::class)->bootstrap();
        restore_error_handler();
        restore_exception_handler();
        // In-memory query doubles: never connect to or modify a real database.
        DB::extend('report-isolation-test', function ($config) {
            return new class(null, $config['database']) extends MySqlConnection
            {
                public function select($query, $bindings = [], $useReadPdo = true)
                {
                    $company = $this->getDatabaseName();
                    $tables = [
                        'orders' => ['id' => 1, 'party_id' => 1, 'user_id' => 1, 'status' => 'approved', 'created_at' => '2026-05-01 12:00:00'],
                        'order_items' => ['id' => 1, 'order_id' => 1, 'product_id' => 1, 'packing_id' => 1, 'qty' => 2, 'total_price' => 40],
                        'product_packings' => ['id' => 1, 'packing_value' => 1, 'packing_size' => 'Kg'],
                        'product_categories' => ['id' => 1, 'name' => 'Category'],
                        'tally_sales_bills' => ['id' => 1, 'invoice_date' => '2026-05-01', 'party_name' => $company.' Party', 'product_name_with_packing' => $company.' Product', 'qty' => 2, 'amount' => $company === 'company_a' ? 100 : 300],
                        'tally_party_syncs' => ['id' => 1, 'master_id' => 'code1', 'party_name' => $company.' Party', 'state' => $company.' State'],
                        'customers' => ['id' => 1, 'agro_name' => $company.' Party', 'party_code' => 'code1', 'user_id' => 1, 'state_id' => 1],
                        'users' => ['id' => 1, 'name' => $company.' Employee', 'state_id' => 1],
                        'states' => ['id' => 1, 'name' => $company.' State'],
                        'products' => ['id' => 1, 'product_name' => $company.' Product', 'product_category_id' => 1],
                    ];
                    foreach ($tables as $table => $row) {
                        if (str_contains($query, 'from `'.$table.'`')) {
                            MonthlyProductReportTenancyTest::$queriedTables[] = $table;

                            if (MonthlyProductReportTenancyTest::$manyParties && in_array($table, ['customers', 'tally_sales_bills'], true)) {
                                return array_map(function ($id) use ($row, $table, $company) {
                                    $row['id'] = $id;
                                    if ($table === 'customers') {
                                        $row['agro_name'] = $company.' Party '.$id;
                                        $row['party_code'] = 'code'.$id;
                                    } else {
                                        $row['party_name'] = $company.' Party '.$id;
                                    }
                                    return (object) $row;
                                }, range(1, 60));
                            }
                            return [(object) $row];
                        }
                    }
                    throw new \RuntimeException('Unexpected query: '.$query);
                }
            };
        });
        foreach (['mysql', 'tenant'] as $connection) {
            DB::purge($connection);
            config(['database.connections.'.$connection => ['driver' => 'report-isolation-test', 'database' => 'central']]);
        }
    }

    protected function tearDown(): void
    {
        tenancy()->tenant = null;
        DB::purge('tenant');
        DB::purge('mysql');
        HandleExceptions::forgetApp();
        $this->app->flush();
        parent::tearDown();
    }

    public function test_reports_and_filter_models_follow_the_active_tenant_without_retaining_previous_company_data(): void
    {
        // IDs intentionally collide across tenants, as they do in separate databases.
        foreach ([['company_a', 100.0], ['company_b', 300.0], ['company_a', 100.0]] as [$database, $value]) {
            $this->assertTenantReport($database, $value);
        }
        tenancy()->tenant = null;
        self::assertSame('central', (new TallySalesBill)->getConnection()->getDatabaseName());
    }

    public function test_report_routes_require_order_permission_and_tenant_routes_keep_database_middleware(): void
    {
        $routes = $this->app['router']->getRoutes();
        $central = $routes->match(Request::create('http://localhost/admin/product-monthwise-report/party'));
        self::assertContains('admin', $central->gatherMiddleware());
        self::assertContains('permission:view_order', $central->gatherMiddleware());
        self::assertSame(['GET', 'HEAD'], $central->methods());

        $provider = $this->app->getProvider(RouteServiceProvider::class);
        $method = new \ReflectionMethod($provider, 'mapTenantRoutes');
        $method->invoke($provider);
        foreach (['party', 'employee', 'state'] as $dimension) {
            $route = $routes->match(Request::create('http://company.example/admin/product-monthwise-report/'.$dimension));
            self::assertContains(EnsureTenantDatabase::class, $route->gatherMiddleware());
            self::assertContains(PreventAccessFromCentralDomains::class, $route->gatherMiddleware());
            self::assertContains('permission:view_order', $route->gatherMiddleware());
            self::assertContains('admin', $route->gatherMiddleware());
        }
    }

    public function test_source_filter_keeps_queries_totals_options_and_exports_separate(): void
    {
        $tenant = new Tenant;
        $tenant->forceFill(['id' => 'company_a', 'tenancy_db_name' => 'company_a']);
        tenancy()->tenant = $tenant;
        $controller = new class extends MonthlyProductReportController
        {
            protected function getRoleBasedStateAndEmployeeFilters()
            {
                return ['employees' => collect(), 'user' => User::first(), 'roleName' => 'master_admin', 'states' => State::get()];
            }
        };
        foreach (['party', 'employee', 'state'] as $dimension) {
            foreach (['tally' => 100.0, 'orders' => 40.0] as $source => $expected) {
                self::$queriedTables = [];
                $request = Request::create('http://localhost/admin/product-monthwise-report/'.$dimension, 'GET', ['source' => $source]);
                $data = $controller->index($request, $dimension)->getData();
                self::assertSame($source, $data['source']);
                self::assertSame($expected, $data['report']['total']['value']);
                self::assertSame(1, $data['report']['line_count']);
                self::assertContains($source === 'tally' ? 'tally_sales_bills' : 'orders', self::$queriedTables);
                self::assertNotContains($source === 'tally' ? 'orders' : 'tally_sales_bills', self::$queriedTables);
                $key = $data['products']->first()->id;
                self::assertSame($source === 'tally' ? 'company_a product' : 'product:1:packing:1', $key);
                $exportRequest = Request::create('http://localhost/admin/product-monthwise-report/'.$dimension, 'GET', ['source' => $source, 'export' => 'csv']);
                $response = $controller->index($exportRequest, $dimension);
                self::assertStringContainsString($dimension.'-product-monthwise-'.$source.'.csv', $response->headers->get('Content-Disposition'));
                ob_start();
                $response->sendContent();
                $csv = ob_get_clean();
                $lines = explode("\n", trim($csv));
                $total = str_getcsv(end($lines));
                self::assertSame('Grand Total', $total[0]);
                self::assertSame($expected, (float) $total[count($total) - 1]);
            }
        }
        $data = $controller->index(Request::create('http://localhost/report'), 'party')->getData();
        self::assertSame('tally', $data['source']);
    }

    public function test_party_owner_filter_replaces_category_only_in_party_report(): void
    {
        $controller = new class extends MonthlyProductReportController
        {
            protected function getRoleBasedStateAndEmployeeFilters()
            {
                return ['employees' => collect(), 'user' => User::first(), 'roleName' => 'master_admin', 'states' => State::get()];
            }
        };
        foreach (['tally', 'orders'] as $source) {
            $data = $controller->index(Request::create('http://localhost/report', 'GET', ['source' => $source]), 'party')->getData();
            self::assertSame(1, $data['employees']->first()->id);
            self::assertSame(1, $data['parties']->first()->employee_id);
            self::assertTrue($data['categories']->isEmpty());
            $filtered = $controller->index(Request::create('http://localhost/report', 'GET', [
                'source' => $source, 'user_id' => 99,
            ]), 'party')->getData();
            self::assertSame([], $filtered['report']['groups']);
            $category = $controller->index(Request::create('http://localhost/report', 'GET', [
                'source' => $source, 'product_category_id' => 999,
            ]), 'party')->getData();
            self::assertSame($data['report']['total'], $category['report']['total']);
            $state = $controller->index(Request::create('http://localhost/report', 'GET', [
                'source' => $source, 'product_category_id' => 999,
            ]), 'state')->getData();
            self::assertSame([], $state['report']['groups']);
            self::assertNotEmpty($state['categories']);
        }
    }

    public function test_only_party_report_paginates_whole_groups_and_keeps_full_totals_and_exports(): void
    {
        self::$manyParties = true;
        $controller = new class extends MonthlyProductReportController
        {
            protected function getRoleBasedStateAndEmployeeFilters()
            {
                return ['employees' => collect(), 'user' => User::first(), 'roleName' => 'master_admin', 'states' => State::get()];
            }
        };
        $request = fn ($params = []) => Request::create('http://localhost/report', 'GET', $params);
        $first = $controller->index($request(), 'party')->getData();
        self::assertCount(50, $first['report']['groups']);
        self::assertSame(60, $first['groupCount']);
        self::assertSame(18000.0, $first['report']['total']['value']);
        $second = $controller->index($request(['per_page' => 25, 'page' => 2]), 'party')->getData();
        self::assertCount(25, $second['report']['groups']);
        self::assertSame(26, $second['partyPagination']->firstItem());
        self::assertSame(18000.0, $second['report']['total']['value']);
        self::assertSame('central Party 26', reset($second['report']['groups'])['name']);
        foreach (['state', 'employee'] as $dimension) {
            $other = $controller->index($request(), $dimension)->getData();
            self::assertNull($other['partyPagination']);
        }
        $print = $controller->index($request(['print' => 1]), 'party')->getData();
        self::assertCount(60, $print['report']['groups']);
        $export = $controller->index($request(['page' => 2, 'per_page' => 25, 'export' => 'csv']), 'party');
        ob_start();
        $export->sendContent();
        $csv = ob_get_clean();
        self::assertStringContainsString('central Party 1', $csv);
        self::assertStringContainsString('central Party 60', $csv);
    }

    private function assertTenantReport(string $database, float $value): void
    {
        $tenant = new Tenant;
        $tenant->forceFill(['id' => $database, 'tenancy_db_name' => $database]);
        tenancy()->tenant = $tenant;
        foreach ([TallySalesBill::class, TallyPartySync::class, Customer::class, User::class, State::class, Product::class, ProductCategory::class, Company::class] as $model) {
            self::assertSame($database, (new $model)->getConnection()->getDatabaseName());
        }
        $rows = MonthlyProductReport::salesRows(TallySalesBill::get(), Customer::with(['user.state', 'state'])->get(), TallyPartySync::get(), Product::get(), true, []);
        foreach (['party' => 'Party', 'employee' => 'Employee', 'state' => 'State'] as $dimension => $label) {
            $report = MonthlyProductReport::build($rows, $dimension, ['2026-05']);
            self::assertCount(1, $report['groups']);
            $group = reset($report['groups']);
            self::assertSame($database.' '.$label, $group['name']);
            self::assertSame($database.' Product', reset($group['products'])['name']);
            self::assertSame($value, $report['total']['value']);
        }
    }
}
