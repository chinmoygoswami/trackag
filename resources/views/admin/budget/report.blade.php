@extends('admin.layout.layout')

@php
    $fmt2 = new \NumberFormatter('en_IN', \NumberFormatter::DECIMAL);
    $fmt2->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
    $fmt2->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

    $fmt0 = new \NumberFormatter('en_IN', \NumberFormatter::DECIMAL);
    $fmt0->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
    $fmt0->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
@endphp

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
<style>
    .card-premium {
        border: none;
        box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.08);
        border-radius: 14px;
    }
    .card-premium .card-header { border-radius: 14px 14px 0 0; }

    th.dtfc-fixed-left, td.dtfc-fixed-left {
        background-color: #fff !important;
        z-index: 1;
    }
    thead tr:nth-child(1) th.dtfc-fixed-left,
    thead tr:nth-child(2) th.dtfc-fixed-left {
        background-color: #f8f9fa !important;
        z-index: 3 !important;
    }

    /* Overall row */
    tr.overall-row td {
        background-color: #212529 !important;
        color: #fff !important;
        font-weight: 700 !important;
    }
    tr.overall-row td.dtfc-fixed-left {
        background-color: #212529 !important;
        color: #fff !important;
    }

    .pct-success { color: #198754; font-weight: 700; }
    .pct-danger  { color: #dc3545; font-weight: 700; }

    /* Summary stat cards */
    .stat-card {
        border-radius: 14px;
        border: none;
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }
    .stat-card:hover { transform: translateY(-3px); }
</style>
@endpush

@section('content')
<main class="app-main">
    <div class="app-content-header py-3 border-bottom bg-white">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-chart-bar text-primary me-2"></i> Budget Summary Report
                    </h3>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('budget.index') }}">Budget Plan</a></li>
                        <li class="breadcrumb-item active">Summary Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content py-4">
        <div class="container-fluid px-4">

            {{-- State Summary Cards --}}
            <div class="row mb-4">
                @foreach($stateReport as $stateId => $data)
                    @php
                        $stateTarget  = $data['total_target'];
                        $stateAchieve = array_sum($data['monthly_achievements']);
                        $statePercent = $stateTarget > 0
                            ? ($stateAchieve / $stateTarget) * 100
                            : ($stateAchieve > 0 ? 100 : 0);
                    @endphp
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                        <div class="stat-card card h-100 border-top border-3 {{ $statePercent >= 100 ? 'border-success' : 'border-warning' }}">
                            <div class="card-body p-3">
                                <div class="text-center mb-2 pb-2 border-bottom">
                                    <h6 class="fw-bold text-dark mb-0 text-truncate" title="{{ $data['name'] }}">
                                        <i class="fas fa-map-marker-alt text-primary me-1"></i>{{ $data['name'] }}
                                    </h6>
                                </div>
                                <div class="row text-center g-1">
                                    <div class="col-6 border-end">
                                        <span class="text-muted d-block" style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px;">Target</span>
                                        <span class="fw-bold text-dark d-block text-truncate" style="font-size:0.85rem;" title="₹{{ $fmt2->format($stateTarget) }}">
                                            ₹{{ $fmt2->format($stateTarget) }}
                                        </span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block" style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px;">Achievement</span>
                                        <span class="fw-bold text-success d-block text-truncate" style="font-size:0.85rem;" title="₹{{ $fmt2->format($stateAchieve) }}">
                                            ₹{{ $fmt2->format($stateAchieve) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-2 text-center bg-light rounded py-2">
                                    <span class="text-muted fw-bold d-block mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.5px;">Performance</span>
                                    <span class="fw-bold badge {{ $statePercent >= 100 ? 'bg-success' : 'bg-warning text-dark' }}" style="font-size:0.82rem;">
                                        {{ number_format($statePercent, 1) }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Filter --}}
            <div class="card card-premium mb-4">
                <div class="card-body">
                    <form action="{{ route('budget.report') }}" method="GET">
                        <div class="row align-items-end g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Financial Year</label>
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
                                <button type="submit" class="btn btn-warning w-100" style="border-radius:8px; font-weight:600;">
                                    <i class="fas fa-search me-1"></i> GO
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- State-wise Table --}}
            <div class="card card-premium mb-4">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="fas fa-table me-2"></i> State-wise Budget Achievement — {{ $financial_year }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <table id="budget-report-table" class="table table-bordered table-hover text-center mb-0" style="white-space: nowrap; font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" class="align-middle text-start">State Name</th>
                                <th rowspan="2" class="align-middle">Total Target</th>
                                <th rowspan="2" class="align-middle">Total Achive</th>
                                <th rowspan="2" class="align-middle">Ach %</th>
                                @foreach($months as $monthName => $monthNum)
                                    <th colspan="3">{{ ucfirst($monthName) }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach($months as $monthName => $monthNum)
                                    <th class="text-muted" style="font-size:11px;">Target</th>
                                    <th class="text-muted" style="font-size:11px;">Achive</th>
                                    <th class="text-muted" style="font-size:11px;">Ach %</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stateReport as $stateId => $data)
                                @php
                                    $total_achive  = array_sum($data['monthly_achievements']);
                                    $total_percent = $data['total_target'] > 0
                                        ? ($total_achive / $data['total_target']) * 100
                                        : ($total_achive > 0 ? 100 : 0);
                                @endphp
                                <tr>
                                    <td class="text-start align-middle fw-semibold">{{ $data['name'] }}</td>
                                    <td class="align-middle fw-bold">₹{{ $fmt2->format($data['total_target']) }}</td>
                                    <td class="align-middle fw-bold">₹{{ $fmt2->format($total_achive) }}</td>
                                    <td class="align-middle fw-bold {{ $total_percent >= 100 ? 'pct-success' : 'pct-danger' }}">
                                        {{ number_format($total_percent, 1) }}%
                                    </td>
                                    @foreach($months as $monthName => $monthNum)
                                        @php
                                            $target  = $data['monthly_targets'][$monthName] ?? 0;
                                            $achive  = $data['monthly_achievements'][$monthName] ?? 0;
                                            $percent = $target > 0
                                                ? ($achive / $target) * 100
                                                : ($achive > 0 ? 100 : 0);
                                        @endphp
                                        <td class="align-middle text-muted">{{ $fmt0->format($target) }}</td>
                                        <td class="align-middle">{{ $fmt0->format($achive) }}</td>
                                        <td class="align-middle fw-bold {{ $percent >= 100 ? 'pct-success' : 'pct-danger' }}">
                                            {{ number_format($percent, 1) }}%
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($months) * 3 + 4 }}" class="py-5 text-muted">
                                        <i class="fas fa-inbox fa-2x d-block mb-2 text-secondary"></i>
                                        No budget data available for this financial year.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if(count($stateReport) > 0)
                        @php
                            $overallAchiveTotal = array_sum($overallAchievements);
                            $overallPercent     = $overallTotalTarget > 0
                                ? ($overallAchiveTotal / $overallTotalTarget) * 100
                                : ($overallAchiveTotal > 0 ? 100 : 0);
                        @endphp
                        <tfoot>
                            <tr class="overall-row">
                                <td class="text-start">
                                    <i class="fas fa-globe me-1"></i> OVERALL
                                </td>
                                <td>₹{{ $fmt2->format($overallTotalTarget) }}</td>
                                <td>₹{{ $fmt2->format($overallAchiveTotal) }}</td>
                                <td class="{{ $overallPercent >= 100 ? 'text-success' : 'text-warning' }}">
                                    {{ number_format($overallPercent, 1) }}%
                                </td>
                                @foreach($months as $monthName => $monthNum)
                                    @php
                                        $oTarget  = $overallTargets[$monthName] ?? 0;
                                        $oAchive  = $overallAchievements[$monthName] ?? 0;
                                        $oPercent = $oTarget > 0
                                            ? ($oAchive / $oTarget) * 100
                                            : ($oAchive > 0 ? 100 : 0);
                                    @endphp
                                    <td>{{ $fmt0->format($oTarget) }}</td>
                                    <td>{{ $fmt0->format($oAchive) }}</td>
                                    <td class="{{ $oPercent >= 100 ? 'text-success' : 'text-warning' }}">
                                        {{ number_format($oPercent, 1) }}%
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>

        </div>
    </div>
</main>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
<script>
$(document).ready(function() {
    $('#budget-report-table').DataTable({
        responsive: false,
        scrollX: true,
        autoWidth: false,
        paging: false,
        searching: false,
        info: false,
        order: [],
        fixedColumns: { left: 4 }  // State, Total Target, Total Achive, Ach%
    });
});
</script>
@endpush
