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
                                @endif
                            </td>
                            <td class="text-success fw-bold">₹{{ number_format($data['payment_collection'], 2) }}</td>
                            <td>{{ $data['farmer_download'] }}</td>
                            <td>{{ $data['field_demo'] }}</td>
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
</script>
@endpush
