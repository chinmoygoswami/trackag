@extends('admin.layout.layout')
@section('title', 'Farmer Orders | Trackag')

@section('content')
<main class="app-main">
    <div class="app-content-header"><div class="container-fluid"><h3 class="mb-0">Farmer Orders</h3></div></div>
    <div class="app-content"><div class="container-fluid">
        <div class="card card-primary card-outline">
            <div class="card-header"><h3 class="card-title">Separate Farmer Order Management</h3></div>
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('farmer-order.index') }}" class="row g-2">
                    <div class="col-md-2"><label>From date</label><input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control"></div>
                    <div class="col-md-2"><label>To date</label><input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control"></div>
                    <div class="col-md-2"><label>Employee</label><select name="employee_id" class="form-select"><option value="">All</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label>Farmer</label><select name="farmer_id" class="form-select"><option value="">All</option>@foreach($farmers as $farmer)<option value="{{ $farmer->id }}" @selected(request('farmer_id') == $farmer->id)>{{ $farmer->farmer_name }} {{ $farmer->village ? '('.$farmer->village.')' : '' }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label>Status</label><select name="status" class="form-select"><option value="">All</option>@foreach(['pending','approved','hold','rejected','part_dispatched','dispatched'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>
                    <div class="col-md-1 align-self-end"><button class="btn btn-primary w-100">Filter</button></div>
                </form>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead><tr><th>Date / Order</th><th>Farmer</th><th>Employee</th><th>Payment</th><th>Items</th><th>Total</th><th>Status / Review</th><th>Dispatch</th></tr></thead>
                    <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>{{ $order->created_at->format('d-m-Y') }}<br><strong>{{ $order->order_no }}</strong></td>
                            <td>{{ $order->farmer->farmer_name ?? 'Farmer removed' }}<br><small>{{ $order->farmer->mobile_no ?? '' }} {{ $order->farmer->village ? '· '.$order->farmer->village : '' }}</small></td>
                            <td>{{ $order->user->name ?? '-' }}<br><small>{{ $order->user->state->name ?? '' }}</small></td>
                            <td>{{ ucfirst($order->order_type) }}</td>
                            <td><ul class="mb-0 ps-3">@foreach($order->items as $item)<li>{{ $item->product->product_name ?? 'Product' }} {{ $item->packing ? '· '.$item->packing->packing_value.' '.$item->packing->packing_size : '' }} — Qty {{ $item->qty }}, shipper {{ $item->shipper_size ?? 0 }}</li>@endforeach</ul></td>
                            <td>₹{{ number_format((float) $order->items->sum('grand_total'), 2) }}</td>
                            <td>
                                <strong>{{ ucwords(str_replace('_',' ',$order->status)) }}</strong>
                                @if(in_array($order->status, ['pending','hold']))
                                    <form method="POST" action="{{ route('farmer-order.status.update') }}" class="farmer-status-form mt-2">
                                        @csrf<input type="hidden" name="farmer_order_id" value="{{ $order->id }}">
                                        <select name="status" class="form-select form-select-sm mb-1"><option value="approved">Approve</option>@if($order->status === 'pending')<option value="hold">Hold</option><option value="rejected">Reject</option>@endif</select>
                                        <input name="remark" class="form-control form-control-sm mb-1" placeholder="Remark for hold / reject">
                                        <button class="btn btn-sm btn-primary">Save status</button>
                                    </form>
                                @endif
                                @if($order->remark2)<small class="d-block mt-1">Review note: {{ $order->remark2 }}</small>@endif
                            </td>
                            <td>
                                @if(in_array($order->status, ['approved','part_dispatched']))
                                    <form method="POST" action="{{ route('farmer-order.dispatch.store') }}" enctype="multipart/form-data" class="farmer-dispatch-form">
                                        @csrf<input type="hidden" name="farmer_order_id" value="{{ $order->id }}">
                                        @foreach($order->items as $index => $item)
                                            @php($sent = (int) $item->dispatches->sum('dispatch_qty'))
                                            <label class="small d-block mt-1">{{ $item->product->product_name ?? 'Item' }} · remaining {{ max(0, (int) $item->shipper_size - $sent) }}</label>
                                            <input type="hidden" name="dispatch_items[{{ $index }}][item_id]" value="{{ $item->id }}">
                                            <input type="number" min="0" max="{{ max(0, (int) $item->shipper_size - $sent) }}" name="dispatch_items[{{ $index }}][dispatch_qty]" value="0" class="form-control form-control-sm mb-1">
                                        @endforeach
                                        <input name="lr_number" class="form-control form-control-sm mb-1" placeholder="LR number">
                                        <input name="transport_name" class="form-control form-control-sm mb-1" placeholder="Transport">
                                        <input name="vehicle_no" class="form-control form-control-sm mb-1" placeholder="Vehicle number">
                                        <input type="file" name="dispatch_image" accept="image/jpeg,image/png" class="form-control form-control-sm mb-1">
                                        <button class="btn btn-sm btn-success">Save dispatch</button>
                                    </form>
                                @else
                                    <span class="text-muted">Approve order to dispatch</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">No farmer orders found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div></div>
</main>
@endsection

@push('scripts')
<script>
$(function () {
    $('.farmer-status-form, .farmer-dispatch-form').on('submit', function (event) {
        event.preventDefault();
        const form = this;
        $.ajax({url: form.action, method: 'POST', data: new FormData(form), processData: false, contentType: false})
            .done(function (response) { alert(response.message || 'Saved'); window.location.reload(); })
            .fail(function (xhr) { alert(xhr.responseJSON?.message || 'Unable to save farmer order changes.'); });
    });
});
</script>
@endpush
