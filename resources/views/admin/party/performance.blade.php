@extends('admin.layout.layout')
@section('title', 'Party Performance | Trackag')

@section('content')
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
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-primary text-white shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Total Sales</h6>
                                    <h3 class="mb-0">₹{{ number_format($performanceData->sum('total_sales'), 2) }}</h3>
                                </div>
                                <div class="fs-1 text-white-50"><i class="bi bi-cart-check"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-success text-white shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Total Payments Received</h6>
                                    <h3 class="mb-0">₹{{ number_format($performanceData->sum('total_payment'), 2) }}</h3>
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
                                    <h6 class="text-white-50 mb-1">Total Closing Balance</h6>
                                    <h3 class="mb-0">₹{{ number_format($performanceData->sum('closing_balance'), 2) }}</h3>
                                </div>
                                <div class="fs-1 text-white-50"><i class="bi bi-wallet2"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-info text-white shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Total Items Sold (Qty)</h6>
                                    <h3 class="mb-0">{{ number_format($performanceData->sum('total_qty')) }}</h3>
                                </div>
                                <div class="fs-1 text-white-50"><i class="bi bi-boxes"></i></div>
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
                    <table id="data-table" class="table table-bordered table-hover table-striped align-middle">
                        <thead class="table-light text-uppercase" style="font-size: 13px;">
                            <tr>
                                <th>#</th>
                                <th>Master ID</th>
                                <th>Party Name</th>
                                <th>State</th>
                                <th class="text-end">Total Qty</th>
                                <th class="text-end">Total Sales (₹)</th>
                                <th class="text-end">Total Payment (₹)</th>
                                <th class="text-end">Closing Balance (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($performanceData as $index => $record)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $record->master_id }}</td>
                                <td>{{ $record->party_name }}</td>
                                <td>{{ $record->state ?? '-' }}</td>
                                <td class="text-end">{{ number_format($record->total_qty) }}</td>
                                <td class="text-end text-primary fw-bold">{{ number_format($record->total_sales, 2) }}</td>
                                <td class="text-end text-success fw-bold">{{ number_format($record->total_payment, 2) }}</td>
                                <td class="text-end text-danger fw-bold">{{ number_format($record->closing_balance, 2) }}</td>
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
<script>
$(document).ready(function() {
    $('#data-table').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 50,
        lengthMenu: [15, 25, 50, 100],
        order: [[5, 'desc']] // Order by total sales by default
    });
});
</script>
@endpush
