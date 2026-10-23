@extends('admin.layout.layout')
@section('title', 'Order Report | Trackag')

@section('content')
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6"><h3 class="mb-0">Order Report</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item">Order</li>
                        <li class="breadcrumb-item active">Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Order Report List</h3></div>
                <div class="card-body border-bottom">
                    <form method="GET" action="{{ route('order.report') }}">
                        <div class="row g-2">
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">State</label>
                                <select name="state_id" class="form-select">
                                    <option value="">All</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->id }}" @selected(request('state_id') == $state->id)>{{ $state->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Employee</label>
                                <select name="user_id" class="form-select">
                                    <option value="">All</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Party</label>
                                <select name="party_id" class="form-select">
                                    <option value="">All</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" @selected(request('party_id') == $customer->id)>{{ $customer->agro_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Order Type</label>
                                <select name="order_type" class="form-select">
                                    <option value="">All</option>
                                    <option value="cash" @selected(request('order_type') === 'cash')>Cash</option>
                                    <option value="debit" @selected(request('order_type') === 'debit')>Debit</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Order No</label>
                                <input type="text" name="order_no" class="form-control" value="{{ request('order_no') }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <a href="{{ route('order.report') }}" class="btn btn-secondary w-100"><i class="bi bi-arrow-clockwise me-1"></i>Reset</a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" formaction="{{ route('order.report.export') }}" class="btn btn-success w-100">
                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card-body table-responsive">
                    <table id="order-report-table" class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Create Date</th>
                                <th>State Name</th>
                                <th>Order Type</th>
                                <th>Order No</th>
                                <th>Party Name</th>
                                <th>Emp Name</th>
                                <th class="text-end">Amount</th>
                                <th>Order Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                                <tr>
                                    <td data-order="{{ $order->created_at->timestamp }}">{{ $order->created_at->format('d-m-Y') }}</td>
                                    <td>{{ $order->user->state->name ?? '-' }}</td>
                                    <td>{{ ucfirst($order->order_type) }}</td>
                                    <td>{{ $order->order_no }}</td>
                                    <td>{{ $order->customer->agro_name ?? '-' }}</td>
                                    <td>{{ $order->user->name ?? '-' }}</td>
                                    <td class="text-end">{{ number_format($order->items->sum('total_price'), 2) }}</td>
                                    <td>{{ ucwords(str_replace('_', ' ', $order->status)) }}</td>
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
    $(function () {
        $('#order-report-table').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                emptyTable: 'No approved orders found.'
            }
        });
    });
</script>
@endpush
