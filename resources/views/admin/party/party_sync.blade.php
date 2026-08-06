@extends('admin.layout.layout')
@section('title', 'Party Sync | Trackag')

@section('content')
<main class="app-main">
    <div class="app-content-header py-3 bg-light border-bottom">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-primary"><i class="bi bi-arrow-repeat me-2"></i>Party Sync</h3>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Party Sync</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content py-4">
        <div class="container-fluid px-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 text-primary fw-semibold">Party Sync Details</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover table-striped align-middle">
                        <thead class="table-light text-uppercase" style="font-size: 13px;">
                            <tr>
                                <th>Sr. No.</th>
                                <th>Group Name</th>
                                <th>Party Name</th>
                                <th>Phone 1</th>
                                <th>Phone 2</th>
                                <th>Contact Person</th>
                                <th>State</th>
                                <th>District</th>
                                <th>GST No</th>
                                <th>Party Create Date</th>
                                <th>Address</th>
                                <th>Email</th>
                                <th>PAN No</th>
                                <th>Credit Days</th>
                                <th>Credit Limit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $records = \App\Models\TallyPartySync::orderBy('id', 'desc')->paginate(15);
                            @endphp
                            @forelse($records as $index => $record)
                            <tr>
                                <td>{{ $records->firstItem() + $index }}</td>
                                <td>{{ $record->group_name }}</td>
                                <td>{{ $record->party_name }}</td>
                                <td>{{ $record->phone_1 }}</td>
                                <td>{{ $record->phone_2 }}</td>
                                <td>{{ $record->contact_person_name }}</td>
                                <td>{{ $record->state }}</td>
                                <td>{{ $record->district }}</td>
                                <td>{{ $record->gst_no }}</td>
                                <td>{{ $record->party_create_date ? $record->party_create_date->format('d-m-Y') : '' }}</td>
                                <td>{{ $record->address }}</td>
                                <td>{{ $record->email }}</td>
                                <td>{{ $record->pan_no }}</td>
                                <td>{{ $record->credit_days }}</td>
                                <td>{{ $record->credit_limit }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="15" class="text-center text-muted py-4">No data available</td>
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
