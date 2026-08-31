@extends('admin.layout.layout')

@push('styles')
<style>
    .budget-table thead th {
        position: sticky;
        top: 0;
        background: #f8f9fa !important;
        z-index: 2;
        box-shadow: inset 0 -1px 0 #dee2e6;
    }
    .sticky-col-1 {
        position: sticky !important;
        left: 0;
        z-index: 11 !important;
        background-color: #fff !important;
        border-right: 2px solid #dee2e6 !important;
    }
    thead th.sticky-col-1 {
        z-index: 15 !important;
        background-color: #f8f9fa !important;
    }
    .card-premium {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 12px;
    }
</style>
@endpush

@section('content')
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Budget Summary Report</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('budget.index') }}">Budget Plan</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Summary Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Filter Section -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="{{ route('budget.report') }}" method="GET">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label>FY Year</label>
                                <select name="financial_year" class="form-control select2">
                                    @php
                                        $currentYear = date('Y');
                                        $years = [];
                                        for($i = -1; $i <= 2; $i++) {
                                            $y = $currentYear + $i;
                                            $years[] = $y . '-' . substr($y + 1, 2);
                                        }
                                    @endphp
                                    @foreach($years as $fy)
                                        <option value="{{ $fy }}" {{ $financial_year == $fy ? 'selected' : '' }}>{{ $fy }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-warning">GO</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row mb-2">
                @foreach($stateReport as $stateId => $data)
                    @php
                        $stateTarget = $data['total_target'];
                        $stateAchieve = array_sum($data['monthly_achievements']);
                        $statePercent = $stateTarget > 0 ? ($stateAchieve / $stateTarget) * 100 : ($stateAchieve > 0 ? 100 : 0);
                    @endphp
                    <div class="col-md-3 mb-3">
                        <div class="card card-premium shadow-sm h-100 border-top border-3 {{ $statePercent >= 100 ? 'border-success' : 'border-warning' }}">
                            <div class="card-body py-3">
                                <h6 class="card-title fw-bold text-dark mb-3"><i class="fas fa-map-marker-alt text-primary me-2"></i>{{ $data['name'] }}</h6>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small fw-bold">TARGET</span>
                                    <span class="fw-bold text-dark small">₹{{ number_format($stateTarget, 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small fw-bold">ACHIEVE</span>
                                    <span class="fw-bold text-success small">₹{{ number_format($stateAchieve, 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small fw-bold">ACHIEVE %</span>
                                    <span class="fw-bold badge {{ $statePercent >= 100 ? 'bg-success' : 'bg-warning text-dark' }}">{{ number_format($statePercent, 1) }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Report Table -->
            <div class="card card-premium mb-4">
                <div class="card-header bg-dark text-white">
                    <h3 class="card-title mb-0 fw-bold">State-wise Budget Achievement Summary</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover text-center mb-0">
                            <thead>
                                <tr class="bg-light text-nowrap">
                                    <th rowspan="2" class="align-middle sticky-col-1">State Name</th>
                                    <th rowspan="2" class="align-middle">Total Target</th>
                                    <th rowspan="2" class="align-middle">Total Achive</th>
                                    <th rowspan="2" class="align-middle">Total Ach %</th>
                                    @foreach($months as $monthName => $monthNum)
                                        <th colspan="3">{{ ucfirst($monthName) }}</th>
                                    @endforeach
                                </tr>
                                <tr class="bg-light text-nowrap">
                                    @foreach($months as $monthName => $monthNum)
                                        <th>Target</th>
                                        <th>Achive</th>
                                        <th>Ach %</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($stateReport as $stateId => $data)
                                    <tr>
                                        <td class="text-start align-middle fw-bold sticky-col-1">{{ $data['name'] }}</td>
                                        <td class="align-middle fw-bold">{{ number_format($data['total_target'], 2) }}</td>
                                        @php
                                            $total_achive = array_sum($data['monthly_achievements']);
                                            $total_percent = $data['total_target'] > 0 ? ($total_achive / $data['total_target']) * 100 : ($total_achive > 0 ? 100 : 0);
                                        @endphp
                                        <td class="align-middle fw-bold">{{ number_format($total_achive, 2) }}</td>
                                        <td class="align-middle fw-bold {{ $total_percent >= 100 ? 'text-success' : 'text-danger' }}">{{ number_format($total_percent, 1) }}%</td>
                                        @foreach($months as $monthName => $monthNum)
                                            @php
                                                $target = $data['monthly_targets'][$monthName] ?? 0;
                                                $achive = $data['monthly_achievements'][$monthName] ?? 0;
                                                $percent = $target > 0 ? ($achive / $target) * 100 : ($achive > 0 ? 100 : 0);
                                            @endphp
                                            <td class="align-middle">{{ number_format($target, 0) }}</td>
                                            <td class="align-middle">{{ number_format($achive, 0) }}</td>
                                            <td class="align-middle {{ $percent >= 100 ? 'text-success' : 'text-danger' }} fw-bold">
                                                {{ number_format($percent, 1) }}%
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($months) * 3 + 4 }}" class="py-4 text-muted">No budget data available for this financial year.</td>
                                    </tr>
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
