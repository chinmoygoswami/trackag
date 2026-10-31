@extends('admin.layout.layout')
@section('title', $label.' Wise Vs Product Name Report | Trackag')
@push('styles')
<style>
.monthly-products .report-filters{background:#edf5ff}.monthly-products .report-table{white-space:nowrap;font-size:.875rem}.monthly-products .report-table thead th{background:#e3f3e4;text-align:center;vertical-align:middle}.monthly-products .group-row{background:#eaf3ff;font-weight:700}.monthly-products tfoot{background:#d4edfc;font-weight:700}.monthly-products .metric{border:0;min-height:95px}.monthly-products .product-name{padding-left:3rem}.monthly-products .report-table td{text-align:right}.monthly-products .report-table td:first-child,.monthly-products .report-table td:nth-child(2){text-align:left}
.monthly-products .report-table .group-row > td {
    --bs-table-bg: #e8f0fe;
    background-color: #e8f0fe;
    color: #173b70;
}
.monthly-products .report-table .total-cell,
.monthly-products .report-table thead .total-heading {
    --bs-table-bg: #fff3cd;
    background-color: #fff3cd;
    color: #664d03;
    font-weight: 700;
}
.monthly-products .report-table .group-row > .total-cell {
    --bs-table-bg: #ffe0b2;
    background-color: #ffe0b2;
    color: #713f12;
}
.monthly-products .report-table tfoot td {
    --bs-table-bg: #d1e7dd;
    background-color: #d1e7dd;
    color: #0f5132;
    font-weight: 700;
    border-top: 2px solid #198754;
}
.monthly-products .report-table tfoot .total-cell {
    --bs-table-bg: #0f5132;
    background-color: #0f5132;
    color: #fff;
}
@media print{body *{visibility:hidden}.monthly-products,.monthly-products *{visibility:visible}.monthly-products{position:absolute;left:0;top:0;width:100%}.monthly-products .no-print{display:none!important}.monthly-products .table-responsive{overflow:visible}.monthly-products tr[hidden]{display:table-row!important}@page{size:landscape;margin:10mm}}
</style>
@endpush
@section('content')
<main class="app-main monthly-products">
<div class="app-content-header"><div class="container-fluid d-flex flex-wrap justify-content-between gap-2 align-items-center">
<h4 class="mb-0">{{ $label }} Wise Vs Product Name Report (Month to Month)</h4>
<div class="no-print d-flex gap-2"><a href="{{ route('order.report') }}" class="btn btn-outline-secondary">Back</a><a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a><button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button></div>
</div></div>
<div class="app-content"><div class="container-fluid">
<div class="card report-filters no-print"><div class="card-body">
<form method="GET" action="{{ route('reports.monthly-products', ['dimension' => $dimension]) }}" class="row g-3 align-items-end">
<div class="col-md-2"><label class="form-label" for="source">Data Source</label><select id="source" name="source" class="form-select"><option value="tally" @selected($source === 'tally')>Tally Sales</option><option value="orders" @selected($source === 'orders')>App Orders</option></select></div>
@foreach(['from_month' => 'From Month', 'to_month' => 'To Month'] as $field => $caption)
<div class="col-md-2"><label class="form-label" for="{{ $field }}">{{ $caption }} <span class="text-danger">*</span></label><input required type="month" id="{{ $field }}" name="{{ $field }}" class="form-control" value="{{ $field === 'from_month' ? $from->format('Y-m') : $to->format('Y-m') }}"></div>
@endforeach
@php
$selects = ['state_id' => ['State', $states, 'name']];
if ($dimension === 'party') $selects['party_key'] = ['Party', $parties, 'name'];
if ($dimension === 'employee') $selects['user_id'] = ['Employee', $employees, 'name'];
$selects['product_category_id'] = ['Product Category', $categories, 'name'];
$selects['product_key'] = ['Product / Packing', $products, 'name'];
@endphp
@foreach($selects as $field => [$caption, $options, $nameField])
<div class="col-md-2"><label class="form-label" for="{{ $field }}">{{ $caption }}</label><select id="{{ $field }}" name="{{ $field }}" class="form-select"><option value="">All</option>@foreach($options as $option)<option value="{{ $option->id }}" @selected(request($field) == $option->id)>{{ $option->$nameField ?: $option->name ?: 'Unnamed' }}</option>@endforeach</select></div>
@endforeach
<div class="col-auto"><button class="btn btn-primary">View Report</button> <a class="btn btn-outline-secondary" href="{{ route('reports.monthly-products', ['dimension' => $dimension, 'source' => $source]) }}">Reset</a></div>
</form>
@if($errors->any())<div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>@endif
</div></div>
<div class="row g-3 mb-3">
@foreach([['Total Quantity', $report['total']['qty'], 'primary'], [$source === 'tally' ? 'Total Sales Value' : 'Total Order Value', $report['total']['value'], 'success'], ['Total '.($dimension === 'party' ? 'Parties' : ($dimension === 'employee' ? 'Employees' : 'States')), count($report['groups']), 'warning'], ['Total Products', $report['product_count'], 'info']] as [$caption, $value, $color])
<div class="col-md-3 col-6"><div class="card metric bg-{{ $color }}-subtle"><div class="card-body"><div>{{ $caption }}</div><h4 class="text-{{ $color }} mb-0">{{ in_array($caption, ['Total Sales Value', 'Total Order Value']) ? '₹ ' : '' }}{{ \App\Services\MonthlyProductReport::formatNumber($value, $caption === 'Total Quantity' ? 2 : (in_array($caption, ['Total Sales Value', 'Total Order Value']) ? 2 : 0)) }}</h4></div></div></div>
@endforeach
</div>
<div class="card"><div class="card-body p-0 table-responsive"><table class="table table-bordered report-table mb-0">
<thead><tr><th rowspan="2">{{ $label }} Name</th><th rowspan="2">Product Name</th>@foreach($months as $month)<th colspan="2">{{ $month }}</th>@endforeach<th colspan="2" class="total-heading">Total</th></tr><tr>@foreach($months as $month)<th>Qty</th><th>Value</th>@endforeach<th class="total-heading">Total Qty</th><th class="total-heading">Total Value</th></tr></thead>
<tbody>
@forelse($report['groups'] as $group)
@php($groupIndex = $loop->index)
<tr class="group-row"><td colspan="2"><button type="button" class="btn btn-sm btn-primary py-0 me-2 no-print group-toggle" data-group="{{ $groupIndex }}" aria-expanded="false" aria-label="Toggle products for {{ $group['name'] }}">+</button>{{ $group['name'] }}</td>@include('admin.reports.partials.monthly-product-cells', ['row' => $group])</tr>
@foreach($group['products'] as $product)
<tr data-products="{{ $groupIndex }}" hidden><td></td><td class="product-name">{{ $product['name'] }}</td>@include('admin.reports.partials.monthly-product-cells', ['row' => $product])</tr>
@endforeach
@empty<tr><td colspan="{{ 4 + count($months) * 2 }}" class="text-center py-4 text-muted">No {{ $sourceLabel }} found for these filters.</td></tr>@endforelse
</tbody>
<tfoot><tr><td colspan="2">Grand Total</td>@include('admin.reports.partials.monthly-product-cells', ['row' => $report['total']])</tr></tfoot>
</table></div></div>
</div></div></main>
@endsection
@push('scripts')
<script>
document.querySelector('.monthly-products #source').addEventListener('change', function () {
    const form = this.form;
    ['party_key', 'product_key'].forEach(name => {
        const select = form.elements.namedItem(name);
        if (select) select.value = '';
    });
    form.requestSubmit();
});
document.querySelectorAll('.monthly-products .group-toggle').forEach(button => {
    button.addEventListener('click', () => {
        const expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!expanded));
        button.textContent = expanded ? '+' : '−';
        document.querySelectorAll('.monthly-products [data-products="' + button.dataset.group + '"]').forEach(row => row.hidden = expanded);
    });
});
</script>
@endpush
