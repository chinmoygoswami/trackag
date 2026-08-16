@extends('admin.layout.layout')

@push('styles')
    <style>
        .dashboard-page {
            background: linear-gradient(180deg, #eef3f8 0, #f7f9fc 260px, #f7f9fc 100%);
            min-height: calc(100vh - 57px);
        }

        .dashboard-header {
            padding: 1.5rem 0 0;
        }

        .dashboard-hero {
            align-items: center;
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: .5rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .14);
            display: flex;
            justify-content: space-between;
            min-height: 128px;
            overflow: hidden;
            padding: 1.35rem 1.5rem;
            position: relative;
            margin-bottom: 2rem;
        }

        .dashboard-hero::after {
            background: radial-gradient(circle at 20% 20%, rgba(59, 130, 246, .25), transparent 30%), radial-gradient(circle at 75% 40%, rgba(20, 184, 166, .2), transparent 28%);
            content: "";
            inset: 0;
            opacity: .9;
            position: absolute;
        }

        .dashboard-hero > * {
            position: relative;
            z-index: 1;
        }

        .dashboard-title {
            color: #fff;
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: 0;
        }

        .dashboard-subtitle {
            color: #cbd5e1;
            font-size: .95rem;
            margin-bottom: 0;
        }

        .metric-card {
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: .5rem;
            box-shadow: 0 14px 30px rgba(15, 23, 42, .08);
            display: flex;
            flex-direction: column;
            min-height: 120px;
            overflow: hidden;
            position: relative;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .metric-card:hover {
            border-color: rgba(59, 130, 246, .28);
            box-shadow: 0 18px 38px rgba(15, 23, 42, .12);
            transform: translateY(-2px);
        }

        .metric-card::before {
            content: "";
            bottom: 0;
            height: auto;
            left: 0;
            position: absolute;
            top: 0;
            width: 4px;
        }

        .metric-card.primary::before { background: #2563eb; }
        .metric-card.success::before { background: #16a34a; }
        .metric-card.warning::before { background: #f59e0b; }
        .metric-card.danger::before { background: #dc2626; }

        .metric-card-body {
            align-items: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 1.5rem;
            text-align: center;
        }

        .metric-label {
            color: #6b7280;
            font-size: .85rem;
            font-weight: 700;
            margin-bottom: .5rem;
            text-transform: uppercase;
        }

        .metric-value {
            color: #111827;
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            margin: 0;
        }

        .dashboard-card {
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: .5rem;
            box-shadow: 0 14px 30px rgba(15, 23, 42, .08);
            overflow: hidden;
            height: 100%;
        }

        .dashboard-card .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.25rem;
            font-weight: 800;
            font-size: 1.05rem;
            color: #1e293b;
        }

        .dashboard-card .card-body {
            padding: 1.25rem;
        }

        .state-row {
            display: flex;
            justify-content: space-between;
            padding: 0.65rem 0;
            color: #334155;
            font-weight: 600;
            font-size: 0.95rem;
            border-bottom: 1px dashed #e2e8f0;
        }

        .state-row:last-child {
            border-bottom: none;
        }

        .dashboard-table-container {
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: .5rem;
            box-shadow: 0 14px 30px rgba(15, 23, 42, .08);
            overflow: hidden;
            margin-top: 2rem;
        }

        .dashboard-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
        }

        .dashboard-table thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
            font-size: .8rem;
            letter-spacing: .02em;
            padding: 1rem 1.25rem;
            text-transform: uppercase;
            font-weight: 700;
        }

        .dashboard-table tbody td {
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            padding: 1rem 1.25rem;
            vertical-align: middle;
            font-weight: 500;
        }

        .dashboard-table tbody tr:hover {
            background: #f1f5f9;
        }

        .employee-name {
            color: #2563eb;
            font-weight: 800;
            font-size: 0.95rem;
        }

        .btn-action {
            font-weight: 700;
            font-size: 0.85rem;
            padding: 0.35rem 0.75rem;
            border-radius: 0.35rem;
            transition: all 0.2s;
        }

        .btn-action-map {
            background: #e0f2fe;
            color: #0284c7;
        }
        .btn-action-map:hover { background: #bae6fd; color: #0369a1; }

        .btn-action-log {
            background: #fce7f3;
            color: #be185d;
        }
        .btn-action-log:hover { background: #fbcfe8; color: #9f1239; }

        .btn-action-tour {
            background: #dcfce7;
            color: #166534;
        }
        .btn-action-tour:hover { background: #bbf7d0; color: #15803d; }
    </style>
@endpush

@section('content')
    <main class="app-main dashboard-page">
        <div class="content-header dashboard-header">
            <div class="container-fluid">
                <div class="dashboard-hero">
                    <div>
                        <h3 class="dashboard-title mb-1">Dashboard Overview</h3>
                        <p class="dashboard-subtitle">Real-time daily operations and state-wise performance metrics.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="content pb-4">
            <div class="container-fluid">

                <!-- Top Cards Row -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="metric-card primary">
                            <div class="metric-card-body">
                                <div class="metric-label">Todays Active User Count</div>
                                <h3 class="metric-value">{{ $todaysActiveUserCount }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="metric-card success">
                            <div class="metric-card-body">
                                <div class="metric-label">Today's Place Order Count</div>
                                <h3 class="metric-value">{{ $todaysOrderCount }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="metric-card warning">
                            <div class="metric-card-body">
                                <div class="metric-label">Todays Payment Collection</div>
                                <h3 class="metric-value">₹{{ number_format($todaysPaymentCollection, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="metric-card danger">
                            <div class="metric-card-body">
                                <div class="metric-label">Todays Party Visit Count</div>
                                <h3 class="metric-value">{{ $todaysPartyVisits }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Middle Cards Row -->
                <div class="row g-4 mb-4">
                    <!-- Target Vs Ach % -->
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-header">Target Vs Ach %</div>
                            <div class="card-body">
                                @forelse($statesData as $state)
                                    <div class="state-row">
                                        <span>{{ $state->name }}</span>
                                        <span class="text-primary">{{ $state->target_ach }}%</span>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-3">No state data</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Partywise Outstanding -->
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-header">Partywise Outstanding</div>
                            <div class="card-body">
                                @forelse($statesData as $state)
                                    <div class="state-row">
                                        <span>{{ $state->name }}</span>
                                        <span class="text-danger">{{ number_format($state->outstanding, 0) }}</span>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-3">No state data</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- TA-DA Info -->
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-header">TA-DA Info</div>
                            <div class="card-body">
                                @forelse($statesData as $state)
                                    <div class="state-row">
                                        <span>{{ $state->name }}</span>
                                        <span class="text-warning">{{ number_format($state->tada, 0) }}</span>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-3">No state data</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Payment Credit -->
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-header">Payment Credit</div>
                            <div class="card-body">
                                @forelse($statesData as $state)
                                    <div class="state-row">
                                        <span>{{ $state->name }}</span>
                                        <span class="text-success">{{ number_format($state->payment_credit, 0) }}</span>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-3">No state data</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Table -->
                <div class="dashboard-table-container table-responsive">
                    <table class="table dashboard-table text-nowrap">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Day Start Time / Day End Time</th>
                                <th>Login Hrs</th>
                                <th>Tour Plan</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employeeData as $employee)
                            <tr>
                                <td>
                                    <span class="employee-name">{{ $employee->name }}</span>
                                </td>
                                <td>
                                    <div class="text-success fw-bold">{{ $employee->day_start }}</div>
                                    <div class="text-danger fw-bold">{{ $employee->day_end }}</div>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $employee->login_hrs }}</span></td>
                                <td>
                                    <a href="#" class="btn-action btn-action-tour text-decoration-none d-inline-block">
                                        <i class="fas fa-route me-1"></i> View Tour
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        @if($employee->trip_id)
                                            <a href="{{ route('trip.map', $employee->trip_id) }}" class="btn-action btn-action-map text-decoration-none" target="_blank">
                                                <i class="fas fa-map-marked-alt me-1"></i> MAP
                                            </a>
                                        @else
                                            <span class="btn-action btn-action-map text-decoration-none opacity-50"><i class="fas fa-map-marked-alt me-1"></i> MAP</span>
                                        @endif
                                        <a href="#" class="btn-action btn-action-log text-decoration-none" onclick="event.preventDefault(); loadSessionHistory({{ $employee->id }});">
                                            <i class="fas fa-history me-1"></i> LOG
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No active employees found today.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Session History Modal --}}
                <div class="modal fade" id="sessionHistoryModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="sessionHistoryModalLabel">Session History</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="sessionHistoryContent">Loading...</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
@endsection

@push('scripts')
<script>
    function loadSessionHistory(userId) {
        var modalElement = document.getElementById('sessionHistoryModal');
        // Ensure bootstrap modal is loaded, if not, use jQuery depending on app's standard
        if (typeof bootstrap !== 'undefined') {
            var modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else {
            $(modalElement).modal('show');
        }

        document.getElementById('sessionHistoryContent').innerHTML = 'Loading...';
        
        fetch('{{ url("admin/users") }}/' + userId + '/sessions')
            .then(response => response.text())
            .then(html => {
                document.getElementById('sessionHistoryContent').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('sessionHistoryContent').innerHTML = '<p class="text-danger">Failed to load session history.</p>';
            });
    }
</script>
@endpush

