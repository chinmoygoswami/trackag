@extends('admin.layout.layout')
@section('title', 'Farmer Order List | Trackag')

@section('content')

    <main class="app-main">

        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">

                    <div class="col-sm-6">
                        <h3 class="mb-0">Farmer Order List</h3>
                    </div>

                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item">Farmer Order Master</li>
                            <li class="breadcrumb-item active">Farmer Orders</li>
                        </ol>
                    </div>

                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">

                <div class="card card-primary card-outline">

                    <div class="card-header">
                        <h3 class="card-title">Farmer Order Management</h3>
                    </div>
                    <div class="card-body border-bottom">

                        @php
                            $extraFilterKeys = ['product', 'district_id', 'taluka_id', 'village', 'order_type', 'order_no', 'depo_id'];
                            $showExtraFilters = collect($extraFilterKeys)->contains(fn ($key) => request()->filled($key));
                        @endphp

                        <form method="GET" action="{{ route('farmer-order.index') }}">
                            <div class="row g-2 align-items-end">
                                <div class="col-6 col-md-4 col-xl-2">
                                    <label class="form-label mb-1 small">From Date</label>
                                    <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}">
                                </div>

                                <div class="col-6 col-md-4 col-xl-2">
                                    <label class="form-label mb-1 small">To Date</label>
                                    <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}">
                                </div>

                                <div class="col-6 col-md-4 col-xl-2">
                                    <label class="form-label mb-1 small">State</label>
                                    <select name="state_id" class="form-select form-select-sm">
                                        <option value="">All</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state->id }}" {{ request('state_id') == $state->id ? 'selected' : '' }}>{{ $state->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-6 col-md-4 col-xl-2">
                                    <label class="form-label mb-1 small">Sales Person</label>
                                    <select name="user_id" class="form-select form-select-sm">
                                        <option value="">All</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-6 col-md-4 col-xl-2">
                                    <label class="form-label mb-1 small">Farmer</label>
                                    <select name="farmer_id" class="form-select form-select-sm">
                                        <option value="">All</option>
                                        @foreach($farmers as $farmer)
                                            <option value="{{ $farmer->id }}" {{ request('farmer_id') == $farmer->id ? 'selected' : '' }}>
                                                {{ $farmer->farmer_name }}{{ $farmer->village ? ' ('.$farmer->village.')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-6 col-md-4 col-xl-2">
                                    <label class="form-label mb-1 small">Status</label>
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="">All</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="hold" {{ request('status') == 'hold' ? 'selected' : '' }}>Hold</option>
                                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                        <option value="part_dispatched" {{ request('status') == 'part_dispatched' ? 'selected' : '' }}>Part Dispatched</option>
                                        <option value="dispatched" {{ request('status') == 'dispatched' ? 'selected' : '' }}>Dispatched</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-6 col-xl-2 d-flex gap-2">
                                    <button class="btn btn-primary btn-sm flex-fill">Filter</button>
                                    <a href="{{ route('farmer-order.index') }}" class="btn btn-secondary btn-sm flex-fill">Reset</a>
                                </div>

                                <div class="col-12 col-md-6 col-xl-2">
                                    <button class="btn btn-outline-primary btn-sm w-100" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#farmer-order-extra-filters" aria-expanded="{{ $showExtraFilters ? 'true' : 'false' }}"
                                        aria-controls="farmer-order-extra-filters">
                                        <i class="bi bi-sliders me-1"></i>More filters
                                    </button>
                                </div>
                            </div>

                            <div class="collapse {{ $showExtraFilters ? 'show' : '' }}" id="farmer-order-extra-filters">
                                <div class="row g-2 mt-1">
                                    <div class="col-6 col-md-4 col-xl-2">
                                        <label class="form-label mb-1 small">District</label>
                                        <select name="district_id" class="form-select form-select-sm">
                                            <option value="">All</option>
                                            @foreach($districts as $district)
                                                <option value="{{ $district->id }}" {{ request('district_id') == $district->id ? 'selected' : '' }}>{{ $district->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-6 col-md-4 col-xl-2">
                                        <label class="form-label mb-1 small">Taluka</label>
                                        <select name="taluka_id" class="form-select form-select-sm">
                                            <option value="">All</option>
                                            @foreach($talukas as $taluka)
                                                <option value="{{ $taluka->id }}" {{ request('taluka_id') == $taluka->id ? 'selected' : '' }}>{{ $taluka->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-6 col-md-4 col-xl-2">
                                        <label class="form-label mb-1 small">Village</label>
                                        <select name="village" class="form-select form-select-sm">
                                            <option value="">All</option>
                                            @foreach($villages as $village)
                                                <option value="{{ $village }}" {{ request('village') === $village ? 'selected' : '' }}>{{ $village }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-6 col-md-4 col-xl-2">
                                        <label class="form-label mb-1 small">Product</label>
                                        <input type="text" name="product" class="form-control form-control-sm" value="{{ request('product') }}" placeholder="Search product">
                                    </div>

                                    <div class="col-6 col-md-4 col-xl-2">
                                        <label class="form-label mb-1 small">Order Type</label>
                                        <select name="order_type" class="form-select form-select-sm">
                                            <option value="">All</option>
                                            <option value="cash" {{ request('order_type') == 'cash' ? 'selected' : '' }}>Cash</option>
                                            <option value="debit" {{ request('order_type') == 'debit' ? 'selected' : '' }}>Debit</option>
                                        </select>
                                    </div>

                                    <div class="col-6 col-md-4 col-xl-2">
                                        <label class="form-label mb-1 small">Order No</label>
                                        <input type="text" name="order_no" class="form-control form-control-sm" value="{{ request('order_no') }}">
                                    </div>

                                    <div class="col-6 col-md-4 col-xl-2">
                                        <label class="form-label mb-1 small">Depo</label>
                                        <select name="depo_id" class="form-select form-select-sm">
                                            <option value="">All</option>
                                            @foreach($depos as $depo)
                                                <option value="{{ $depo->id }}" {{ request('depo_id') == $depo->id ? 'selected' : '' }}>{{ $depo->depo_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </form>

                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table id="order-table" class="table table-bordered table-hover table-striped">

                                <thead>
                                    <tr>
                                        <th width="40"></th>
                                        <th>Date</th>
                                        <th>State</th>
                                        <th>Order Type</th>
                                        <th>Order No</th>
                                        <th>Farmer</th>
                                        <th>District</th>
                                        <th>Taluka</th>
                                        <th>Village</th>
                                        <th>Employee</th>
                                        <th>Total Qty</th>
                                        <th>Dispatched</th>
                                        <th>Pending</th>
                                        <th>Amount</th>
                                        <th width="160">Status</th>
                                        <!-- <th>Action</th> -->
                                    </tr>
                                </thead>

                                <tbody>

                                    @foreach($orders as $order)

                                    <tr>

                                        <td>
                                            <button class="btn btn-sm btn-info view-items-btn"
                                                data-id="{{ $order->id }}"
                                                data-status="{{ $order->status }}">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </td>

                                        <td>{{ $order->created_at->format('d-m-Y') }}</td>

                                        <td>{{ $order->user->state->name ?? '-' }}</td>

                                        <td>{{ $order->order_type }}</td>

                                        <td><strong>{{ $order->order_no }}</strong></td>

                                        <td>{{ $order->farmer->farmer_name ?? '-' }}</td>

                                        <td>{{ $order->farmer->district->name ?? '-' }}</td>

                                        <td>{{ $order->farmer->talukaDetail->name ?? '-' }}</td>

                                        <td>{{ $order->farmer->village ?? '-' }}</td>

                                        <td>{{ $order->user->name ?? '-' }}</td>

                                        @php
                                            $totQty = $order->items->sum('qty');
                                            $dispQty = $order->items->reduce(function($carry, $item) {
                                                return $carry + $item->dispatches->sum('dispatch_qty');
                                            }, 0);
                                            $pendQty = max(0, $totQty - $dispQty);
                                        @endphp
                                        <td><span class="badge bg-secondary">{{ $totQty }}</span></td>
                                        <td><span class="badge bg-info">{{ $dispQty }}</span></td>
                                        <td><span class="badge bg-warning text-dark">{{ $pendQty }}</span></td>

                                        <td>
                                            <span class="badge bg-success">
                                                ₹ {{ $order->items->sum('grand_total') }}
                                            </span>
                                        </td>

                                        <td>

                                            <select class="form-select form-select-sm status-change"
                                                data-id="{{ $order->id }}"
                                                {{ $order->status == 'rejected' ? 'disabled' : '' }}>

                                                {{-- Pending --}}
                                                @if($order->status == 'pending')
                                                    <option value="pending" selected>PENDING</option>
                                                @endif

                                                {{-- Hold --}}
                                                @if($order->status != 'approved' || $order->status == 'hold')
                                                    <option value="hold"
                                                        {{ $order->status == 'hold' ? 'selected' : '' }}
                                                        {{ $order->status != 'pending' ? 'disabled' : '' }}>
                                                        HOLD
                                                    </option>
                                                @endif

                                                {{-- Approved --}}
                                                <option value="approved"
                                                    {{ $order->status == 'approved' ? 'selected' : '' }}
                                                    {{ !in_array($order->status,['pending','hold']) ? 'disabled' : '' }}>
                                                    APPROVED
                                                </option>

                                                {{-- Rejected --}}
                                                <option value="rejected"
                                                    {{ $order->status == 'rejected' ? 'selected' : '' }}
                                                    {{ $order->status != 'pending' ? 'disabled' : '' }}>
                                                    REJECTED
                                                </option>

                                                {{-- Part Dispatch --}}
                                                <option value="part_dispatched"
                                                    {{ $order->status == 'part_dispatched' ? 'selected' : '' }}
                                                    {{ !in_array($order->status, ['approved', 'part_dispatched']) ? 'disabled' : '' }}>
                                                    PART DISPATCHED
                                                </option>

                                                {{-- Dispatch --}}
                                                <option value="dispatched"
                                                    {{ $order->status == 'dispatched' ? 'selected' : '' }}
                                                    {{ !in_array($order->status, ['approved', 'part_dispatched']) ? 'disabled' : '' }}>
                                                    DISPATCHED
                                                </option>

                                                </select>

                                        </td>



                                    </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>
                    </div>
                </div>

            </div>
        </div>

    </main>


    <!-- ORDER SUMMARY MODAL (Read Only) -->
    <div class="modal fade" id="orderItemsModal" tabindex="-1" aria-labelledby="orderItemsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderItemsModalLabel">Order Summary & Dispatch History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Order Summary -->
                    <div class="card mb-3 shadow-sm border-0">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 fw-bold">Order Summary</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label text-muted small fw-bold">Order ID</label>
                                    <div id="summary_order_no" class="fw-bold"></div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label text-muted small fw-bold">Status</label>
                                    <div id="summary_status"></div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label text-muted small fw-bold">Total Qty</label>
                                    <div id="summary_total_qty" class="fw-bold"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-muted small fw-bold">Dispatched Qty</label>
                                    <div id="summary_disp_qty" class="text-info fw-bold"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-muted small fw-bold">Pending Qty</label>
                                    <div id="summary_pend_qty" class="text-warning text-dark fw-bold"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Base Order Items (Editable if pending) -->
                    <div class="card mb-3 shadow-sm border-0" id="orderItemsTableCard" style="display:none;">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-list text-secondary me-2"></i>Order Items</h6>
                            <button class="btn btn-sm btn-primary" id="saveOrderItemsBtn" style="display:none;" data-order-id="">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-0 align-middle text-sm" id="baseItemsTable">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Packing</th>
                                            <th>Price</th>
                                            <th class="text-center" style="width: 120px;">Qty</th>
                                            <th>Total Price</th>
                                            <th>GST</th>
                                            <th>Grand Total</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="baseItemsBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Dispatch History -->
                    <div class="card mb-3 shadow-sm border-0" id="summaryHistoryCard" style="display:none;">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-history text-secondary me-2"></i>Dispatch History</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Date</th>
                                            <th>Transport / LR Number</th>
                                            <th>Image</th>
                                        </tr>
                                    </thead>
                                    <tbody id="summaryHistoryBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Dispatch Detailed Breakdown -->
                    <div id="dispatch_breakdown_container"></div>
                </div>
            </div>
        </div>
    </div>


    <!-- DISPATCH MODAL -->
    <div class="modal fade" id="dispatchModal" tabindex="-1" aria-labelledby="dispatchModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="dispatchModalLabel">Dispatch Management Panel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-light">
                    <form id="dispatchForm" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" id="dispatch_order_id" name="farmer_order_id">

                        <!-- Top LR Details Section -->
                        <div class="card mb-3 shadow-sm border-0">
                            <div class="card-header bg-white">
                                <h6 class="mb-0 fw-bold">Dispatch Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label text-muted small fw-bold">LR Number</label>
                                        <input type="text" name="lr_number" id="dispatch_lr_number" class="form-control" placeholder="Enter LR No">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-muted small fw-bold">Transport Name</label>
                                        <input type="text" name="transport_name" id="dispatch_transport_name" class="form-control" placeholder="Enter Transport">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-muted small fw-bold">Vehicle Number</label>
                                        <input type="text" name="vehicle_no" id="dispatch_vehicle_no" class="form-control" placeholder="Enter Vehicle No">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-muted small fw-bold">Dispatch Image</label>
                                        <input type="file" name="dispatch_image" id="dispatch_img_upload" class="form-control" accept="image/jpeg,image/png,image/jpg">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="card mb-3 shadow-sm border-0">
                            <div class="card-header bg-white">
                                <h6 class="mb-0 fw-bold">Order Items</h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0" id="dispatchItemsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th>Packing</th>
                                            <th>Price</th>
                                            <th>Order Qty</th>
                                            <th class="bg-primary text-white" style="width: 120px;">Dispatch Qty</th>
                                            <th>Pending Qty</th>
                                            <th>Total Price</th>
                                            <th>GST (%)</th>
                                            <th>Grand Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dispatchItemsBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Previous Dispatches -->
                        <div class="card shadow-sm border-0" id="previousDispatchesCard" style="display:none;">
                            <div class="card-header bg-white">
                                <h6 class="mb-0 fw-bold"><i class="fas fa-history text-secondary me-2"></i>Dispatch History</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Item</th>
                                                <th>Dispatch Qty</th>
                                                <th>LR Number</th>
                                                <th>Transport</th>
                                                <th>Vehicle</th>
                                                <th>Image</th>
                                            </tr>
                                        </thead>
                                        <tbody id="previousDispatchesBody">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning d-none text-dark fw-bold" id="btnPartDispatch">Part Dispatch</button>
                    <button type="button" class="btn btn-success d-none fw-bold" id="btnFullDispatch">Dispatch</button>
                </div>
            </div>
        </div>
    </div>


    <!-- STATUS MODAL -->

    <div class="modal fade" id="statusModal">

        <div class="modal-dialog">

            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" id="modal_order_id">

                    <div id="remark_box" style="display:none">

                        <label>Remark</label>

                        <textarea id="remark" class="form-control"></textarea>

                    </div>

                    <div id="dispatch_box" style="display:none">

                        <label>LR Number</label>

                        <input type="text" id="lr_number" name="lr_number" class="form-control mb-2">

                        <label>Transport Name</label>

                        <input type="text" id="transport_name" name="transport_name" class="form-control mb-2">

                        <label>Destination</label>

                        <input type="text" id="destination" name="destination" class="form-control mb-2">

                        <label>Dispatch Image <span class="text-danger">*</span></label>
                        <input type="file" id="dispatch_image" name="dispatch_image" class="form-control"
                            accept="image/jpeg,image/png,image/jpg">

                    </div>

                </div>

                <div class="modal-footer">

                    <button class="btn btn-primary" id="saveStatus">
                        Update
                    </button>

                </div>

            </div>
        </div>
    </div>

@endsection


@push('scripts')
    <script>

        $(function () {

            $('#order-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: false
            });

        });


        /* -----------------------
           ORDER ITEMS MODAL (READ ONLY)
        ----------------------- */

        $(document).on('click', '.view-items-btn', function (e) {
            e.preventDefault();
            let order_id = $(this).data('id');

            $('#orderItemsModal').modal('show');
            $('#dispatch_breakdown_container').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i> Loading details...</div>');
            $('#summaryHistoryBody').empty();
            $('#summaryHistoryCard').hide();

            $.ajax({
                url: `/admin/farmer-order/${order_id}/dispatch-data`,
                type: 'GET',
                success: function(res) {
                    if (res.status && res.items) {
                        $('#dispatch_breakdown_container').empty();
                        let items = res.items;
                        let order = res.order;
                        let dispatches = res.dispatches;

                        let totalQty = 0;
                        let totalDisp = 0;

                        let itemMap = {};
                        let baseBody = $('#baseItemsBody');
                        baseBody.empty();

                        let isPending = (order.status === 'pending');
                        if (isPending) {
                            $('#saveOrderItemsBtn').show().data('order-id', order.id);
                        } else {
                            $('#saveOrderItemsBtn').hide();
                        }

                        if (items.length > 0) {
                            $('#orderItemsTableCard').show();
                            $.each(items, function (index, item) {
                                totalQty += parseInt(item.order_qty) || 0;
                                totalDisp += item.dispatched_qty;
                                itemMap[item.id] = item;

                                let packingStr = (item.packing_value && item.packing) ? item.packing_value + ' ' + item.packing : '-';
                                let price = parseFloat(item.price) || 0;
                                let gstPercent = parseFloat(item.gst_percent ?? item.gst) || 0;
                                let discount = parseFloat(item.discount) || 0;
                                let amount = parseFloat(item.total_price);
                                if (!Number.isFinite(amount)) amount = price * item.order_qty;
                                let afterDiscount = amount - discount;
                                if (afterDiscount < 0) afterDiscount = 0;
                                let gstAmount = parseFloat(item.gst);
                                if (!Number.isFinite(gstAmount)) gstAmount = (afterDiscount * gstPercent) / 100;
                                let grandTotal = parseFloat(item.grand_total);
                                if (!Number.isFinite(grandTotal)) grandTotal = afterDiscount + gstAmount;

                                let qtyHtml = '';
                                let actionHtml = '';
                                if (isPending) {
                                    qtyHtml = `<input type="number" class="form-control form-control-sm text-center fw-bold text-primary border-primary bg-primary bg-opacity-10 live-item-qty" data-id="${item.id}" data-price="${price}" data-gst-percent="${gstPercent}" data-discount="${discount}" value="${item.order_qty || 1}" min="1">`;
                                    actionHtml = `<span class="badge bg-primary">Editable</span>`;
                                } else {
                                    qtyHtml = `<span class="badge bg-secondary px-3 py-2 fs-6 shadow-sm">${item.order_qty}</span>`;
                                    actionHtml = `<span class="badge bg-secondary">Readonly</span>`;
                                }

                                baseBody.append(`
                                    <tr id="base-row-${item.id}">
                                        <td class="fw-bold">${item.product || '-'}</td>
                                        <td>${packingStr}</td>
                                        <td>₹${price.toFixed(2)}</td>
                                        <td>${qtyHtml}</td>
                                        <td class="base-total-price">₹${amount.toFixed(2)}</td>
                                        <td>${gstPercent}% (<span class="base-gst-amt">₹${gstAmount.toFixed(2)}</span>)</td>
                                        <td class="fw-bold text-success fs-6 base-grand-total">₹${grandTotal.toFixed(2)}</td>
                                        <td class="text-center">${actionHtml}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            $('#orderItemsTableCard').hide();
                        }

                        // Set order summary
                        let badgeClass = 'bg-primary';
                        if(order.status === 'dispatched') badgeClass = 'bg-success';
                        else if(order.status === 'rejected') badgeClass = 'bg-danger';

                        $('#summary_order_no').text(order.order_no);
                        $('#summary_status').html(`<span class="badge ${badgeClass}">${order.status.toUpperCase()}</span>`);
                        $('#summary_total_qty').html(`<span class="badge bg-secondary p-2 fs-6">${totalQty}</span>`);
                        $('#summary_disp_qty').html(`<span class="badge bg-info p-2 fs-6">${totalDisp}</span>`);
                        $('#summary_pend_qty').html(`<span class="badge bg-warning text-dark p-2 fs-6">${totalQty - totalDisp}</span>`);

                        // Group dispatches
                        if (dispatches && dispatches.length > 0) {
                            $('#summaryHistoryCard').show();

                            // Sort chronologically
                            dispatches.sort((a,b) => new Date(a.created_at) - new Date(b.created_at));

                            let grouped = {};
                            let currentPending = {};
                            // copy initial qtys
                            Object.values(itemMap).forEach(i => { currentPending[i.id] = parseInt(i.order_qty) || 0; });

                            $.each(dispatches, function (index, d) {
                                let type = d.dispatch_type ? d.dispatch_type.toUpperCase() : 'PARTIAL';
                                let detail = d.detail || {};
                                let date = detail.dispatch_date ? detail.dispatch_date.substring(0, 10) : (d.created_at ? d.created_at.substring(0, 10) : '-');
                                // Determine unique group by time loosely or exact match of created_at string
                                let groupKey = date + '_' + d.created_at;

                                if (!grouped[groupKey]) {
                                    grouped[groupKey] = {
                                        type: type,
                                        date: date,
                                        lr_number: detail.lr_number || '-',
                                        transport_name: detail.transport_name || '-',
                                        vehicle_no: detail.vehicle_no || '-',
                                        dispatch_image: detail.dispatch_image,
                                        details: []
                                    };
                                }
                                if(type === 'FINAL') grouped[groupKey].type = 'FINAL';
                                grouped[groupKey].details.push(d);
                            });

                            let histBody = $('#summaryHistoryBody');
                            histBody.empty();
                            let breakdownHtml = '';

                            let dispatchCount = 1;
                            $.each(grouped, function(key, g) {
                                let imgHtml = '-';
                                let btnImgHtml = '';
                                if (g.dispatch_image) {
                                    imgHtml = `<a href="/storage/${g.dispatch_image}" target="_blank" class="badge bg-primary">View Image</a>`;
                                    btnImgHtml = `<a href="/storage/${g.dispatch_image}" target="_blank" class="btn btn-sm btn-outline-primary shadow-sm"><i class="fas fa-image me-1"></i> View LR</a>`;
                                }
                                let typeBadge = g.type === 'FINAL' ? 'badge bg-success' : 'badge bg-warning text-dark';

                                histBody.append(`
                                    <tr>
                                        <td><span class="${typeBadge}">${g.type}</span></td>
                                        <td>${g.date}</td>
                                        <td>${g.transport_name} <br> <small class="text-muted">LR: ${g.lr_number}</small></td>
                                        <td>${imgHtml}</td>
                                    </tr>
                                `);

                                // Process breakdown items
                                let tableRows = '';
                                let groupTotalAmt = 0;
                                let groupTotalGST = 0;
                                let groupGrandTotal = 0;

                                $.each(g.details, function(idx, drow) {
                                    let itemObj = itemMap[drow.order_item_id];
                                    if(!itemObj) return;

                                    // Update progressive pending quantity logic
                                    currentPending[itemObj.id] -= drow.dispatch_qty;
                                    let thisPending = currentPending[itemObj.id];

                                    let price = parseFloat(itemObj.price) || 0;
                                    let gstPercent = parseFloat(itemObj.gst_percent) || parseFloat(itemObj.gst) || 0;

                                    let total_price = drow.dispatch_qty * price;
                                    let gst_amount = (total_price * gstPercent) / 100;
                                    let grand_total = total_price + gst_amount;

                                    groupTotalAmt += total_price;
                                    groupTotalGST += gst_amount;
                                    groupGrandTotal += grand_total;

                                    let packingStr = (itemObj.packing_value && itemObj.packing) ? itemObj.packing_value + ' ' + itemObj.packing : '-';

                                    tableRows += `
                                        <tr>
                                            <td class="fw-bold">${itemObj.product || '-'}</td>
                                            <td>${packingStr}</td>
                                            <td>₹${price.toFixed(2)}</td>
                                            <td class="text-center"><span class="badge bg-secondary p-2">${itemObj.order_qty}</span></td>
                                            <td class="text-center"><span class="badge bg-info p-2">${drow.dispatch_qty}</span></td>
                                            <td class="text-center"><span class="badge bg-warning text-dark p-2">${thisPending}</span></td>
                                            <td>₹${total_price.toFixed(2)}</td>
                                            <td>${gstPercent}% (₹${gst_amount.toFixed(2)})</td>
                                            <td class="fw-bold text-success">₹${grand_total.toFixed(2)}</td>
                                        </tr>
                                    `;
                                });

                                breakdownHtml += `
                                <div class="card mb-4 shadow-sm border border-secondary border-opacity-25 invoice-card">
                                    <div class="card-header bg-white border-bottom pb-2 pt-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-truck-loading me-2"></i>Dispatch #${dispatchCount}</h5>
                                            <span class="${typeBadge} px-3 py-2 fs-6 rounded-pill shadow-sm">${g.type} DISPATCH</span>
                                        </div>
                                        <div class="row text-muted small mt-3">
                                            <div class="col-md-3"><strong>Date:</strong> <span class="text-dark">${g.date}</span></div>
                                            <div class="col-md-3"><strong>Transport:</strong> <span class="text-dark">${g.transport_name}</span></div>
                                            <div class="col-md-3"><strong>LR No:</strong> <span class="text-dark">${g.lr_number}</span></div>
                                            <div class="col-md-3"><strong>Vehicle No:</strong> <span class="text-dark">${g.vehicle_no}</span></div>
                                        </div>
                                        <div class="mt-2 text-end">
                                            ${btnImgHtml}
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped mb-0 align-middle text-sm">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Product</th>
                                                        <th>Packing</th>
                                                        <th>Price</th>
                                                        <th class="text-center">Order Qty</th>
                                                        <th class="text-center">Dispatch Qty</th>
                                                        <th class="text-center">Pending Qty</th>
                                                        <th>Total Price</th>
                                                        <th>GST</th>
                                                        <th>Grand Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${tableRows}
                                                </tbody>
                                                <tfoot class="table-light">
                                                    <tr>
                                                        <td colspan="6" class="text-end fw-bold">TOTALS:</td>
                                                        <td class="fw-bold">₹${groupTotalAmt.toFixed(2)}</td>
                                                        <td class="fw-bold text-danger">₹${groupTotalGST.toFixed(2)}</td>
                                                        <td class="fw-bold text-success fs-5">₹${groupGrandTotal.toFixed(2)}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                `;
                                dispatchCount++;
                            });

                            $('#dispatch_breakdown_container').html(breakdownHtml);

                        } else {
                            $('#dispatch_breakdown_container').html('<div class="alert alert-info">No dispatches found for this order.</div>');
                        }

                    } else {
                        $('#dispatch_breakdown_container').html('<div class="text-center text-danger">Failed to load data.</div>');
                    }
                },
                error: function() {
                    $('#dispatch_breakdown_container').html('<div class="text-center text-danger">Error loading details.</div>');
                }
            });
        });

        /* -----------------------
           LIVE QTY CALCULATION
        ----------------------- */
        $(document).on('input', '.live-item-qty', function() {
            let val = parseInt($(this).val()) || 1;
            if (val < 1) {
                val = 1;
                $(this).val(val);
            }

            let price = parseFloat($(this).data('price')) || 0;
            let gstPercent = parseFloat($(this).data('gst-percent')) || 0;
            let discount = parseFloat($(this).data('discount')) || 0;

            let amount = price * val;
            let afterDiscount = amount - discount;
            if(afterDiscount < 0) afterDiscount = 0;

            let gstAmount = (afterDiscount * gstPercent) / 100;
            let grandTotal = afterDiscount + gstAmount;

            let tr = $(this).closest('tr');
            tr.find('.base-total-price').text('₹' + amount.toFixed(2));
            tr.find('.base-gst-amt').text('₹' + gstAmount.toFixed(2));
            tr.find('.base-grand-total').text('₹' + grandTotal.toFixed(2));
        });

        /* -----------------------
           SAVE ORDER ITEMS QTY
        ----------------------- */
        $(document).on('click', '#saveOrderItemsBtn', function(e) {
            e.preventDefault();
            let btn = $(this);
            let order_id = btn.data('order-id');

            let items = [];
            $('.live-item-qty').each(function() {
                items.push({
                    id: $(this).data('id'),
                    qty: parseInt($(this).val()) || 1
                });
            });

            if (items.length === 0) {
                alert('No items to save.');
                return;
            }

            let originalHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            $.ajax({
                url: "{{ route('farmer-order.items.update_qty') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    farmer_order_id: order_id,
                    items: items
                },
                success: function(res) {
                    if (res.success) {
                        alert('Order items updated successfully!');
                        // Refresh the modal content to recalibrate summary logic
                        btn.prop('disabled', false).html(originalHtml);
                        openDispatchModal(order_id); // we have it defined below, or we re-trigger view-items manually
                        location.reload(); // Hard reload better ensures base table synchronizes
                    } else {
                        alert(res.message || 'Error occurred');
                        btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr) {
                    let msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to update items.';
                    alert(msg);
                    btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        /* -----------------------
           STATUS CHANGE & DISPATCH INIT
        ----------------------- */

        $(document).on('click', '.open-dispatch-btn', function(e) {
            e.preventDefault();
            let order_id = $(this).data('id');
            openDispatchModal(order_id);
        });

        $(document).on('change', '.status-change', function () {

            let status = $(this).val();
            let order_id = $(this).data('id');

            console.log("status change", order_id, status);

            if (status === 'part_dispatched' || status === 'dispatched') {
                $(this).val($(this).find('option[selected]').val() || $(this).find('option:first').val());
                openDispatchModal(order_id);
                return;
            }

            $('#modal_order_id').val(order_id);
            $('#remark_box').hide();
            $('#dispatch_box').hide();

            if (status === 'hold' || status === 'rejected') {
                $('#remark_box').show();
                $('#statusModal').modal('show');
            } else {
                updateStatus(order_id, status);
            }

        });


        /* -----------------------
           MODAL SAVE BUTTON
        ----------------------- */

        $(document).on('click', '#saveStatus', function () {

            let btn = $(this);
            let originalHtml = btn.html();

            let order_id = $('#modal_order_id').val();
            let status = $('.status-change[data-id="' + order_id + '"]').val();
            let remark = $('#remark').val();
            let lr_number = $('#lr_number').val();
            let transport_name = $('#transport_name').val();
            let destination = $('#destination').val();
            let dispatch_image = $('#dispatch_image')[0].files[0];

            let formData = new FormData();
            formData.append('_token', "{{ csrf_token() }}");
            formData.append('farmer_order_id', order_id);
            formData.append('status', status);
            formData.append('remark', remark);
            formData.append('lr_number', lr_number);
            formData.append('transport_name', transport_name);
            formData.append('destination', destination);

            if (dispatch_image) {
                formData.append('dispatch_image', dispatch_image);
            }

            $.ajax({
                url: "{{ route('farmer-order.status.update') }}",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function () {
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                },
                success: function (res) {
                    btn.prop('disabled', false).html(originalHtml);
                    if (res.status) {
                        $('#statusModal').modal('hide');
                        location.reload();
                    } else {
                        alert(res.message);
                    }
                },
                error: function (xhr) {
                    btn.prop('disabled', false).html(originalHtml);
                    let errors = xhr.responseJSON.errors;
                    if (errors) {
                        let errorMsg = '';
                        $.each(errors, function (key, value) {
                            errorMsg += value[0] + '\n';
                        });
                        alert(errorMsg);
                    } else {
                        alert('An error occurred while updating status.');
                    }
                }
            });

        });


        /* -----------------------
           DISPATCH MODAL & AJAX
        ----------------------- */

        function openDispatchModal(orderId) {
            console.log("Opening Dispatch Modal for Order ID:", orderId);
            $('#dispatch_order_id').val(orderId);
            $('#dispatchForm')[0].reset();
            $('#dispatchItemsBody').html('<tr><td colspan="10" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i> Loading...</td></tr>');
            $('#previousDispatchesCard').hide();
            $('#btnPartDispatch, #btnFullDispatch').addClass('d-none');

            $('#dispatchModal').modal('show');

            console.log("Fetching dispatch data via AJAX...");
            $.ajax({
                url: `/admin/farmer-order/${orderId}/dispatch-data`,
                type: 'GET',
                success: function(res) {
                    console.log("Dispatch data response:", res);
                    if (res.status) {
                        renderDispatchItems(res.items);
                        renderDispatchHistory(res.dispatches, res.items);
                    } else {
                        alert('Failed to load dispatch data');
                        $('#dispatchModal').modal('hide');
                    }
                },
                error: function() {
                    alert('Error loading dispatch data');
                    $('#dispatchModal').modal('hide');
                }
            });
        }

        function renderDispatchItems(items) {
            let tbody = $('#dispatchItemsBody');
            tbody.empty();
            let totalPendingGlobally = 0;

            if (items.length > 0) {
                $.each(items, function (index, item) {
                    totalPendingGlobally += item.pending_qty;
                    let gstPercent = parseFloat(item.gst_percent) || 0;
                    let row = `
                    <tr data-id="${item.id}" data-price="${item.price}" data-gst-percent="${gstPercent}" data-discount="${item.discount}" data-pending="${item.pending_qty}">
                        <td class="fw-bold">${item.product || '-'}</td>
                        <td>${item.packing_value && item.packing ? item.packing_value + ' ' + item.packing : '-'}</td>
                        <td>₹${parseFloat(item.price).toFixed(2)}</td>
                        <td class="text-center"><span class="badge bg-secondary px-2 py-1">${item.order_qty}</span></td>
                        <td class="bg-primary bg-opacity-10 p-2">
                            <input type="number" class="form-control form-control-sm dispatch-qty-input fw-bold text-center border-primary shadow-sm"
                                value="0" min="0" max="${item.pending_qty}" ${item.pending_qty === 0 ? 'disabled' : ''}>
                        </td>
                        <td class="text-center"><span class="badge bg-${item.pending_qty > 0 ? 'warning text-dark' : 'success'} px-2 py-1 item-pending-text">${item.pending_qty}</span></td>
                        <td class="item-total-price">₹0.00</td>
                        <td>${parseFloat(item.gst_percent) || 0}% (<span class="gst-amount-text">₹0.00</span>)</td>
                        <td class="item-grand-total fw-bold text-success">₹0.00</td>
                    </tr>`;
                    tbody.append(row);
                });

                if (totalPendingGlobally > 0) {
                    $('#btnPartDispatch').removeClass('d-none');
                    $('#btnFullDispatch').removeClass('d-none');
                }
            } else {
                tbody.append('<tr><td colspan="9" class="text-center">No items found.</td></tr>');
            }
        }

        $(document).on('input', '.dispatch-qty-input', function() {
            let tr = $(this).closest('tr');
            let pendingQty = parseInt(tr.data('pending')) || 0;
            let qty = parseInt($(this).val()) || 0;

            if (qty > pendingQty) {
                qty = pendingQty;
                $(this).val(qty);
            }
            if (qty < 0) {
                qty = 0;
                $(this).val(qty);
            }

            let price = parseFloat(tr.data('price')) || 0;
            let gstPercent = parseFloat(tr.data('gst-percent')) || 0;
            let discount = parseFloat(tr.data('discount')) || 0;

            let total = price * qty;
            let totalAfterDiscount = total;
            if(qty > 0) {
                totalAfterDiscount = total - discount;
                if(totalAfterDiscount < 0) totalAfterDiscount = 0;
            } else {
                totalAfterDiscount = 0;
            }

            let gstAmount = (totalAfterDiscount * gstPercent) / 100;
            let grandTotal = totalAfterDiscount + gstAmount;

            tr.find('.item-total-price').text('₹' + total.toFixed(2));
            tr.find('.gst-amount-text').text('₹' + gstAmount.toFixed(2));
            tr.find('.item-grand-total').text('₹' + grandTotal.toFixed(2));
        });

        function renderDispatchHistory(dispatches, itemsMap) {
            let tbody = $('#previousDispatchesBody');
            tbody.empty();
            if (dispatches && dispatches.length > 0) {
                $('#previousDispatchesCard').show();
                let map = {};
                itemsMap.forEach(i => { map[i.id] = (i.product || 'Item') + ' (' + (i.packing || '') + ')'; });

                $.each(dispatches, function(index, d) {
                    let itemName = map[d.order_item_id] || 'Unknown';
                    let detail = d.detail || {};
                    let imgHtml = '-';
                    if (detail.dispatch_image) {
                        imgHtml = `<a href="/storage/${detail.dispatch_image}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-image"></i> View</a>`;
                    }
                    let dateStr = detail.dispatch_date ? detail.dispatch_date.substring(0, 10) : '-';

                    let row = `
                    <tr>
                        <td>${dateStr}</td>
                        <td class="fw-bold">${itemName}</td>
                        <td><span class="badge bg-secondary font-monospace fs-6">${d.dispatch_qty}</span></td>
                        <td>${detail.lr_number || '-'}</td>
                        <td>${detail.transport_name || '-'}</td>
                        <td>${detail.vehicle_no || '-'}</td>
                        <td>${imgHtml}</td>
                    </tr>`;
                    tbody.append(row);
                });
            } else {
                $('#previousDispatchesCard').hide();
            }
        }

        $('#btnPartDispatch, #btnFullDispatch').on('click', function() {
            let btn = $(this);
            let form = $('#dispatchForm')[0];
            let formData = new FormData(form);

            let hasQty = false;
            let totalInputs = 0;
            $('#dispatchItemsBody tr').each(function(index, tr) {
                let itemId = $(tr).data('id');
                let qty = parseInt($(tr).find('.dispatch-qty-input').val()) || 0;
                if (qty > 0) {
                    hasQty = true;
                }
                formData.append(`dispatch_items[${totalInputs}][item_id]`, itemId);
                formData.append(`dispatch_items[${totalInputs}][dispatch_qty]`, qty);
                totalInputs++;
            });

            if (!hasQty) {
                alert('Please enter dispatch quantity for at least one item.');
                return;
            }

            let originalHtml = btn.html();

            $.ajax({
                url: "{{ route('farmer-order.dispatch.store') }}",
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('#btnPartDispatch, #btnFullDispatch').prop('disabled', true);
                    btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                },
                success: function(res) {
                    if (res.status) {
                        $('#dispatchModal').modal('hide');
                        location.reload();
                    } else {
                        alert(res.message);
                    }
                },
                error: function(xhr) {
                    let errors = xhr.responseJSON?.errors;
                    if (errors) {
                        let eMsg = '';
                        $.each(errors, function(k, v) { eMsg += v[0] + '\\n'; });
                        alert(eMsg);
                    } else {
                        alert('Failed to save dispatch.');
                    }
                },
                complete: function() {
                    $('#btnPartDispatch, #btnFullDispatch').prop('disabled', false);
                    btn.html(originalHtml);
                }
            });
        });

        /* -----------------------
           SIMPLE STATUS UPDATE
        ----------------------- */

        function updateStatus(order_id, status) {

            $.ajax({

                url: "{{ route('farmer-order.status.update') }}",

                type: "POST",

                data: {

                    _token: "{{ csrf_token() }}",

                    farmer_order_id: order_id,

                    status: status

                },

                success: function (res) {

                    if (res.status) {

                        location.reload();

                    }

                }

            });

        }

    </script>

@endpush
