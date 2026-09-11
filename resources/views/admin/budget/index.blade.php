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
    .target-input:focus {
        border-color: #ffc107;
        box-shadow: 0 0 0 0.25rem rgba(255, 193, 7, 0.25);
    }
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }

    .card-premium {
        border: none;
        box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.08);
        border-radius: 14px;
    }
    .card-premium .card-header { border-radius: 14px 14px 0 0; }

    /* DataTables FixedColumns overrides */
    th.dtfc-fixed-left, td.dtfc-fixed-left {
        background-color: #fff !important;
        z-index: 1;
    }
    thead tr:nth-child(1) th.dtfc-fixed-left,
    thead tr:nth-child(2) th.dtfc-fixed-left {
        background-color: #f8f9fa !important;
        z-index: 3 !important;
    }
    .dataTables_scrollBody { border-bottom: 1px solid #dee2e6; }

    .btn-premium {
        border-radius: 8px;
        padding: 8px 20px;
        font-weight: 600;
        transition: all 0.25s;
    }
    .btn-premium:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.12); }
    .modal-content { border-radius: 15px; border: none; }

    /* Percent badges in table */
    .pct-success { color: #198754; font-weight: 700; }
    .pct-danger  { color: #dc3545; font-weight: 700; }
</style>
@endpush

@section('content')
<main class="app-main">
    <div class="app-content-header py-3 border-bottom bg-white">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-bullseye text-warning me-2"></i> Budget Achievements
                    </h3>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Budget Plan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- History Modal --}}
    <div class="modal fade" id="budgetLogsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-history me-2"></i> Budget Change History — <span id="logUserName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm">
                            <thead class="table-light">
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
                <div class="card-body">
                    <form action="{{ route('budget.index') }}" method="GET">
                        <div class="row align-items-end g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted small text-uppercase">FY Year</label>
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
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted small text-uppercase">State</label>
                                <select name="state_id" class="form-control select2">
                                    <option value="">All States</option>
                                    @foreach($states as $st)
                                        <option value="{{ $st->id }}" {{ $state_id == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Employee</label>
                                <select name="employee_id" class="form-control select2">
                                    <option value="">All Employees</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}" {{ $employee_id == $emp->id ? 'selected' : '' }}>{{ $emp->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-warning btn-premium w-100">
                                    <i class="fas fa-search me-1"></i> GO
                                </button>
                            </div>
                            @if(auth()->user()->hasRole('master_admin') || auth()->user()->hasRole('sub_admin'))
                            <div class="col-md-1">
                                <button type="button" class="btn btn-dark btn-premium w-100"
                                    data-bs-toggle="modal" data-bs-target="#addBudgetModal">
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
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-table me-2"></i> Budget vs Achievement — {{ $financial_year }}
                    </h5>
                    @if(auth()->user()->hasRole('master_admin') || auth()->user()->hasRole('sub_admin'))
                    <button type="button" class="btn btn-dark btn-sm btn-premium"
                        data-bs-toggle="modal" data-bs-target="#addBudgetModal">
                        <i class="fas fa-plus-circle me-1"></i> Set Target
                    </button>
                    @endif
                </div>
                <div class="card-body p-0">
                    <table id="budget-index-table" class="table table-bordered table-hover text-center mb-0" style="white-space: nowrap; font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" class="align-middle">Action</th>
                                <th rowspan="2" class="align-middle text-start">Employee</th>
                                <th rowspan="2" class="align-middle">Total Target</th>
                                <th rowspan="2" class="align-middle">Total Achive</th>
                                <th rowspan="2" class="align-middle">Ach %</th>
                                @foreach($months as $monthName => $monthNum)
                                    <th colspan="3" class="bg-light">{{ ucfirst($monthName) }}</th>
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
                                    $total_percent  = $total_target > 0
                                        ? ($total_achive / $total_target) * 100
                                        : ($total_achive > 0 ? 100 : 0);
                                @endphp
                                <tr>
                                    <td class="align-middle">
                                        <button type="button" class="btn btn-outline-warning btn-sm edit-budget-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#addBudgetModal"
                                            data-user-id="{{ $budget->user_id }}"
                                            data-state-id="{{ $budget->state_id }}"
                                            data-fy="{{ $budget->financial_year }}"
                                            data-total="{{ $budget->total_target }}"
                                            data-targets='{{ $targetDataJson }}'
                                            title="Edit">
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
                                    <td class="text-start align-middle fw-semibold">{{ $budget->user->name }}</td>
                                    <td class="align-middle fw-bold">₹{{ $fmt2->format($total_target) }}</td>
                                    <td class="align-middle fw-bold">₹{{ $fmt2->format($total_achive) }}</td>
                                    <td class="align-middle fw-bold {{ $total_percent >= 100 ? 'pct-success' : 'pct-danger' }}">
                                        {{ number_format($total_percent, 1) }}%
                                    </td>
                                    @foreach($months as $monthName => $monthNum)
                                        @php
                                            $target  = $budget->$monthName ?? 0;
                                            $achive  = $budget->achievements[$monthName] ?? 0;
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
                                    <td colspan="{{ count($months) * 3 + 5 }}" class="py-5 text-muted">
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

    {{-- Set / Edit Target Modal --}}
    <div class="modal fade" id="addBudgetModal" tabindex="-1" aria-labelledby="addBudgetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="addBudgetModalLabel">
                        <i class="fas fa-bullseye me-2"></i> Set Budget Target
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('budget.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row mb-3 g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">State</label>
                                <select name="state_id" class="form-control select2-modal" required>
                                    <option value="">Select State</option>
                                    @foreach($states as $st)
                                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Employee</label>
                                <select name="user_id" class="form-control select2-modal" required>
                                    <option value="">Select Employee</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Financial Year</label>
                                <select name="financial_year" class="form-control select2-modal" required>
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
                                    <table class="table table-bordered table-sm align-middle">
                                        <thead class="table-light">
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
                                                            <span class="input-group-text bg-warning-subtle border-warning text-dark">₹</span>
                                                            <input type="number" name="monthly_targets[{{ $m }}]"
                                                                class="form-control target-input"
                                                                data-month="{{ $m }}"
                                                                step="0.01" value="0" min="0">
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-secondary share-percent" id="share-{{ $m }}">0%</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="card bg-light border-0 h-100">
                                    <div class="card-body d-flex flex-column justify-content-center text-center p-4">
                                        <h6 class="text-muted text-uppercase fw-bold mb-3">Yearly Total Target</h6>
                                        <div class="input-group input-group-lg mb-3">
                                            <span class="input-group-text bg-warning border-warning text-dark">₹</span>
                                            <input type="number" id="total-target-input" name="total_target"
                                                class="form-control border-warning fw-bold"
                                                placeholder="Enter Total Yearly Budget"
                                                step="0.01" min="0">
                                        </div>
                                        <p class="text-muted small mb-4">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Entering a total auto-splits it across all 12 months.
                                        </p>
                                        <div class="py-3 border-top border-bottom mb-4">
                                            <span class="text-muted small text-uppercase fw-bold d-block mb-1">Calculated Total</span>
                                            <h1 class="display-5 fw-bold text-warning" id="total-budget-big">0.00</h1>
                                        </div>
                                        <button type="submit" class="btn btn-warning btn-lg btn-premium w-100">
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
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
<script>
$(document).ready(function() {

    // DataTable with 2 fixed columns (Action + Employee) + monthly scrollbar
    $('#budget-index-table').DataTable({
        responsive: false,
        scrollX: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [15, 25, 50, 100],
        order: [],
        fixedColumns: { left: 5 }  // Action, Employee, Total Target, Total Achive, Ach%
    });

    // Select2 inside modal
    $('#addBudgetModal').on('shown.bs.modal', function () {
        $('.select2-modal').select2({ dropdownParent: $('#addBudgetModal') });
    });

    // Handle Edit Button
    $('.edit-budget-btn').on('click', function() {
        const btn     = $(this);
        const targets = btn.data('targets');
        $('#addBudgetModal select[name="user_id"]').val(btn.data('user-id')).trigger('change');
        $('#addBudgetModal select[name="state_id"]').val(btn.data('state-id')).trigger('change');
        $('#addBudgetModal select[name="financial_year"]').val(btn.data('fy')).trigger('change');
        $('#total-target-input').val(btn.data('total'));
        Object.keys(targets).forEach(month => {
            $(`.target-input[data-month="${month}"]`).val(targets[month]);
        });
        updateBigDisplay(btn.data('total'));
        updateShares(btn.data('total'));
        $('#addBudgetModalLabel').html('<i class="fas fa-edit me-2"></i> Edit Budget Target');
    });

    // Reset for New Entry
    $('[data-bs-target="#addBudgetModal"]:not(.edit-budget-btn)').on('click', function() {
        $('#addBudgetModal form')[0].reset();
        $('.select2-modal').val('').trigger('change');
        $('#total-target-input').val('');
        $('.target-input').val(0);
        updateBigDisplay(0);
        updateShares(0);
        $('#addBudgetModalLabel').html('<i class="fas fa-plus-circle me-2"></i> Set Budget Target');
    });

    // Block minus, e, arrows on number inputs
    $('input[type="number"]').on('keydown', function(e) {
        if (['-', 'e', 'E'].includes(e.key) || e.keyCode === 38 || e.keyCode === 40) e.preventDefault();
    });
    $('input[type="number"]').on('wheel', function() { $(this).blur(); });

    // Monthly input change
    $('.target-input').on('input', calculateTotalFromMonthly);

    // Total input — auto-distribute
    $('#total-target-input').on('input', function() {
        let total   = parseFloat($(this).val()) || 0;
        let monthly = (total / 12).toFixed(2);
        $('.target-input').val(monthly);
        $('.target-input[data-month="march"]').val((total - (monthly * 11)).toFixed(2));
        updateBigDisplay(total);
        updateShares(total);
    });

    function calculateTotalFromMonthly() {
        let total = 0;
        $('.target-input').each(function() { total += parseFloat($(this).val()) || 0; });
        $('#total-target-input').val(total.toFixed(2));
        updateBigDisplay(total);
        updateShares(total);
    }

    function updateBigDisplay(total) {
        $('#total-budget-big').text(Number(total).toLocaleString('en-IN', { minimumFractionDigits: 2 }));
    }

    function updateShares(total) {
        $('.target-input').each(function() {
            let val   = parseFloat($(this).val()) || 0;
            let month = $(this).data('month');
            $(`#share-${month}`).text(total > 0 ? (val / total * 100).toFixed(1) + '%' : '0%');
        });
    }

    // View Logs
    $('.view-logs').on('click', function() {
        const userId   = $(this).data('user-id');
        const userName = $(this).data('user-name');
        const fy       = $(this).data('fy');
        $('#logUserName').text(userName);
        $('#logsTableBody').html('<tr><td colspan="5" class="text-center py-3"><div class="spinner-border text-info" role="status"></div></td></tr>');
        $('#budgetLogsModal').modal('show');
        $.ajax({
            url: "{{ route('budget.logs') }}",
            type: 'GET',
            data: { user_id: userId, financial_year: fy },
            success: function(res) {
                let html = res.logs.length
                    ? res.logs.map(log => `
                        <tr>
                            <td>${log.date}</td>
                            <td>${log.month}</td>
                            <td class="text-end">${log.old_value}</td>
                            <td class="text-end text-primary fw-bold">${log.new_value}</td>
                            <td>${log.admin_name}</td>
                        </tr>`).join('')
                    : '<tr><td colspan="5" class="text-center text-muted py-3">No change history found.</td></tr>';
                $('#logsTableBody').html(html);
            },
            error: function() {
                $('#logsTableBody').html('<tr><td colspan="5" class="text-center text-danger py-3">Error loading history.</td></tr>');
            }
        });
    });
});
</script>
@endpush