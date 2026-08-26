@extends('admin.layout.layout')

@push('styles')
<style>
    .reports-page { background-color: #f8fafc; min-height: calc(100vh - 57px); }
    .table-responsive { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); overflow: hidden; }
    .dashboard-table thead th { background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 0.75rem; letter-spacing: 0.05em; padding: 1rem 1.5rem; text-transform: uppercase; font-weight: 700; white-space: nowrap;}
    .dashboard-table tbody td { border-bottom: 1px solid #f1f5f9; color: #334155; padding: 1rem 1.5rem; vertical-align: middle; font-weight: 500; white-space: nowrap;}
    .dashboard-table tbody tr:hover { background: #f8fafc; }
    .filter-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
    .btn-view { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-weight: 600; font-size: 0.8125rem; padding: 0.4rem 0.875rem; border-radius: 0.5rem; transition: all 0.2s; }
    .btn-view:hover { background: #dbeafe; color: #1d4ed8; }
</style>
@endpush

@section('content')
<main class="app-main reports-page">
    <div class="content py-4">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="section-title mb-0">
                    <i class="fas fa-file-alt text-primary me-2"></i> Employee Activity Reports
                </h5>
            </div>

            <!-- Filter Section -->
            <div class="filter-card">
                <form method="GET" action="{{ route('reports.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="filter" class="form-label fw-bold">Report Type</label>
                        <select name="filter" id="filter" class="form-select">
                            <option value="daily" {{ $filter == 'daily' ? 'selected' : '' }}>Daily Report</option>
                            <option value="weekly" {{ $filter == 'weekly' ? 'selected' : '' }}>Weekly Report</option>
                            <option value="monthly" {{ $filter == 'monthly' ? 'selected' : '' }}>Monthly Report</option>
                            <option value="yearly" {{ $filter == 'yearly' ? 'selected' : '' }}>Yearly Report</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Apply Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table Section -->
            <div class="table-responsive">
                <table class="table dashboard-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>State</th>
                            <th>Employee Name</th>
                            <th>Reporting To</th>
                            <th>Punch In</th>
                            <th>Punch Out</th>
                            <th>Working Hrs</th>
                            <th>Tour Plan</th>
                            <th>Travel KM</th>
                            <th>Visit Party Name (Count)</th>
                            <th>New Party Name (Count)</th>
                            <th>Order Count</th>
                            <th>Payment Collection</th>
                            <th>Farmer Download</th>
                            <th>Field Demo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData as $data)
                        <tr>
                            <td>{{ $data['date'] }}</td>
                            <td>{{ $data['state'] }}</td>
                            <td class="fw-bold text-dark">{{ $data['employee_name'] }}</td>
                            <td>{{ $data['reporting_to'] }}</td>
                            <td><span class="text-success fw-bold">{{ $data['punch_in'] }}</span></td>
                            <td><span class="text-danger fw-bold">{{ $data['punch_out'] }}</span></td>
                            <td><span class="badge bg-light text-dark border">{{ $data['working_hrs'] }} Hrs</span></td>
                            <td>{{ $data['tour_plan'] }}</td>
                            <td>{{ $data['travel_km'] }} KM</td>
                            <td>
                                {{ $data['visit_party_count'] }}
                                @if($data['visit_party_count'] > 0)
                                    <button class="btn btn-sm btn-view ms-2" onclick="showVisitedParties({{ json_encode($data['visited_parties']) }})">VIEW</button>
                                @endif
                            </td>
                            <td>
                                {{ $data['new_party_count'] }}
                                @if($data['new_party_count'] > 0)
                                    <button class="btn btn-sm btn-view ms-2" onclick="showNewParties({{ json_encode($data['new_parties']) }})">VIEW</button>
                                @endif
                            </td>
                            <td>
                                {{ $data['order_count'] }} 
                                @if($data['order_count'] > 0)
                                    <br><span class="text-success fw-bold">(₹{{ number_format($data['order_amount'], 2) }})</span>
                                    <button class="btn btn-sm btn-view mt-1" onclick="showOrders({{ json_encode($data['orders_list']) }})">VIEW</button>
                                @endif
                            </td>
                            <td>
                                <span class="text-success fw-bold">₹{{ number_format($data['payment_collection'], 2) }}</span>
                                @if($data['payment_collection'] > 0)
                                    <button class="btn btn-sm btn-view ms-2" onclick="showPayments({{ json_encode($data['payments_list']) }})">VIEW</button>
                                @endif
                            </td>
                            <td>{{ $data['farmer_download'] }}</td>
                            <td>
                                {{ $data['field_demo'] }}
                                @if($data['field_demo'] > 0)
                                    <button class="btn btn-sm btn-view ms-2" onclick="showFarmVisits({{ json_encode($data['farm_visits_list']) }})">VIEW</button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="15" class="text-center py-4 text-muted">No report data found for this filter.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    @include('admin.reports.partials.visited-parties-modal')
    @include('admin.reports.partials.new-parties-modal')
    @include('admin.reports.partials.orders-modal')
    @include('admin.reports.partials.payments-modal')
    @include('admin.reports.partials.farm-visits-modal')
</main>
@endsection

@push('scripts')
<script>
    function showVisitedParties(parties) {
        let content = '';
        if(parties.length === 0) {
            content = '<p class="text-center text-muted">No parties visited.</p>';
        } else {
            content = '<ul class="list-group list-group-flush">';
            parties.forEach(party => {
                let partyName = (party.customer && party.customer.agro_name) ? party.customer.agro_name : 'Party ID: ' + (party.customer_id || 'Unknown');
                let checkIn = 'N/A';
                if (party.check_in_time) {
                    let date = new Date(party.check_in_time);
                    checkIn = date.toLocaleString('en-IN', {
                        day: '2-digit', month: 'short', year: 'numeric',
                        hour: '2-digit', minute: '2-digit', hour12: true
                    });
                }
                
                content += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-building me-2 text-primary"></i> ${partyName}</span>
                                <span class="badge bg-light text-dark">Checked In: ${checkIn}</span>
                            </li>`;
            });
            content += '</ul>';
        }
        
        document.getElementById('visitedPartiesContent').innerHTML = content;
        
        var modalElement = document.getElementById('visitedPartiesModal');
        if (typeof bootstrap !== 'undefined') {
            var modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else {
            $(modalElement).modal('show');
        }
    }
    function showNewParties(parties) {
        let content = '';
        if(parties.length === 0) {
            content = '<p class="text-center text-muted">No new parties.</p>';
        } else {
            content = '<ul class="list-group list-group-flush">';
            parties.forEach(party => {
                let partyName = party.agro_name || 'Unknown Name';
                let checkIn = 'N/A';
                if (party.created_at) {
                    let date = new Date(party.created_at);
                    checkIn = date.toLocaleString('en-IN', {
                        day: '2-digit', month: 'short', year: 'numeric',
                        hour: '2-digit', minute: '2-digit', hour12: true
                    });
                }
                
                content += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-building me-2 text-primary"></i> ${partyName}</span>
                                <span class="badge bg-light text-dark">Added On: ${checkIn}</span>
                            </li>`;
            });
            content += '</ul>';
        }
        
        document.getElementById('newPartiesContent').innerHTML = content;
        
        var modalElement = document.getElementById('newPartiesModal');
        if (typeof bootstrap !== 'undefined') {
            var modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else {
            $(modalElement).modal('show');
        }
    }

    function showOrders(orders) {
        let content = '';
        if(orders.length === 0) {
            content = '<p class="text-center text-muted">No orders found.</p>';
        } else {
            content = '<ul class="list-group list-group-flush">';
            orders.forEach(order => {
                let partyName = (order.customer && order.customer.agro_name) ? order.customer.agro_name : 'Party ID: ' + (order.party_id || 'Unknown');
                let orderNo = order.order_no || 'N/A';
                
                let amount = 0;
                if(order.items && order.items.length > 0) {
                    amount = order.items.reduce((sum, item) => sum + parseFloat(item.grand_total || 0), 0);
                }
                
                content += `<li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold"><i class="fas fa-shopping-cart me-2 text-primary"></i> ${partyName}</div>
                                    <small class="text-muted">Order No: ${orderNo}</small>
                                </div>
                                <span class="badge bg-success text-white rounded-pill">₹${amount.toFixed(2)}</span>
                            </li>`;
            });
            content += '</ul>';
        }
        
        document.getElementById('ordersContent').innerHTML = content;
        
        var modalElement = document.getElementById('ordersModal');
        if (typeof bootstrap !== 'undefined') {
            var modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else {
            $(modalElement).modal('show');
        }
    }

    function showPayments(payments) {
        let content = '';
        if(payments.length === 0) {
            content = '<p class="text-center text-muted">No payments found.</p>';
        } else {
            content = '<ul class="list-group list-group-flush">';
            payments.forEach(payment => {
                let partyName = (payment.customer && payment.customer.agro_name) ? payment.customer.agro_name : 'Party ID: ' + (payment.customer_id || 'Unknown');
                let mode = payment.payment_mode || 'N/A';
                let amount = payment.amount ? parseFloat(payment.amount).toFixed(2) : '0.00';
                
                content += `<li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold"><i class="fas fa-building me-2 text-success"></i> ${partyName}</div>
                                    <small class="text-muted">Mode: ${mode}</small>
                                </div>
                                <span class="badge bg-success text-white rounded-pill">₹${amount}</span>
                            </li>`;
            });
            content += '</ul>';
        }
        
        document.getElementById('paymentsContent').innerHTML = content;
        
        var modalElement = document.getElementById('paymentsModal');
        if (typeof bootstrap !== 'undefined') {
            var modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else {
            $(modalElement).modal('show');
        }
    }

    function showFarmVisits(visits) {
        let content = '';
        if(visits.length === 0) {
            content = '<p class="text-center text-muted">No field demos found.</p>';
        } else {
            content = '<ul class="list-group list-group-flush">';
            visits.forEach(visit => {
                let farmerName = (visit.farmer && visit.farmer.name) ? visit.farmer.name : 'Farmer ID: ' + (visit.farmer_id || 'Unknown');
                let cropName = (visit.crop && visit.crop.name) ? visit.crop.name : 'N/A';
                
                content += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><i class="fas fa-user-tag me-2 text-warning"></i> ${farmerName}</div>
                                    <small class="text-muted">Crop: ${cropName}</small>
                                </div>
                            </li>`;
            });
            content += '</ul>';
        }
        
        document.getElementById('farmVisitsContent').innerHTML = content;
        
        var modalElement = document.getElementById('farmVisitsModal');
        if (typeof bootstrap !== 'undefined') {
            var modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else {
            $(modalElement).modal('show');
        }
    }
</script>
@endpush
