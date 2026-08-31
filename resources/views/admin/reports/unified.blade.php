@extends('admin.layout.layout')

@section('content')
<main class="app-main">
    <div class="content py-4">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="section-title mb-0">
                    <i class="fas fa-chart-line text-primary me-2"></i> Unified Monthly Performance Report
                </h5>
            </div>

            <!-- Filter Section -->
            <div class="card mb-4 shadow-sm border-0 card-premium">
                <div class="card-body">
                    <form method="GET" action="{{ route('reports.unified') }}" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-muted">Month</label>
                            <select name="month" class="form-select select2">
                                @for($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}" {{ $month == $i ? 'selected' : '' }}>
                                        {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-muted">Year</label>
                            <select name="year" class="form-select select2">
                                @php $current = date('Y'); @endphp
                                @for($y = $current - 2; $y <= $current + 1; $y++)
                                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Table -->
            <div class="card card-premium shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fs-6 fw-bold"><i class="fas fa-table me-2"></i> Monthly Performance Data</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive p-3">
                        <table id="unified-reports-table" class="table table-hover table-striped table-bordered align-middle w-100" style="white-space: nowrap;">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>State</th>
                                    <th>Employee Name</th>
                                    <th>Reporting To</th>
                                    <th>Present Days</th>
                                    <th>Working Hrs</th>
                                    <th>Avg Work Hrs</th>
                                    <th>Travel KM</th>
                                    <th>Yr Target %</th>
                                    <th>Mo Target %</th>
                                    <th>Visits</th>
                                    <th>New Parties</th>
                                    <th>Orders</th>
                                    <th>Collection</th>
                                    <th>Farmer DL</th>
                                    <th>Field Demo</th>
                                    <th>TA</th>
                                    <th>DA</th>
                                    <th>Other Allw.</th>
                                    <th>Total Exp.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reportData as $data)
                                <tr>
                                    <td><span class="text-muted small fw-bold">{{ $data['date'] }}</span></td>
                                    <td>{{ $data['state'] }}</td>
                                    <td>
                                        <div class="fw-bold text-primary">{{ $data['employee_name'] }}</div>
                                    </td>
                                    <td>{{ $data['reporting_to'] }}</td>
                                    
                                    <td class="text-center"><span class="badge bg-secondary">{{ $data['present_days'] }}</span></td>
                                    <td class="text-center">{{ $data['working_hrs'] }}</td>
                                    <td class="text-center">{{ $data['avg_work_hrs'] }}</td>
                                    <td class="text-center fw-bold">{{ $data['total_travel_km'] }} <small class="text-muted">km</small></td>
                                    
                                    <td class="text-center">
                                        <span class="badge {{ $data['yearly_target_pct'] >= 100 ? 'bg-success' : 'bg-warning text-dark' }}">{{ $data['yearly_target_pct'] }}%</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $data['monthly_target_pct'] >= 100 ? 'bg-success' : 'bg-warning text-dark' }}">{{ $data['monthly_target_pct'] }}%</span>
                                    </td>
                                    
                                    <td class="text-center">{{ $data['visit_party_count'] }}</td>
                                    <td class="text-center">{{ $data['new_party_count'] }}</td>
                                    <td class="text-center">{{ $data['order_count'] }}</td>
                                    <td class="text-end fw-bold text-success">₹{{ number_format($data['payment_collection'], 2) }}</td>
                                    
                                    <td class="text-center">{{ $data['farmer_download'] }}</td>
                                    <td class="text-center">{{ $data['field_demo'] }}</td>
                                    
                                    <td class="text-end">₹{{ number_format($data['ta_allowance'], 2) }}</td>
                                    <td class="text-end">₹{{ number_format($data['da_allowance'], 2) }}</td>
                                    <td class="text-end">₹{{ number_format($data['other_allowance'], 2) }}</td>
                                    <td class="text-end fw-bold text-danger">₹{{ number_format($data['total_expense'], 2) }}</td>
                                </tr>
                                @empty
                                <!-- Empty state handled by DataTables automatically if empty, but good to have fallback -->
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        var reportsCount = @json(count($reportData));
        if (reportsCount > 0) {
            $('#unified-reports-table').DataTable({
                responsive: false, // False so we can use scrollX for many columns
                scrollX: true,
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[2, 'asc']], // Order by employee name by default
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search records..."
                }
            });
        }
    });
</script>
@endpush
