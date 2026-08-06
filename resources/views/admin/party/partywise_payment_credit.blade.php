@extends('admin.layout.layout')
@section('title', 'Partywise Payment Credit | Trackag')

@section('content')
<main class="app-main">
    <div class="app-content-header py-3 bg-light border-bottom">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-primary"><i class="bi bi-currency-rupee me-2"></i>Partywise Payment Credit</h3>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Partywise Payment Credit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content py-4">
        <div class="container-fluid px-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 text-primary fw-semibold">Partywise Payment Credit Details</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover table-striped align-middle">
                        <thead class="table-light text-uppercase" style="font-size: 13px;">
                            <tr>
                                <th>Sr. No.</th>
                                <th>Party Name</th>
                                <th>Payment Date</th>
                                <th>Payment Mode</th>
                                <th>Credit Amount</th>
                                <th>Voucher No</th>
                                <th>Voucher Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $records = \App\Models\TallyPartywisePaymentCredit::orderBy('id', 'desc')->paginate(15);
                            @endphp
                            @forelse($records as $index => $record)
                            <tr>
                                <td>{{ $records->firstItem() + $index }}</td>
                                <td>{{ $record->party_name }}</td>
                                <td>{{ $record->payment_date ? $record->payment_date->format('d-m-Y') : '' }}</td>
                                <td>{{ $record->payment_mode }}</td>
                                <td>{{ number_format($record->credit_amount, 2) }}</td>
                                <td>{{ $record->voucher_no }}</td>
                                <td>{{ $record->voucher_type }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No data available</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-3">
                        {{ $records->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
