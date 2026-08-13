@extends('admin.layout.layout')
@section('title', 'Partywise Opening & Closing | Trackag')

@section('content')
<main class="app-main">
    <div class="app-content-header py-3 bg-light border-bottom">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-primary"><i class="bi bi-wallet2 me-2"></i>Partywise Opening & Closing</h3>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Partywise Opening & Closing</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content py-4">
        <div class="container-fluid px-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 text-primary fw-semibold">Partywise Opening & Closing Details</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover table-striped align-middle">
                        <thead class="table-light text-uppercase" style="font-size: 13px;">
                            <tr>
                                <th>Sr. No.</th>
                                <th>Master ID</th>
                                <th>Date</th>
                                <th>Party Name</th>
                                <th>Opening Balance Amt.</th>
                                <th>Debit Amt.</th>
                                <th>Credit Amt.</th>
                                <th>Closing Balance Amt.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $records = \App\Models\TallyOpeningClosing::orderBy('id', 'desc')->paginate(15);
                            @endphp
                            @forelse($records as $index => $record)
                            <tr>
                                <td>{{ $records->firstItem() + $index }}</td>
                                <td>{{ $record->master_id }}</td>
                                <td>{{ $record->date ? $record->date->format('d-m-Y') : '' }}</td>
                                <td>{{ $record->party_name }}</td>
                                <td>{{ number_format($record->opening_balance_amt, 2) }}</td>
                                <td>{{ number_format($record->debit_amt, 2) }}</td>
                                <td>{{ number_format($record->credit_amt, 2) }}</td>
                                <td>{{ number_format($record->closing_balance_amt, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No data available</td>
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
