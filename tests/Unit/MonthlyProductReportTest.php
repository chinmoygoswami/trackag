<?php

namespace Tests\Unit;

use App\Services\MonthlyProductReport;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class MonthlyProductReportTest extends TestCase
{
    public function test_indian_number_grouping_preserves_decimal_precision(): void
    {
        foreach ([
            [0, 2, '0.00'],
            [1234.5, 2, '1,234.50'],
            [123456.78, 2, '1,23,456.78'],
            [234567890, 2, '23,45,67,890.00'],
            [-1234567.89, 2, '-12,34,567.89'],
            [123456.789, 3, '1,23,456.789'],
            [1234567, 0, '12,34,567'],
            [99999.999, 2, '1,00,000.00'],
        ] as [$value, $decimals, $expected]) {
            self::assertSame($expected, MonthlyProductReport::formatNumber($value, $decimals));
        }
    }

    private function sale(string $party, string $product = 'Product - 1 Kg', string $date = '2026-05-01', float $qty = 2.5, float $value = 100): object
    {
        return (object) ['party_name' => $party, 'product_name_with_packing' => $product, 'invoice_date' => new DateTimeImmutable($date), 'qty' => $qty, 'amount' => $value, 'grand_total' => 9999];
    }

    private function customer(int $id, int $employee = 4): object
    {
        return (object) ['id' => $id, 'party_code' => 'code'.$id, 'agro_name' => 'Party '.$id, 'name' => null, 'user_id' => $employee,
            'user' => (object) ['name' => 'Employee '.$employee, 'state' => null],
            'state' => (object) ['id' => $id, 'name' => 'State '.$id]];
    }

    public function test_tally_months_packing_subtotals_and_filters(): void
    {
        $rows = MonthlyProductReport::salesRows([
            $this->sale('Party 1', date: '2025-12-31'),
            $this->sale('Party 1', 'Product - 5 Kg', '2026-01-01', 3, 200),
            $this->sale('Party 2', date: '2026-01-31', qty: 1, value: 50),
            $this->sale('Party 2', date: '2026-02-01', qty: 99, value: 999),
        ], [$this->customer(1), $this->customer(2, 5)], [], [(object) ['product_name' => 'Product', 'product_category_id' => 8]], true, []);
        foreach (['party', 'employee', 'state'] as $dimension) {
            $report = MonthlyProductReport::build($rows, $dimension, ['2025-12', '2026-01', '2026-03']);
            self::assertCount(2, $report['groups']);
            self::assertSame(6.5, $report['total']['qty']);
            self::assertSame(350.0, $report['total']['value']);
            self::assertSame(250.0, $report['total']['monthly']['2026-01']['value']);
            self::assertSame(0.0, $report['total']['monthly']['2026-03']['qty']);
            self::assertSame(2, $report['product_count']);
            self::assertSame(3, $report['line_count']);
            foreach ($report['groups'] as $group) {
                self::assertSame($group['qty'], array_sum(array_column($group['products'], 'qty')));
                self::assertSame($group['value'], array_sum(array_column($group['products'], 'value')));
            }
        }
        foreach (['party_key' => 'customer:1', 'employee_key' => 4, 'state_id' => 1, 'product_key' => 'product - 5 kg', 'product_category_id' => 999] as $field => $value) {
            $report = MonthlyProductReport::build($rows, 'party', ['2025-12', '2026-01'], [$field => $value]);
            self::assertSame($field === 'product_category_id' ? 0.0 : ($field === 'product_key' ? 200.0 : 300.0), $report['total']['value']);
        }
        self::assertSame([], MonthlyProductReport::build([], 'party', ['2026-01'])['groups']);
    }

    public function test_sync_aliases_unmatched_sales_and_restricted_access(): void
    {
        $sync = (object) ['master_id' => 'code1', 'party_name' => 'Tally Party', 'state' => 'Gujarat'];
        $sales = [$this->sale('  TALLY   PARTY '), $this->sale('Unknown'), $this->sale('Party 2')];
        $customers = [$this->customer(1), $this->customer(2, 5)];
        $rows = MonthlyProductReport::salesRows($sales, $customers, [$sync], [], false, [4]);
        self::assertCount(1, $rows);
        self::assertSame('customer:1', $rows[0]['party_key']);
        $rows = MonthlyProductReport::salesRows($sales, $customers, [$sync], [], true, []);
        self::assertCount(3, $rows);
        self::assertSame('Unassigned Employee', $rows[1]['employee_name']);
        self::assertSame('Unassigned State', $rows[1]['state_name']);
        self::assertSame(300.0, MonthlyProductReport::build($rows, 'employee', ['2026-05'])['total']['value']);
        $duplicate = $this->customer(3, 5);
        $duplicate->agro_name = 'Party 1';
        self::assertSame([], MonthlyProductReport::salesRows([$this->sale('Party 1')], [$this->customer(1), $duplicate], [], [], false, [4]));
    }
}
