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
    #budget-index-table,
    #budget-index-table thead th,
    #budget-index-table tbody td {
        font-size: 15px !important;
    }

    /* ── Horizontal scroll wrapper ── */
    .budget-scroll-wrap {
        overflow-x: auto;
        position: relative;
    }

    /* ── CSS Sticky columns (no plugin needed) ── */
    /* Col widths: Action=90px, Employee=160px, TotalTarget=150px, TotalAchive=150px, Ach%=90px */
    #budget-index-table .sticky-action {
        position: sticky;
        left: 0;
        z-index: 4;
        background: #fff;
        min-width: 90px;
        border-right: 1px solid #dee2e6 !important;
    }
    #budget-index-table .sticky-emp {
        position: sticky;
        left: 90px;
        z-index: 4;
        background: #fff;
        min-width: 160px;
        border-right: 1px solid #dee2e6 !important;
    }
    #budget-index-table .sticky-ttarget {
        position: sticky;
        left: 250px;
        z-index: 4;
        background: #fff;
        min-width: 150px;
        border-right: 1px solid #dee2e6 !important;
    }
    #budget-index-table .sticky-tachive {
        position: sticky;
        left: 400px;
        z-index: 4;
        background: #fff;
        min-width: 150px;
        border-right: 1px solid #dee2e6 !important;
    }
    #budget-index-table .sticky-pct {
        position: sticky;
        left: 550px;
        z-index: 4;
        background: #fff;
        min-width: 90px;
        border-right: 3px solid #495057 !important;
    }
    #budget-index-table thead .sticky-action,
    #budget-index-table thead .sticky-emp,
    #budget-index-table thead .sticky-ttarget,
    #budget-index-table thead .sticky-tachive,
    #budget-index-table thead .sticky-pct {
        z-index: 6;
    }

    /* ── Table header row 1 ── */
    #budget-index-table thead tr:nth-child(1) th {
        background-color: #343a40;
        color: #fff;
        border-color: #495057;
        vertical-align: middle;
    }
    /* ── Table header row 2 (sub-headers) ── */
    #budget-index-table thead tr:nth-child(2) th {
        background-color: #495057;
        color: #e9ecef;
        border-color: #6c757d;
        font-size: 12px !important;
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    /* sticky col header backgrounds */
    #budget-index-table thead .sticky-action  { background-color: #212529 !important; }
    #budget-index-table thead .sticky-emp     { background-color: #212529 !important; }
    #budget-index-table thead .sticky-ttarget { background-color: #0d6efd !important; }
    #budget-index-table thead .sticky-tachive { background-color: #198754 !important; }
    #budget-index-table thead .sticky-pct     { background-color: #6f42c1 !important; }

    /* Fixed summary cols colour coding */
    #budget-index-table thead .hdr-target  { background-color: #0d6efd !important; }
    #budget-index-table thead .hdr-achive  { background-color: #198754 !important; }
    #budget-index-table thead .hdr-pct     { background-color: #6f42c1 !important; }

    /* Month group alternating colours */
    #budget-index-table thead th.month-hdr-even { background-color: #ffc107 !important; color: #212529 !important; border-color: #e0a800; }
    #budget-index-table thead th.month-hdr-odd  { background-color: #fd7e14 !important; color: #fff    !important; border-color: #e07010; }
    #budget-index-table thead th.sub-even { background-color: #fff3cd !important; color: #212529 !important; }
    #budget-index-table thead th.sub-odd  { background-color: #ffe0b2 !important; color: #212529 !important; }

    /* body hover on sticky cols */
    #budget-index-table tbody tr:hover td { background-color: #fffde7 !important; }
    #budget-index-table tbody tr:hover .sticky-action,
    #budget-index-table tbody tr:hover .sticky-emp,
    #budget-index-table tbody tr:hover .sticky-ttarget,
    #budget-index-table tbody tr:hover .sticky-tachive,
    #budget-index-table tbody tr:hover .sticky-pct { background-color: #fffde7 !important; }

    /* Pct colours */
    .pct-success { color: #198754 !important; font-weight: 700; }
    .pct-danger  { color: #dc3545 !important; font-weight: 700; }
    .total-target-cell { color: #0d6efd; font-weight: 700; }
    .total-achive-cell { color: #198754; font-weight: 700; }

    /* Cards */
    .card-premium { border: none; box-shadow: 0 4px 18px rgba(0,0,0,.09); border-radius: 14px; }
    .card-premium .card-header { border-radius: 14px 14px 0 0; }
    .btn-premium { border-radius: 8px; font-weight: 600; transition: all .22s; }
    .btn-premium:hover { transform: translateY(-2px); box-shadow: 0 5px 12px rgba(0,0,0,.15); }
    .modal-content { border-radius: 15px; border: none; }

    /* Input spinner hide */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }
    .target-input:focus { border-color: #ffc107; box-shadow: 0 0 0 .25rem rgba(255,193,7,.25); }
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
                        <i class="fas fa-bullseye text-warning me-2"></i> Budget Achievements
                    </h3>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-warning">Dashboard</a></li>
                        <li class="breadcrumb-item active text-white-50">Budget Plan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- History Modal --}}
    <div class="modal fade" id="budgetLogsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header text-white" style="background:#0dcaf0;">
                    <h5 class="modal-title fw-bold" style="font-size:13px;">
                        <i class="fas fa-history me-2"></i> Budget Change History — <span id="logUserName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm mb-0" style="font-size:13px;">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date &amp; Time</th>
                                    <th>Month</th>
                                    <th class="text-end">Old Value</th>
                                    <th class="text-end">New Value</th>
                                    <th>Changed By</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content py-4">
        <div class="container-fluid px-4">

            {{-- Filter Card --}}
            <div class="card card-premium mb-4">
                <div class="card-header py-3" style="background:linear-gradient(90deg,#ffc107,#fd7e14); border-radius:14px 14px 0 0;">
                    <h6 class="mb-0 fw-bold text-dark" style="font-size:13px;"><i class="fas fa-filter me-2"></i>Filters</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('budget.index') }}" method="GET">
                        <div class="row align-items-end g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px;">FY Year</label>
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
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px;">State</label>
                                <select name="state_id" class="form-control select2" style="font-size:13px;">
                                    <option value="">All States</option>
                                    @foreach($states as $st)
                                        <option value="{{ $st->id }}" {{ $state_id==$st->id?'selected':'' }}>{{ $st->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px;">Employee</label>
                                <select name="employee_id" class="form-control select2" style="font-size:13px;">
                                    <option value="">All Employees</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}" {{ $employee_id==$emp->id?'selected':'' }}>{{ $emp->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-warning btn-premium w-100" style="font-size:13px;">
                                    <i class="fas fa-search me-1"></i> GO
                                </button>
                            </div>
                            @if(auth()->user()->hasRole('master_admin') || auth()->user()->hasRole('sub_admin'))
                            <div class="col-md-1">
                                <button type="button" class="btn btn-dark btn-premium w-100" style="font-size:13px;"
                                    data-bs-toggle="modal" data-bs-target="#addBudgetModal" title="Set Target">
                                    <i class="fas fa-plus-circle"></i>
                                </button>
                            </div>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            {{-- Table Card --}}
            <div class="card card-premium mb-4">
                <div class="card-header d-flex justify-content-between align-items-center py-3"
                     style="background:linear-gradient(90deg,#ffc107,#fd7e14); border-radius:14px 14px 0 0;">
                    <h5 class="mb-0 fw-bold text-dark" style="font-size:14px;">
                        <i class="fas fa-table me-2"></i> Budget vs Achievement — {{ $financial_year }}
                    </h5>
                    @if(auth()->user()->hasRole('master_admin') || auth()->user()->hasRole('sub_admin'))
                    <button type="button" class="btn btn-dark btn-sm btn-premium" style="font-size:13px;"
                        data-bs-toggle="modal" data-bs-target="#addBudgetModal">
                        <i class="fas fa-plus-circle me-1"></i> Set Target
                    </button>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="budget-scroll-wrap">
                        <table id="budget-index-table" class="table table-bordered table-hover text-center mb-0" style="white-space:nowrap; width:100%;">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="align-middle sticky-action">Action</th>
                                    <th rowspan="2" class="align-middle text-start sticky-emp">Employee</th>
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
                                @forelse($budgets as $budget)
                                    @php
                                        $targetData   = [];
                                        $total_achive = 0;
                                        foreach($monthList as $m) {
                                            $targetData[$m] = $budget->$m ?? 0;
                                            $total_achive  += $budget->achievements[$m] ?? 0;
                                        }
                                        $targetDataJson = json_encode($targetData);
                                        $total_target   = $budget->total_target ?? 0;
                                        $total_percent  = $total_target > 0 ? ($total_achive/$total_target)*100 : ($total_achive>0?100:0);
                                    @endphp
                                    <tr>
                                        <td class="align-middle sticky-action">
                                            <button type="button" class="btn btn-outline-warning btn-sm edit-budget-btn"
                                                data-bs-toggle="modal" data-bs-target="#addBudgetModal"
                                                data-user-id="{{ $budget->user_id }}"
                                                data-state-id="{{ $budget->state_id }}"
                                                data-fy="{{ $budget->financial_year }}"
                                                data-total="{{ $budget->total_target }}"
                                                data-targets='{{ $targetDataJson }}' title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info view-logs ms-1"
                                                title="View History"
                                                data-user-id="{{ $budget->user_id }}"
                                                data-user-name="{{ $budget->user->name }}"
                                                data-fy="{{ $budget->financial_year }}">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </td>
                                        <td class="text-start align-middle fw-semibold sticky-emp">{{ $budget->user->name }}</td>
                                        <td class="align-middle total-target-cell sticky-ttarget">₹{{ $fmt2->format($total_target) }}</td>
                                        <td class="align-middle total-achive-cell sticky-tachive">₹{{ $fmt2->format($total_achive) }}</td>
                                        <td class="align-middle sticky-pct {{ $total_percent>=100?'pct-success':'pct-danger' }}">
                                            {{ number_format($total_percent,1) }}%
                                        </td>
                                        @php $mi = 0; @endphp
                                        @foreach($months as $monthName => $monthNum)
                                            @php
                                                $target  = $budget->$monthName ?? 0;
                                                $achive  = $budget->achievements[$monthName] ?? 0;
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
                                        <td colspan="{{ count($months)*3+5 }}" class="py-5 text-muted text-center">
                                            <i class="fas fa-inbox fa-2x d-block mb-2 text-secondary"></i>
                                            No budget records found for the selected filters.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Set / Edit Target Modal --}}
    <div class="modal fade" id="addBudgetModal" tabindex="-1" aria-labelledby="addBudgetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header text-dark" style="background:linear-gradient(90deg,#ffc107,#fd7e14);">
                    <h5 class="modal-title fw-bold" id="addBudgetModalLabel" style="font-size:14px;">
                        <i class="fas fa-bullseye me-2"></i> Set Budget Target
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('budget.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row mb-3 g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold" style="font-size:13px;">State</label>
                                <select name="state_id" class="form-control select2-modal" required style="font-size:13px;">
                                    <option value="">Select State</option>
                                    @foreach($states as $st)
                                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold" style="font-size:13px;">Employee</label>
                                <select name="user_id" class="form-control select2-modal" required style="font-size:13px;">
                                    <option value="">Select Employee</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold" style="font-size:13px;">Financial Year</label>
                                <select name="financial_year" class="form-control select2-modal" required style="font-size:13px;">
                                    @foreach($years as $fy)
                                        <option value="{{ $fy }}">{{ $fy }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <hr>
                        <div class="row mt-3 g-3">
                            <div class="col-md-7">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle" style="font-size:13px;">
                                        <thead style="background:#212529; color:#fff;">
                                            <tr>
                                                <th width="30%">Month</th>
                                                <th width="50%">Target Amount</th>
                                                <th width="20%" class="text-center">% Share</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($monthList as $m)
                                                <tr>
                                                    <td class="fw-semibold">{{ ucfirst($m) }}</td>
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text text-dark fw-bold" style="background:#ffc107; border-color:#ffc107; font-size:13px;">₹</span>
                                                            <input type="number" name="monthly_targets[{{ $m }}]"
                                                                class="form-control target-input"
                                                                data-month="{{ $m }}"
                                                                step="0.01" value="0" min="0" style="font-size:13px;">
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-secondary share-percent" id="share-{{ $m }}" style="font-size:12px;">0%</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="card border-0 h-100" style="background:linear-gradient(135deg,#212529,#343a40); border-radius:12px;">
                                    <div class="card-body d-flex flex-column justify-content-center text-center p-4">
                                        <h6 class="text-warning text-uppercase fw-bold mb-3" style="letter-spacing:.5px; font-size:13px;">Yearly Total Target</h6>
                                        <div class="input-group input-group-lg mb-3">
                                            <span class="input-group-text text-dark fw-bold" style="background:#ffc107; border-color:#ffc107; font-size:13px;">₹</span>
                                            <input type="number" id="total-target-input" name="total_target"
                                                class="form-control fw-bold" style="border-color:#ffc107; font-size:13px;"
                                                placeholder="Enter Total Yearly Budget" step="0.01" min="0">
                                        </div>
                                        <p class="text-white-50 mb-4" style="font-size:12px;">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Entering a total auto-splits it across all 12 months.
                                        </p>
                                        <div class="py-3 border-top border-secondary mb-4">
                                            <span class="text-white-50 fw-bold d-block mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Calculated Total</span>
                                            <h1 class="display-5 fw-bold text-warning" id="total-budget-big">0.00</h1>
                                        </div>
                                        <button type="submit" class="btn btn-warning btn-lg btn-premium w-100" style="font-size:13px;">
                                            <i class="fas fa-save me-2"></i> Save Budget Plan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
$(document).ready(function() {

    $('#addBudgetModal').on('shown.bs.modal', function () {
        $('.select2-modal').select2({ dropdownParent: $('#addBudgetModal') });
    });

    $('.edit-budget-btn').on('click', function() {
        const btn = $(this), targets = btn.data('targets');
        $('#addBudgetModal select[name="user_id"]').val(btn.data('user-id')).trigger('change');
        $('#addBudgetModal select[name="state_id"]').val(btn.data('state-id')).trigger('change');
        $('#addBudgetModal select[name="financial_year"]').val(btn.data('fy')).trigger('change');
        $('#total-target-input').val(btn.data('total'));
        Object.keys(targets).forEach(m => { $(`.target-input[data-month="${m}"]`).val(targets[m]); });
        updateBigDisplay(btn.data('total'));
        updateShares(btn.data('total'));
        $('#addBudgetModalLabel').html('<i class="fas fa-edit me-2"></i> Edit Budget Target');
    });

    $('[data-bs-target="#addBudgetModal"]:not(.edit-budget-btn)').on('click', function() {
        $('#addBudgetModal form')[0].reset();
        $('.select2-modal').val('').trigger('change');
        $('#total-target-input').val('');
        $('.target-input').val(0);
        updateBigDisplay(0); updateShares(0);
        $('#addBudgetModalLabel').html('<i class="fas fa-plus-circle me-2"></i> Set Budget Target');
    });

    $('input[type="number"]').on('keydown', function(e) {
        if (['-','e','E'].includes(e.key) || e.keyCode===38 || e.keyCode===40) e.preventDefault();
    });
    $('input[type="number"]').on('wheel', function() { $(this).blur(); });

    $('.target-input').on('input', calcFromMonthly);

    $('#total-target-input').on('input', function() {
        let total = parseFloat($(this).val()) || 0;
        let monthly = (total / 12).toFixed(2);
        $('.target-input').val(monthly);
        $('.target-input[data-month="march"]').val((total - (monthly * 11)).toFixed(2));
        updateBigDisplay(total); updateShares(total);
    });

    function calcFromMonthly() {
        let total = 0;
        $('.target-input').each(function() { total += parseFloat($(this).val()) || 0; });
        $('#total-target-input').val(total.toFixed(2));
        updateBigDisplay(total); updateShares(total);
    }

    function updateBigDisplay(total) {
        $('#total-budget-big').text(Number(total).toLocaleString('en-IN', { minimumFractionDigits: 2 }));
    }

    function updateShares(total) {
        $('.target-input').each(function() {
            let val = parseFloat($(this).val()) || 0, m = $(this).data('month');
            $(`#share-${m}`).text(total > 0 ? (val / total * 100).toFixed(1) + '%' : '0%');
        });
    }

    $('.view-logs').on('click', function() {
        const userId = $(this).data('user-id'), userName = $(this).data('user-name'), fy = $(this).data('fy');
        $('#logUserName').text(userName);
        $('#logsTableBody').html('<tr><td colspan="5" class="text-center py-3"><div class="spinner-border text-info" role="status"></div></td></tr>');
        $('#budgetLogsModal').modal('show');
        $.ajax({
            url: "{{ route('budget.logs') }}", type: 'GET', data: { user_id: userId, financial_year: fy },
            success: function(res) {
                let html = res.logs.length
                    ? res.logs.map(l => `<tr><td>${l.date}</td><td>${l.month}</td><td class="text-end">${l.old_value}</td><td class="text-end text-primary fw-bold">${l.new_value}</td><td>${l.admin_name}</td></tr>`).join('')
                    : '<tr><td colspan="5" class="text-center text-muted py-3">No change history found.</td></tr>';
                $('#logsTableBody').html(html);
            },
            error: function() { $('#logsTableBody').html('<tr><td colspan="5" class="text-center text-danger py-3">Error loading history.</td></tr>'); }
        });
    });
});
</script>
@endpush