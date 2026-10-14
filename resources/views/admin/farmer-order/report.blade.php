@extends('admin.layout.layout')
@section('title', 'Farmer Order Report | Trackag')

@section('content')
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6"><h3 class="mb-0">Farmer Order Report</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item">Farmer Order</li>
                        <li class="breadcrumb-item active">Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Farmer Order Report List</h3></div>
                <div class="card-body border-bottom">
                    <form method="GET" action="{{ route('farmer-order.report') }}">
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
                                <label class="form-label">Farmer</label>
                                <select name="farmer_id" class="form-select">
                                    <option value="">All</option>
                                    @foreach($farmers as $farmer)
                                        <option value="{{ $farmer->id }}" @selected(request('farmer_id') == $farmer->id)>
                                            {{ $farmer->farmer_name }}
                                        </option>
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
                                <a href="{{ route('farmer-order.report') }}" class="btn btn-secondary w-100"><i class="bi bi-arrow-clockwise me-1"></i>Reset</a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" formaction="{{ route('farmer-order.report.export') }}" class="btn btn-success w-100">
                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card-body table-responsive">
                    <table id="farmer-order-report-table" class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Create Date</th>
                                <th>State Name</th>
                                <th>Order Type</th>
                                <th>Order No</th>
                                <th>Farmer Name</th>
                                <th>Farmer Contact / Location</th>
                                <th>Emp Name</th>
                                <th>Product Details</th>
                                <th>Delivery / Remarks</th>
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
                                    <td>{{ $order->farmer->farmer_name ?? '-' }}</td>
                                    <td>
                                        <div>{{ $order->farmer->mobile_no ?? '-' }}</div>
                                        <div class="small text-muted">{{ $order->farmer->village ?? '-' }}</div>
                                    </td>
                                    <td>{{ $order->user->name ?? '-' }}</td>
                                    <td>
                                        @forelse($order->items as $item)
                                            <div class="border rounded p-2 mb-2">
                                                <div class="fw-semibold">{{ $item->product->product_name ?? 'Product unavailable' }}</div>
                                                <div class="small text-muted">
                                                    Packing: {{ trim(($item->packing->packing_value ?? '').' '.($item->packing->packing_size ?? '')) ?: '-' }}
                                                    <span class="mx-1">|</span>
                                                    Qty: {{ $item->qty }}
                                                    <span class="mx-1">|</span>
                                                    Unit Price: {{ number_format((float) $item->price, 2) }}
                                                </div>
                                                <div class="small">
                                                    Amount: {{ number_format((float) $item->total_price, 2) }}
                                                    <span class="mx-1">|</span>
                                                    Discount: {{ number_format((float) $item->discount, 2) }}
                                                    <span class="mx-1">|</span>
                                                    GST: {{ number_format((float) $item->gst, 2) }}
                                                    <span class="mx-1">|</span>
                                                    Total: <strong>{{ number_format((float) $item->grand_total, 2) }}</strong>
                                                </div>
                                            </div>
                                        @empty
                                            -
                                        @endforelse
                                    </td>
                                    <td>
                                        <div><strong>Place:</strong> {{ $order->delivery_place ?: '-' }}</div>
                                        <div><strong>Transport:</strong> {{ $order->preferred_transport ?: '-' }}</div>
                                        @if($order->remark)
                                            <div class="small"><strong>Remark:</strong> {{ $order->remark }}</div>
                                        @endif
                                        @if($order->remark2)
                                            <div class="small"><strong>Back-office:</strong> {{ $order->remark2 }}</div>
                                        @endif
                                    </td>
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
        $('#farmer-order-report-table').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            language: { emptyTable: 'No approved farmer orders found.' }
        });
    });
</script>
@endpush
