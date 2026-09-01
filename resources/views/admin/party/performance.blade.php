@extends('admin.layout.layout')
@php
    if (!isset($fmt2)) {
        $fmt2 = new \NumberFormatter('en_IN', \NumberFormatter::DECIMAL);
        $fmt2->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $fmt2->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
    }
    if (!isset($fmt0)) {
        $fmt0 = new \NumberFormatter('en_IN', \NumberFormatter::DECIMAL);
        $fmt0->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $fmt0->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
    }
@endphp

@section('title', 'Party Performance | Trackag')

@section('content')
@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
<style>
    /* Ensure fixed columns have a solid background */
    th.dtfc-fixed-left, td.dtfc-fixed-left {
        background-color: #fff !important;
        z-index: 1;
    }
    thead tr:nth-child(1) th.dtfc-fixed-left {
        background-color: #f8f9fa !important;
    }
</style>
@endpush
<main class="app-main">
    <div class="app-content-header py-3 bg-light border-bottom">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-primary"><i class="bi bi-bar-chart-fill me-2"></i>Party Performance</h3>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Party Performance</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content py-4">
        <div class="container-fluid px-4">
            
            <!-- Summary Widgets -->
            @php
                $totalOpening = $performanceData->sum('opening_balance');
                $totalCredit = $performanceData->sum('credit_amt');
                $totalDebit = $performanceData->sum('debit_amt');
                $totalClosing = $performanceData->sum('closing_balance');
            @endphp
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-primary text-white shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Total Opening Balance</h6>
                                    <h3 class="mb-0">₹{{ $fmt2->format($totalOpening) }}</h3>
                                </div>
                                <div class="fs-1 text-white-50"><i class="bi bi-wallet-fill"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-success text-white shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Total Credit (Payment)</h6>
                                    <h3 class="mb-0">₹{{ $fmt2->format($totalCredit) }}</h3>
                                </div>
                                <div class="fs-1 text-white-50"><i class="bi bi-cash-coin"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-warning text-white shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Total Debit (Bill)</h6>
                                    <h3 class="mb-0">₹{{ $fmt2->format($totalDebit) }}</h3>
                                </div>
                                <div class="fs-1 text-white-50"><i class="bi bi-cart-check"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-info text-white shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Total Closing Balance</h6>
                                    <h3 class="mb-0">₹{{ $fmt2->format($totalClosing) }}</h3>
                                </div>
                                <div class="fs-1 text-white-50"><i class="bi bi-wallet2"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 text-primary fw-semibold">Party Performance Details</h5>
                </div>
                <div class="card-body table-responsive">
                    <table id="data-table" class="table table-bordered table-hover table-striped align-middle" style="white-space: nowrap;">
                        <thead class="table-light text-center" style="font-size: 13px; vertical-align: middle;">
                            <tr>
                                <th rowspan="2" class="align-middle border-end text-start">Party Name(Shop Name)</th>
                                <th rowspan="2" class="align-middle border-end">Employee Name</th>
                                <th rowspan="2" class="align-middle border-end">State</th>
                                <th rowspan="2" class="align-middle border-end">Opening</th>
                                <th rowspan="2" class="align-middle border-end">Credit</th>
                                <th rowspan="2" class="align-middle border-end">Debit</th>
                                <th rowspan="2" class="align-middle border-end">Closing</th>
                                @foreach($uniqueMonths as $ym)
                                    <th colspan="2" class="border-end" style="background-color: #e9ecef;">{{ \Carbon\Carbon::parse($ym . '-01')->format("M'y") }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach($uniqueMonths as $ym)
                                    <th class="border-end text-success">Credit(Payment)</th>
                                    <th class="border-end text-danger">Debit (Bill)</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($performanceData as $index => $record)
                            <tr class="text-center">
                                <td class="text-start border-end fw-medium" style="background-color: #f8f9fa;">{{ $record->party_name }}</td>
                                <td class="border-end fw-medium">{{ $record->employee_name }}</td>
                                <td class="border-end">{{ $record->state ?? '-' }}</td>
                                <td class="border-end text-nowrap">{{ $fmt2->format($record->opening_balance) }}</td>
                                <td class="border-end text-nowrap">{{ $fmt2->format($record->credit_amt) }}</td>
                                <td class="border-end text-nowrap">{{ $fmt2->format($record->debit_amt) }}</td>
                                <td class="border-end text-nowrap">{{ $fmt2->format($record->closing_balance) }}</td>
                                @foreach($uniqueMonths as $ym)
                                    <td class="border-end text-success">{{ $fmt2->format($record->monthly[$ym]['credit']) }}</td>
                                    <td class="border-end text-danger">{{ $fmt2->format($record->monthly[$ym]['debit']) }}</td>
                                @endforeach
                            </tr>
                            @endforeach
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
$(document).ready(function() {
    $('#data-table').DataTable({
        responsive: false,
        scrollX: true,
        autoWidth: false,
        pageLength: 50,
        lengthMenu: [15, 25, 50, 100],
        order: [], // Disable initial sort due to complex headers
        fixedColumns: {
            left: 7
        }
    });
});
</script>
@endpush
