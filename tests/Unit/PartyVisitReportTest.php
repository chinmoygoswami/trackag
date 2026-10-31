<?php

namespace Tests\Unit;

use App\Http\Controllers\PartyController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;

class PartyVisitReportTest extends TestCase
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
        DB::extend('visit-report-test', fn ($config) => new class(null, 'test') extends MySqlConnection {
            public function select($query, $bindings = [], $useReadPdo = true)
            {
                PartyVisitReportTest::$queries[] = [$query, $bindings];
                if (str_contains($query, 'count(*) as aggregate')) {
                    return [(object) ['aggregate' => 10000]];
                }
                if (str_contains($query, 'from `customers`')) {
                    return [(object) ['id' => 26, 'agro_name' => 'Party 26', 'user_id' => 1]];
                }
                if (str_contains($query, 'from `users`')) {
                    return [(object) ['id' => 1, 'name' => 'Employee']];
                }
                if (str_contains($query, 'from `party_visits`')) {
                    return [(object) ['customer_id' => 26, 'visit_month' => '2026-04', 'visit_count' => 3],
                        (object) ['customer_id' => 26, 'visit_month' => '2027-03', 'visit_count' => 2]];
                }
                if (str_contains($query, 'from `party_visit_targets`')) {
                    return [(object) ['id' => 1, 'target' => 2]];
                }
                throw new \RuntimeException($query);
            }
        });
        DB::purge('mysql');
        config(['database.connections.mysql' => ['driver' => 'visit-report-test', 'database' => 'test']]);
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

    public function test_all_filters_are_optional_and_only_requested_page_visits_are_aggregated(): void
    {
        $request = Request::create('http://localhost/admin/party-visit-report-data', 'GET', [
            'financial_year' => '2026-2027', 'draw' => 4, 'start' => 25, 'length' => 25,
        ]);
        $result = (new PartyController)->getPartyVisitReportData($request)->getData(true);
        self::assertSame(4, $result['draw']);
        self::assertSame(10000, $result['recordsTotal']);
        self::assertCount(1, $result['data']);
        self::assertCount(14, $result['columns']);
        self::assertSame(3, $result['data'][0]['month_0']['count']);
        self::assertSame(0, $result['data'][0]['month_1']['count']);
        self::assertSame(2, $result['data'][0]['month_11']['count']);
        $customerQuery = array_values(array_filter(self::$queries, fn ($q) => str_contains($q[0], 'select `id`, `agro_name`')))[0];
        self::assertStringContainsString('limit 25 offset 25', $customerQuery[0]);
        $visitQuery = array_values(array_filter(self::$queries, fn ($q) => str_contains($q[0], 'from `party_visits`')))[0];
        self::assertStringContainsString('COUNT(*)', $visitQuery[0]);
        self::assertStringContainsString('group by', $visitQuery[0]);
        self::assertSame([26, '2026-04-01', '2027-03-31'], $visitQuery[1]);
    }
}
