<?php

namespace Tests\Unit;

use App\Http\Controllers\PartyMonthlyCollectionReportController;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class PartyMonthlyCollectionReportTest extends TestCase
{
    public static function reportTypes(): array
    {
        return [
            'employee excludes GST' => ['employees', 1500.50],
            'state excludes GST' => ['states', 1500.50],
        ];
    }

    #[DataProvider('reportTypes')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_report_amounts_and_financial_year_are_isolated(string $method, float $expected): void
    {
        $container = new Container;
        Container::setInstance($container);
        $user = Mockery::mock();
        $user->id = 7;
        $user->shouldReceive('getRoleNames')->andReturn(collect(['master_admin']));
        $auth = Mockery::mock(AuthFactory::class);
        $auth->shouldReceive('user')->andReturn($user);
        $container->instance(AuthFactory::class, $auth);

        $users = Mockery::mock('alias:App\Models\User');
        $users->shouldReceive('where')->with('reporting_to', 7)->andReturnSelf();
        $users->shouldReceive('pluck')->with('id')->andReturn(collect());

        $customers = Mockery::mock('alias:App\Models\Customer');
        $customers->shouldReceive('query', 'with', 'where')->andReturnSelf();
        $customers->shouldReceive('when')->with(false, Mockery::type('Closure'))->andReturnSelf();
        $customers->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 1, 'user_id' => 7, 'party_code' => 'P1', 'agro_name' => 'Local Party',
                'user' => (object) ['name' => 'Employee', 'state' => null],
                'state' => (object) ['name' => 'Gujarat'],
            ],
        ]));
        $sync = Mockery::mock('alias:App\Models\TallyPartySync');
        $sync->shouldReceive('query', 'whereIn')->andReturnSelf();
        $sync->shouldReceive('get')->andReturn(collect([
            (object) ['master_id' => 'P1', 'party_name' => 'Tally Party'],
        ]));

        // Simulate the database aggregate while checking the actual SQL field
        // and April-to-March range requested by the controller.
        $sales = Mockery::mock('alias:App\Models\TallySalesBill');
        $payments = Mockery::mock('alias:App\Models\TallyPartywisePaymentCredit');
        foreach ([$sales, $payments] as $model) {
            $model->shouldReceive('min')->andReturn('2025-04-01');
            $model->shouldReceive('max')->andReturn('2026-03-31');
            $model->shouldReceive('query')->andReturnSelf();
            $model->shouldReceive('groupBy')->with('party_name', 'month_key')->andReturnSelf();
        }
        $sales->shouldReceive('whereBetween')->once()
            ->with('invoice_date', ['2025-04-01', '2026-03-31'])->andReturnSelf();
        $sales->shouldReceive('selectRaw')->once()
            ->with("party_name, DATE_FORMAT(invoice_date, '%Y-%m') as month_key")
            ->andReturnSelf();
        $sales->shouldReceive('withSalesTotalWithoutGst')->once()->andReturnSelf();
        $sales->shouldReceive('get')->andReturn(collect([
            (object) ['party_name' => ' TALLY PARTY ', 'month_key' => '2025-04', 'total_amount' => $expected],
            (object) ['party_name' => 'P1', 'month_key' => '2026-03', 'total_amount' => -100],
            (object) ['party_name' => 'Unlinked', 'month_key' => '2025-04', 'total_amount' => 9999],
        ]));
        $payments->shouldReceive('whereBetween')->once()
            ->with('payment_date', ['2025-04-01', '2026-03-31'])->andReturnSelf();
        $payments->shouldReceive('selectRaw')->once()
            ->with("party_name, DATE_FORMAT(payment_date, '%Y-%m') as month_key, SUM(credit_amount) as total_amount")
            ->andReturnSelf();
        $payments->shouldReceive('get')->andReturn(collect([
            (object) ['party_name' => 'local party', 'month_key' => '2025-04', 'total_amount' => 1180],
        ]));

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once()->andReturn(['financial_year' => 2025]);
        $view = Mockery::mock(View::class);
        $views = Mockery::mock(ViewFactory::class);
        $views->shouldReceive('make')->once()->with(
            'admin.party.monthly-debit-credit',
            Mockery::on(function (array $data) use ($expected) {
                self::assertCount(12, $data['months']);
                self::assertSame(2025, $data['financialYear']);
                self::assertCount(1, $data['rows']);
                $monthly = $data['rows']->first()['monthly'];
                self::assertSame($expected, $monthly['2025-04']['debit']);
                self::assertSame(1180.0, $monthly['2025-04']['payment']);
                self::assertSame(-100.0, $monthly['2026-03']['debit']);
                self::assertSame(['debit' => 0.0, 'payment' => 0.0], $monthly['2025-05']);

                return true;
            }),
            []
        )->andReturn($view);
        $container->instance(ViewFactory::class, $views);

        self::assertSame($view, (new PartyMonthlyCollectionReportController)->{$method}($request));
        Mockery::close();
    }
}
