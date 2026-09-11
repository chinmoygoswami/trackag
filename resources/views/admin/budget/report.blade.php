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
<style>
    /* ── Font size: uniform 15px ── */
    #budget-report-table,
    #budget-report-table thead th,
    #budget-report-table tbody td,
    #budget-report-table tfoot td {
        font-size: 15px !important;
    }

    /* ── Scroll wrapper ── */
    .budget-scroll-wrap {
        overflow-x: auto;
        position: relative;
    }

    /* ── CSS sticky for State Name column ── */
    #budget-report-table .sticky-state {
        position: sticky;
        left: 0;
        z-index: 4;
        background: #fff;
        min-width: 160px;
        border-right: 1px solid #dee2e6 !important;
    }
    #budget-report-table .sticky-ttarget {
        position: sticky;
        left: 160px;
        z-index: 4;
        background: #fff;
        min-width: 150px;
        border-right: 1px solid #dee2e6 !important;
    }
    #budget-report-table .sticky-tachive {
        position: sticky;
        left: 310px;
        z-index: 4;
        background: #fff;
        min-width: 150px;
        border-right: 1px solid #dee2e6 !important;
    }
    #budget-report-table .sticky-pct {
        position: sticky;
        left: 460px;
        z-index: 4;
        background: #fff;
        min-width: 90px;
        border-right: 3px solid #495057 !important;
    }
    #budget-report-table thead .sticky-state,
    #budget-report-table thead .sticky-ttarget,
    #budget-report-table thead .sticky-tachive,
    #budget-report-table thead .sticky-pct { z-index: 6; }

    /* ── Header row 1 ── */
    #budget-report-table thead tr:nth-child(1) th {
        background-color: #343a40;
        color: #fff;
        border-color: #495057;
        vertical-align: middle;
    }
    /* ── Header row 2 (sub) ── */
    #budget-report-table thead tr:nth-child(2) th {
        background-color: #495057;
        color: #e9ecef;
        border-color: #6c757d;
        font-size: 12px !important;
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    /* sticky state header */
    #budget-report-table thead .sticky-state { background-color: #212529 !important; }
    #budget-report-table thead .sticky-ttarget { background-color: #0d6efd !important; }
    #budget-report-table thead .sticky-tachive { background-color: #198754 !important; }
    #budget-report-table thead .sticky-pct { background-color: #6f42c1 !important; }

    /* colour-coded summary cols */
    #budget-report-table thead .hdr-target { background-color: #0d6efd !important; }
    #budget-report-table thead .hdr-achive { background-color: #198754 !important; }
    #budget-report-table thead .hdr-pct    { background-color: #6f42c1 !important; }

    /* Month alternating colours */
    #budget-report-table thead th.month-hdr-even { background-color: #ffc107 !important; color: #212529 !important; border-color: #e0a800; }
    #budget-report-table thead th.month-hdr-odd  { background-color: #fd7e14 !important; color: #fff    !important; border-color: #e07010; }
    #budget-report-table thead th.sub-even { background-color: #fff3cd !important; color: #212529 !important; }
    #budget-report-table thead th.sub-odd  { background-color: #ffe0b2 !important; color: #212529 !important; }

    /* Row hover */
    #budget-report-table tbody tr:hover td { background-color: #fffde7 !important; }
    #budget-report-table tbody tr:hover .sticky-state,
    #budget-report-table tbody tr:hover .sticky-ttarget,
    #budget-report-table tbody tr:hover .sticky-tachive,
    #budget-report-table tbody tr:hover .sticky-pct { background-color: #fffde7 !important; }

    /* Overall row */
    #budget-report-table tfoot tr.overall-row td {
        background-color: #212529 !important;
        color: #fff !important;
        font-weight: 700 !important;
    }
    #budget-report-table tfoot tr.overall-row .sticky-state,
    #budget-report-table tfoot tr.overall-row .sticky-ttarget,
    #budget-report-table tfoot tr.overall-row .sticky-tachive,
    #budget-report-table tfoot tr.overall-row .sticky-pct {
        background-color: #212529 !important;
        color: #fff !important;
    }

    .pct-success { color: #198754 !important; font-weight: 700; }
    .pct-danger  { color: #dc3545 !important; font-weight: 700; }
    .total-target-cell { color: #0d6efd; font-weight: 700; }
    .total-achive-cell { color: #198754; font-weight: 700; }

    /* Stat cards */
    .stat-card {
        border: none; border-radius: 14px;
        box-shadow: 0 4px 16px rgba(0,0,0,.09);
        transition: transform .2s, box-shadow .2s;
    }
    .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.13); }
    .stat-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; }
    .stat-value { font-size: 13px; font-weight: 700; }

    .card-premium { border: none; box-shadow: 0 4px 18px rgba(0,0,0,.09); border-radius: 14px; }
    .card-premium .card-header { border-radius: 14px 14px 0 0; }
</style>
@endpush

@section('content')
<main class="app-main">

    {{-- Page Header --}}
    <div class="app-content-header py-3 border-bottom" style="background:linear-gradient(135deg,#212529,#343a40);">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold text-white">
                        <i class="fas fa-chart-bar text-warning me-2"></i> Budget Summary Report
                    </h3>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-warning">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('budget.index') }}" class="text-white-50">Budget Plan</a></li>
                        <li class="breadcrumb-item active text-white-50">Summary Report</li>
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
                                    <h6 class="fw-bold text-dark mb-0 text-truncate" style="font-size:13px;" title="{{ $data['name'] }}">
                                        <i class="fas fa-map-marker-alt text-primary me-1"></i>{{ $data['name'] }}
                                    </h6>
                                </div>
                                <div class="row text-center g-1 mb-2">
                                    <div class="col-6 border-end">
                                        <span class="stat-label text-muted d-block">Target</span>
                                        <span class="stat-value total-target-cell d-block text-truncate" title="₹{{ $fmt2->format($stateTarget) }}">
                                            ₹{{ $fmt2->format($stateTarget) }}
                                        </span>
                                    </div>
                                    <div class="col-6">
                                        <span class="stat-label text-muted d-block">Achievement</span>
                                        <span class="stat-value total-achive-cell d-block text-truncate" title="₹{{ $fmt2->format($stateAchieve) }}">
                                            ₹{{ $fmt2->format($stateAchieve) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-center bg-light rounded py-2">
                                    <span class="stat-label text-muted d-block mb-1">Performance</span>
                                    <span class="fw-bold badge {{ $statePercent >= 100 ? 'bg-success' : 'bg-warning text-dark' }}" style="font-size:12px;">
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
                <div class="card-header py-3" style="background:linear-gradient(90deg,#ffc107,#fd7e14); border-radius:14px 14px 0 0;">
                    <h6 class="mb-0 fw-bold text-dark" style="font-size:13px;"><i class="fas fa-filter me-2"></i>Filters</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('budget.report') }}" method="GET">
                        <div class="row align-items-end g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px;">Financial Year</label>
                                <select name="financial_year" class="form-control select2" style="font-size:13px;">
                                    @php
                                        $currentYear = date('Y'); $years = [];
                                        for($i=-1;$i<=2;$i++){ $y=$currentYear+$i; $years[]=$y.'-'.substr($y+1,2); }
                                    @endphp
                                    @foreach($years as $fy)
                                        <option value="{{ $fy }}" {{ $financial_year==$fy?'selected':'' }}>{{ $fy }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-warning w-100" style="border-radius:8px; font-weight:600; font-size:13px;">
                                    <i class="fas fa-search me-1"></i> GO
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Report Table --}}
            <div class="card card-premium mb-4">
                <div class="card-header py-3" style="background:linear-gradient(90deg,#212529,#343a40); border-radius:14px 14px 0 0;">
                    <h5 class="mb-0 fw-bold text-white" style="font-size:14px;">
                        <i class="fas fa-table text-warning me-2"></i> State-wise Budget Achievement — {{ $financial_year }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="budget-scroll-wrap">
                        <table id="budget-report-table" class="table table-bordered table-hover text-center mb-0" style="white-space:nowrap; width:100%;">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="align-middle text-start sticky-state">State Name</th>
                                    <th rowspan="2" class="align-middle sticky-ttarget">Total Target</th>
                                    <th rowspan="2" class="align-middle sticky-tachive">Total Achive</th>
                                    <th rowspan="2" class="align-middle sticky-pct">Ach %</th>
                                    @php $mi = 0; @endphp
                                    @foreach($months as $monthName => $monthNum)
                                        <th colspan="3" class="{{ $mi%2===0 ? 'month-hdr-even' : 'month-hdr-odd' }}">{{ ucfirst($monthName) }}</th>
                                        @php $mi++; @endphp
                                    @endforeach
                                </tr>
                                <tr>
                                    @php $mi = 0; @endphp
                                    @foreach($months as $monthName => $monthNum)
                                        <th class="{{ $mi%2===0 ? 'sub-even' : 'sub-odd' }}">Target</th>
                                        <th class="{{ $mi%2===0 ? 'sub-even' : 'sub-odd' }}">Achive</th>
                                        <th class="{{ $mi%2===0 ? 'sub-even' : 'sub-odd' }}">Ach %</th>
                                        @php $mi++; @endphp
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
                                        <td class="text-start align-middle fw-semibold sticky-state">{{ $data['name'] }}</td>
                                        <td class="align-middle total-target-cell sticky-ttarget">₹{{ $fmt2->format($data['total_target']) }}</td>
                                        <td class="align-middle total-achive-cell sticky-tachive">₹{{ $fmt2->format($total_achive) }}</td>
                                        <td class="align-middle sticky-pct {{ $total_percent>=100?'pct-success':'pct-danger' }}">
                                            {{ number_format($total_percent,1) }}%
                                        </td>
                                        @php $mi = 0; @endphp
                                        @foreach($months as $monthName => $monthNum)
                                            @php
                                                $target  = $data['monthly_targets'][$monthName] ?? 0;
                                                $achive  = $data['monthly_achievements'][$monthName] ?? 0;
                                                $percent = $target>0?($achive/$target)*100:($achive>0?100:0);
                                                $bg      = $mi%2!==0 ? 'style="background:#fff8f0;"' : '';
                                                $mi++;
                                            @endphp
                                            <td class="align-middle text-muted" {!! $bg !!}>{{ $fmt0->format($target) }}</td>
                                            <td class="align-middle fw-semibold" {!! $bg !!}>{{ $fmt0->format($achive) }}</td>
                                            <td class="align-middle {{ $percent>=100?'pct-success':'pct-danger' }}" {!! $bg !!}>{{ number_format($percent,1) }}%</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($months)*3+4 }}" class="py-5 text-muted text-center">
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
                                    <td class="text-start sticky-state">
                                        <i class="fas fa-globe text-warning me-1"></i> OVERALL
                                    </td>
                                    <td class="sticky-ttarget">₹{{ $fmt2->format($overallTotalTarget) }}</td>
                                    <td class="sticky-tachive">₹{{ $fmt2->format($overallAchiveTotal) }}</td>
                                    <td class="sticky-pct {{ $overallPercent>=100?'text-success':'text-warning' }}">{{ number_format($overallPercent,1) }}%</td>
                                    @php $mi = 0; @endphp
                                    @foreach($months as $monthName => $monthNum)
                                        @php
                                            $oTarget  = $overallTargets[$monthName] ?? 0;
                                            $oAchive  = $overallAchievements[$monthName] ?? 0;
                                            $oPercent = $oTarget>0?($oAchive/$oTarget)*100:($oAchive>0?100:0);
                                            $mi++;
                                        @endphp
                                        <td>{{ $fmt0->format($oTarget) }}</td>
                                        <td>{{ $fmt0->format($oAchive) }}</td>
                                        <td class="{{ $oPercent>=100?'text-success':'text-warning' }}">{{ number_format($oPercent,1) }}%</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>
@endsection
