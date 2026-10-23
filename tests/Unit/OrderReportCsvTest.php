<?php

namespace Tests\Unit;

use App\Http\Controllers\OrderController;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderReportCsvTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_export_preserves_filters_and_access_and_writes_product_values(): void
    {
        Container::setInstance(new Container);
        $orders = Mockery::mock('alias:App\Models\Order');
        $orders->shouldReceive('with')->with(['user.state', 'customer', 'items'])->once()->andReturnSelf();
        $orders->shouldReceive('where')->with('status', 'approved')->once()->andReturnSelf();
        $orders->shouldReceive('latest')->once()->andReturnSelf();
        $orders->shouldReceive('whereIn')->with('user_id', Mockery::on(fn ($ids) => $ids->all() === [8, 7]))->once()->andReturnSelf();
        $orders->shouldReceive('whereDate')->with('created_at', '>=', '2026-10-01')->once()->andReturnSelf();
        $orders->shouldReceive('whereDate')->with('created_at', '<=', '2026-10-23')->once()->andReturnSelf();
        $orders->shouldReceive('whereHas')->with('user', Mockery::on(function ($callback) {
            $userQuery = Mockery::mock();
            $userQuery->shouldReceive('where')->with('state_id', '3')->once();
            $callback($userQuery);

            return true;
        }))->once()->andReturnSelf();
        foreach (['user_id' => '8', 'party_id' => '9', 'order_type' => 'cash'] as $field => $value) {
            $orders->shouldReceive('where')->with($field, $value)->once()->andReturnSelf();
        }
        $orders->shouldReceive('where')->with('order_no', 'like', '%ORD%')->once()->andReturnSelf();
        $orders->shouldReceive('with')->with(['items.product', 'items.packing'])->once()->andReturnSelf();
        $orders->shouldReceive('get')->once()->andReturn(collect([(object) [
            'order_no' => 'ORD-1', 'created_at' => Carbon::parse('2026-10-23 12:00:00'),
            'order_type' => 'cash', 'status' => 'approved',
            'user' => (object) ['name' => 'Employee', 'state' => (object) ['name' => 'Gujarat']],
            'customer' => (object) ['agro_name' => '=Party, name'],
            'items' => collect([(object) [
                'product' => (object) ['product_name' => 'Product', 'gst' => 18],
                'packing' => (object) ['packing_value' => 1, 'packing_size' => 'L'],
                'qty' => 2, 'price' => 100, 'total_price' => 200, 'gst' => 36, 'grand_total' => 236,
            ]]),
        ]]));

        $factory = Mockery::mock(ResponseFactory::class);
        $factory->shouldReceive('streamDownload')->once()->with(
            Mockery::type('Closure'), Mockery::pattern('/^order-report-\d{8}-\d{6}\.csv$/'),
            ['Content-Type' => 'text/csv; charset=UTF-8']
        )->andReturnUsing(fn ($callback) => new StreamedResponse($callback));
        Container::getInstance()->instance(ResponseFactory::class, $factory);
        $controller = new class extends OrderController {
            protected function getRoleBasedStateAndEmployeeFilters()
            {
                return ['roleName' => 'employee', 'employees' => collect([(object) ['id' => 8]]), 'user' => (object) ['id' => 7]];
            }
        };
        $response = $controller->exportReportCsv(new Request([
            'from_date' => '2026-10-01', 'to_date' => '2026-10-23', 'state_id' => '3',
            'user_id' => '8', 'party_id' => '9', 'order_type' => 'cash', 'order_no' => 'ORD',
        ]));
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = explode("\n", trim(substr($csv, 3)));
        $header = str_getcsv($lines[0]);
        $row = str_getcsv($lines[1]);
        self::assertCount(16, $header);
        self::assertCount(16, $row);
        self::assertSame("'=Party, name", $row[5]);
        self::assertSame(['200', '36', '236', '200', 'Approved'], array_slice($row, 11));
        Mockery::close();
    }
}
