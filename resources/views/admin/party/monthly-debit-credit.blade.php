@extends('admin.layout.layout')

@php
    $amountFormatter = new \NumberFormatter('en_IN', \NumberFormatter::DECIMAL);
    $amountFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
    $amountFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
@endphp

@section('title', $title.' | Trackag')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
<style>
    #monthly-party-report-table th.dtfc-fixed-left,
    #monthly-party-report-table td.dtfc-fixed-left {
        background-color: #fff !important;
        z-index: 1;
    }

    #monthly-party-report-table thead th.dtfc-fixed-left {
        background-color: #f8f9fa !important;
    }
</style>
@endpush

@section('content')
<main class="app-main">
    <div class="app-content-header py-3 bg-light border-bottom">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h3 class="mb-0 text-primary"><i class="bi bi-bar-chart-fill me-2"></i>{{ $title }}</h3>
                </div>
                <div class="col-sm-5 text-sm-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item">Party Reports</li>
                        <li class="breadcrumb-item active">{{ $title }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content py-4">
        <div class="container-fluid px-4">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                <div class="btn-group" role="group" aria-label="Monthly report type">
                    <a href="{{ route('party.monthly.employee-report', ['financial_year' => $financialYear]) }}"
                       class="btn {{ $groupBy === 'employee' ? 'btn-primary' : 'btn-outline-primary' }}">Employee-wise</a>
                    <a href="{{ route('party.monthly.state-report', ['financial_year' => $financialYear]) }}"
                       class="btn {{ $groupBy === 'state' ? 'btn-primary' : 'btn-outline-primary' }}">State-wise</a>
                </div>

                <form method="GET" action="{{ $groupBy === 'employee' ? route('party.monthly.employee-report') : route('party.monthly.state-report') }}"
                      class="d-flex gap-2 align-items-center">
                    <label for="financial_year" class="form-label mb-0 text-nowrap">Financial Year</label>
                    <select id="financial_year" name="financial_year" class="form-select" onchange="this.form.submit()">
                        @foreach($financialYears as $year)
                            <option value="{{ $year }}" @selected((int) $financialYear === (int) $year)>
                                {{ $year }}-{{ substr((string) ($year + 1), -2) }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-1 text-primary fw-semibold">{{ $title }}</h5>
                    <div class="text-muted small">Debit Bill is summed from Tally sales bills; Payment is summed from Tally party payment credits.</div>
                </div>
                <div class="card-body table-responsive">
                    <table id="monthly-party-report-table" class="table table-bordered table-striped table-hover align-middle text-nowrap">
                        <thead class="text-center">
                            <tr>
                                <th rowspan="2" class="align-middle">Sr</th>
                                <th rowspan="2" class="align-middle">{{ $groupBy === 'employee' ? 'Employee Name' : 'State' }}</th>
                                @if($groupBy === 'employee')
                                    <th rowspan="2" class="align-middle">State</th>
                                @endif
                                @foreach($months as $month)
                                    <th colspan="2" class="bg-warning-subtle">{{ $month->format('F') }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach($months as $month)
                                    <th class="bg-light">Debit Bill</th>
                                    <th class="bg-light">Payment</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $index => $row)
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    @if($groupBy === 'employee')
                                        <td>{{ $row['state'] ?: '-' }}</td>
                                    @endif
                                    @foreach($months as $month)
                                        @php($monthData = $row['monthly'][$month->format('Y-m')] ?? ['debit' => 0, 'payment' => 0])
                                        <td class="text-end">{{ $amountFormatter->format($monthData['debit']) }}</td>
                                        <td class="text-end">{{ $amountFormatter->format($monthData['payment']) }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + ($groupBy === 'employee' ? 1 : 0) + ($months->count() * 2) }}" class="text-center text-muted py-4">
                                        No active linked parties or monthly Tally entries found for this financial year.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
<script>
    $(function () {
        $('#monthly-party-report-table').DataTable({
            responsive: false,
            scrollX: true,
            autoWidth: false,
            pageLength: 50,
            lengthMenu: [15, 25, 50, 100],
            order: [],
            fixedColumns: {
                left: {{ $groupBy === 'employee' ? 3 : 2 }}
            }
        });
    });
</script>
@endpush
