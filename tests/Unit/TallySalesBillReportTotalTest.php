<?php

namespace Tests\Unit;

use App\Models\TallySalesBill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\MySqlConnection;
use PHPUnit\Framework\TestCase;

class TallySalesBillReportTotalTest extends TestCase
{
    public function test_shared_scope_fetches_stored_pre_gst_total_and_preserves_other_selections(): void
    {
        // Compile the real Eloquent query without connecting to a database.
        $connection = new MySqlConnection(null);
        $query = (new Builder($connection->query()))->setModel(new TallySalesBill);
        $query->selectRaw('party_name, SUM(qty) as total_qty')
            ->withSalesTotalWithoutGst()
            ->whereBetween('invoice_date', ['2025-04-01', '2026-03-31'])
            ->groupBy('party_name');

        self::assertSame(
            'select party_name, SUM(qty) as total_qty, SUM(amount) as total_amount from `tally_sales_bills` where `invoice_date` between ? and ? group by `party_name`',
            $query->toSql()
        );
        self::assertSame(['2025-04-01', '2026-03-31'], $query->getBindings());
    }

    public function test_scope_does_not_change_normal_invoice_queries(): void
    {
        $connection = new MySqlConnection(null);
        $query = (new Builder($connection->query()))->setModel(new TallySalesBill);

        self::assertSame('select * from `tally_sales_bills`', $query->toSql());
    }
}
