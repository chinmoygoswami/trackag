@extends('admin.layout.layout')

@push('styles')
<style>
    .unified-table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; background: #fff; }
    .unified-table th, .unified-table td { border: 1px solid #dee2e6; padding: 0.5rem; text-align: center; vertical-align: middle; }
    .unified-table thead th { background-color: #ffeb3b; font-weight: bold; }
    .unified-table .bg-orange { background-color: #ff9800; color: #fff; }
    .unified-table .bg-gray { background-color: #b0bec5; }
    .unified-table .text-start { text-align: left; }
    .unified-table .fw-bold { font-weight: bold; }
</style>
@endpush

@section('content')
<main class="app-main">
    <div class="content py-4">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="section-title mb-0">
                    <i class="fas fa-file-alt text-primary me-2"></i> Monthly Unified Performance Report
                </h5>
            </div>

            <!-- Filter Section -->
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-body">
                    <form method="GET" action="{{ route('reports.unified') }}" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Month</label>
                            <select name="month" class="form-select">
                                @for($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}" {{ $month == $i ? 'selected' : '' }}>
                                        {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Year</label>
                            <select name="year" class="form-select">
                                @php $current = date('Y'); @endphp
                                @for($y = $current - 2; $y <= $current + 1; $y++)
                                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Tables per Employee -->
            @forelse($reportData as $data)
            <div class="table-responsive mb-5 shadow-sm rounded">
                <table class="unified-table">
                    <thead>
                        <tr>
                            <th colspan="4" class="fs-5 py-2">Monthly Report</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="bg-orange fw-bold w-25">Date</td>
                            <td class="bg-orange fw-bold w-25">State</td>
                            <td class="bg-orange fw-bold w-25">Employee Name</td>
                            <td class="bg-orange fw-bold w-25">Reporting To</td>
                        </tr>
                        <tr>
                            <td>{{ $data['date'] }}</td>
                            <td>{{ $data['state'] }}</td>
                            <td>{{ $data['employee_name'] }}</td>
                            <td>{{ $data['reporting_to'] }}</td>
                        </tr>
                        
                        <tr><td colspan="4" class="p-2 border-0"></td></tr> <!-- Spacer -->

                        <tr>
                            <td class="bg-gray fw-bold">Employee Name</td>
                            <td class="bg-gray fw-bold">Reporting To</td>
                            <td class="bg-gray fw-bold">Present Days</td>
                            <td class="bg-gray fw-bold">Working Hrs</td>
                            <td class="bg-gray fw-bold">Avg Work Hrs</td>
                            <td class="bg-gray fw-bold">Total Travel KM</td>
                        </tr>
                        <tr>
                            <td>{{ $data['employee_name'] }}</td>
                            <td>{{ $data['reporting_to'] }}</td>
                            <td>{{ $data['present_days'] }}</td>
                            <td>{{ $data['working_hrs'] }}</td>
                            <td>{{ $data['avg_work_hrs'] }}</td>
                            <td>{{ $data['total_travel_km'] }}</td>
                        </tr>

                        <tr><td colspan="4" class="p-2 border-0"></td></tr> <!-- Spacer -->

                        <tr>
                            <td class="bg-orange text-start fw-bold">Yearly Target Vs Achievement</td>
                            <td class="bg-orange fw-bold">{{ $data['yearly_target_pct'] }}%</td>
                            <td class="bg-orange text-start fw-bold">Monthly Target Vs Achievement</td>
                            <td class="bg-orange fw-bold">{{ $data['monthly_target_pct'] }}%</td>
                        </tr>
                        <tr>
                            <td class="text-start fw-bold">Visit Party Name (Count Show)</td>
                            <td>{{ $data['visit_party_count'] }}</td>
                            <td class="text-start bg-orange fw-bold">TA Allowance</td>
                            <td>₹{{ number_format($data['ta_allowance'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-start fw-bold">New Party Name (Count Show)</td>
                            <td>{{ $data['new_party_count'] }}</td>
                            <td class="text-start bg-orange fw-bold">DA Allowance</td>
                            <td>₹{{ number_format($data['da_allowance'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-start fw-bold">Order Count</td>
                            <td>{{ $data['order_count'] }}</td>
                            <td class="text-start bg-orange fw-bold">Other Allowance</td>
                            <td>₹{{ number_format($data['other_allowance'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-start fw-bold">Payment Collection</td>
                            <td>₹{{ number_format($data['payment_collection'], 2) }}</td>
                            <td class="text-start bg-orange fw-bold">Total</td>
                            <td class="fw-bold">₹{{ number_format($data['total_expense'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-start fw-bold">Farmer Download</td>
                            <td>{{ $data['farmer_download'] }}</td>
                            <td colspan="2" class="border-0"></td>
                        </tr>
                        <tr>
                            <td class="text-start fw-bold">Field Demo</td>
                            <td>{{ $data['field_demo'] }}</td>
                            <td colspan="2" class="border-0"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @empty
            <div class="alert alert-info">No employee data found for this month.</div>
            @endforelse

        </div>
    </div>
</main>
@endsection
