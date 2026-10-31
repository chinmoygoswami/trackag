<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TallyPartySync;
use App\Models\TallySalesBill;
use App\Services\MonthlyProductReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class MonthlyProductReportController extends Controller
{
    public function index(Request $request, string $dimension)
    {
        abort_unless(in_array($dimension, ['party', 'employee', 'state'], true), 404);
        $validated = $request->validate([
            'source' => 'nullable|in:tally,orders',
            'from_month' => 'nullable|date_format:Y-m',
            'to_month' => 'nullable|date_format:Y-m|after_or_equal:from_month',
            'state_id' => 'nullable|integer|min:1',
            'party_key' => 'nullable|string|max:500',
            'user_id' => 'nullable|integer|min:1',
            'product_category_id' => 'nullable|integer|min:1',
            'product_key' => 'nullable|string|max:500',
            'export' => 'nullable|in:csv',
        ]);
        if ($dimension === 'party') {
            $request->validate(['per_page' => 'nullable|integer|in:15,25,50,100', 'page' => 'nullable|integer|min:1']);
        }
        $filters = $this->getRoleBasedStateAndEmployeeFilters();
        $allowedIds = $filters['employees']->pluck('id')->push($filters['user']->id)->unique();
        $isAdmin = in_array($filters['roleName'], ['master_admin', 'sub_admin'], true);
        $source = $validated['source'] ?? 'tally';
        $sourceLabel = $source === 'tally' ? 'Tally Sales' : 'App Orders';
        if ($source === 'orders') {
            $orders = Order::with(['customer', 'user.state', 'items.product', 'items.packing'])
                ->whereIn('status', MonthlyProductReport::ORDER_STATUSES)
                ->when(! $isAdmin, fn ($q) => $q->whereIn('user_id', $allowedIds))->get();
            $rows = MonthlyProductReport::orderRows($orders, $filters['states']);
        } else {
            $sales = TallySalesBill::query();
            if ($dimension === 'party') {
                // Combine repeated sales lines before loading them into PHP.
                $sales->selectRaw('MIN(invoice_date) as invoice_date, party_name, product_name_with_packing, SUM(qty) as qty, SUM(amount) as amount')
                    ->groupBy('party_name', 'product_name_with_packing')
                    ->groupByRaw('SUBSTR(invoice_date, 1, 7)');
            } else {
                $sales->select(['invoice_date', 'party_name', 'product_name_with_packing', 'qty', 'amount']);
            }
            $rows = MonthlyProductReport::salesRows(
                $sales->get(),
                Customer::with(['user.state', 'state'])->get(), TallyPartySync::get(), Product::get(),
                $isAdmin, $allowedIds->all(), $filters['states']
            );
        }
        // Only the party report uses the current party owner's employee mapping.
        $partyEmployees = collect();
        if ($dimension === 'party') {
            $assignments = Customer::with('user')
                ->when(! $isAdmin, fn ($query) => $query->whereIn('user_id', $allowedIds))
                ->get()->keyBy(fn ($customer) => 'customer:'.$customer->id);
            foreach ($rows as &$row) {
                $owner = $assignments->get($row['party_key'])?->user;
                $row['employee_key'] = $owner?->id ?? 0;
                $row['employee_name'] = $owner?->name ?? 'Unassigned Employee';
                if ($owner) {
                    $partyEmployees->put($owner->id, $owner);
                }
            }
            unset($row);
        }
        $latest = collect($rows)->max('month');
        $anchor = $latest ? Carbon::createFromFormat('!Y-m', $latest) : now();
        $defaultFrom = $anchor->copy()->startOfMonth()->month(4);
        if ($anchor->month < 4) {
            $defaultFrom->subYear();
        }
        $from = Carbon::createFromFormat('!Y-m', $validated['from_month'] ?? $defaultFrom->format('Y-m'));
        $to = Carbon::createFromFormat('!Y-m', $validated['to_month'] ?? $anchor->format('Y-m'));
        if ($to->lt($from) || $from->diffInMonths($to) > 35) {
            throw ValidationException::withMessages(['to_month' => 'Choose an end month on or after the start month, within 36 months.']);
        }
        $months = [];
        for ($date = $from->copy(); $date->lte($to); $date->addMonth()) {
            $months[$date->format('Y-m')] = $date->format('M-Y');
        }
        $reportFilters = [
            'state_id' => $validated['state_id'] ?? null,
            'product_key' => $validated['product_key'] ?? null,
            'product_category_id' => $dimension === 'party' ? null : ($validated['product_category_id'] ?? null),
        ];
        if ($dimension === 'party') {
            $reportFilters['party_key'] = $validated['party_key'] ?? null;
        }
        if (in_array($dimension, ['party', 'employee'], true)) {
            $reportFilters['employee_key'] = $validated['user_id'] ?? null;
        }
        $report = MonthlyProductReport::build($rows, $dimension, array_keys($months), $reportFilters);
        $label = ucfirst($dimension);
        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($report, $months, $label) {
                $file = fopen('php://output', 'w');
                fwrite($file, "\xEF\xBB\xBF");
                $header = [$label.' Name', 'Product Name'];
                foreach ($months as $month) {
                    array_push($header, $month.' Qty', $month.' Value');
                }
                fputcsv($file, [...$header, 'Total Qty', 'Total Value']);
                $write = function ($name, $product, $row) use ($file) {
                    $safe = fn ($s) => preg_match('/^[=+@\-\t\r\n]/', $s) ? "'".$s : $s;
                    $values = [$safe($name), $safe($product)];
                    foreach ($row['monthly'] as $cell) {
                        array_push($values, $cell['qty'], $cell['value']);
                    }
                    fputcsv($file, [...$values, $row['qty'], $row['value']]);
                };
                foreach ($report['groups'] as $group) {
                    $write($group['name'], 'Subtotal', $group);
                    foreach ($group['products'] as $product) {
                        $write($group['name'], $product['name'], $product);
                    }
                }
                $write('Grand Total', '', $report['total']);
                fclose($file);
            }, $dimension.'-product-monthwise-'.$source.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $groupCount = count($report['groups']);
        $partyPagination = null;
        if ($dimension === 'party' && ! $request->boolean('print')) {
            $perPage = (int) $request->input('per_page', 50);
            $page = max(1, (int) $request->input('page', 1));
            $partyPagination = new LengthAwarePaginator(
                array_slice($report['groups'], ($page - 1) * $perPage, $perPage, true),
                $groupCount, $perPage, $page,
                ['path' => $request->url(), 'query' => $request->except(['page', 'export', 'print'])]
            );
            $report['groups'] = $partyPagination->items();
        }

        return view('admin.reports.monthly-products', compact('report', 'months', 'dimension', 'label', 'from', 'to', 'source', 'sourceLabel', 'groupCount', 'partyPagination') + [
            'states' => $filters['states'],
            'employees' => $dimension === 'party' ? $partyEmployees->sortBy('name') : $filters['employees']->push($filters['user'])->unique('id')->sortBy('name'),
            'parties' => collect($rows)->unique('party_key')->map(fn ($row) => (object) ['id' => $row['party_key'], 'name' => $row['party_name'], 'employee_id' => $row['employee_key']])->sortBy('name'),
            'products' => collect($rows)->unique('product_key')->map(fn ($row) => (object) ['id' => $row['product_key'], 'name' => $row['product_name']])->sortBy('name'),
            'categories' => $dimension === 'party' ? collect() : ProductCategory::orderBy('name')->get(),
        ]);
    }
}
