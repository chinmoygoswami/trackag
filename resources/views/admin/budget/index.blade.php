@extends('admin.layout.layout')

@push('styles')
<style>
    .budget-table-container {
        max-height: 70vh;
        overflow-y: auto;
    }
    .budget-table thead th {
        position: sticky;
        top: 0;
        background: #f8f9fa !important;
        z-index: 2;
        box-shadow: inset 0 -1px 0 #dee2e6;
    }
    .budget-table thead tr:nth-child(2) th {
        top: 48px;
    }
    .target-input:focus {
        border-color: #ffc107;
        box-shadow: 0 0 0 0.25rem rgba(255, 193, 7, 0.25);
    }
    /* Hide arrows from number inputs */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input[type=number] {
        -moz-appearance: textfield;
    }
    .card-premium {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 12px;
    }
    .card-premium .card-header {
        border-radius: 12px 12px 0 0;
    }
    
    /* Horizontal Sticky Columns */
    .sticky-col-1 {
        position: sticky !important;
        left: 0;
        z-index: 11 !important;
        background-color: #fff !important;
        border-right: 2px solid #dee2e6 !important;
    }
    .sticky-col-2 {
        position: sticky !important;
        left: 65px; /* Width of Action column */
        z-index: 11 !important;
        background-color: #fff !important;
        border-right: 2px solid #dee2e6 !important;
    }
    
    /* Ensure thead sticky columns are above tbody ones */
    thead th.sticky-col-1, thead th.sticky-col-2 {
        z-index: 15 !important;
        background-color: #f8f9fa !important;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    .btn-premium {
        border-radius: 8px;
        padding: 8px 20px;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .modal-content {
        border-radius: 15px;
        border: none;
    }
</style>
@endpush

@section('content')
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Budget Plan</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Budget Plan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="budgetLogsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-history me-2"></i> Budget Change History - <span id="logUserName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Month</th>
                                    <th class="text-end">Old Value</th>
                                    <th class="text-end">New Value</th>
                                    <th>Changed By</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <!-- Logs will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Filter Section -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="{{ route('budget.index') }}" method="GET">
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
                            <div class="col-md-3">
                                <label>Select State</label>
                                <select name="state_id" class="form-control select2">
                                    <option value="">All States</option>
                                    @foreach($states as $st)
                                        <option value="{{ $st->id }}" {{ $state_id == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Select Emp</label>
                                <select name="employee_id" class="form-control select2">
                                    <option value="">All Employees</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}" {{ $employee_id == $emp->id ? 'selected' : '' }}>{{ $emp->name }}</option>
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

            <!-- Listing Page Section -->
            <div class="card card-premium mb-4">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0 fw-bold">Budget Achievements</h3>
                    @if(auth()->user()->hasRole('master_admin') || auth()->user()->hasRole('sub_admin'))
                    <button type="button" class="btn btn-dark btn-sm btn-premium ms-auto" data-bs-toggle="modal" data-bs-target="#addBudgetModal">
                        <i class="fas fa-plus-circle me-1"></i> Set Target
                    </button>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover text-center mb-0">
                            <thead>
                                <tr class="bg-light text-nowrap">
                                    <th rowspan="2" class="align-middle sticky-col-1">Action</th>
                                    <th rowspan="2" class="align-middle sticky-col-2">Emp Name</th>
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
                                @forelse($budgets as $budget)
                                    @php
                                        $targetData = [];
                                        $total_achive = 0;
                                        foreach($monthList as $m) {
                                            $targetData[$m] = $budget->$m ?? 0;
                                            $total_achive += $budget->achievements[$m] ?? 0;
                                        }
                                        $targetDataJson = json_encode($targetData);
                                        $total_target = $budget->total_target ?? 0;
                                        $total_percent = $total_target > 0 ? ($total_achive / $total_target) * 100 : ($total_achive > 0 ? 100 : 0);
                                    @endphp
                                    <tr>
                                        <td class="align-middle sticky-col-1">
                                            <button type="button" class="btn btn-outline-warning btn-sm edit-budget-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#addBudgetModal"
                                                data-user-id="{{ $budget->user_id }}"
                                                data-state-id="{{ $budget->state_id }}"
                                                data-fy="{{ $budget->financial_year }}"
                                                data-total="{{ $budget->total_target }}"
                                                data-targets='{{ $targetDataJson }}'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info view-logs" 
                                                title="View History"
                                                data-user-id="{{ $budget->user_id }}"
                                                data-user-name="{{ $budget->user->name }}"
                                                data-fy="{{ $budget->financial_year }}">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </td>
                                        <td class="text-start align-middle sticky-col-2">
                                            <div class="fw-bold">{{ $budget->user->name }}</div>
                                        </td>
                                        <td class="align-middle fw-bold">{{ number_format($total_target, 2) }}</td>
                                        <td class="align-middle fw-bold">{{ number_format($total_achive, 2) }}</td>
                                        <td class="align-middle fw-bold {{ $total_percent >= 100 ? 'text-success' : 'text-danger' }}">{{ number_format($total_percent, 1) }}%</td>
                                        @foreach($months as $monthName => $monthNum)
                                            @php
                                                $target = $budget->$monthName ?? 0;
                                                $achive = $budget->achievements[$monthName] ?? 0;
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
                                        <td colspan="{{ count($months) * 3 + 5 }}" class="py-4 text-muted">No budget records found for selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

    </div>

    <!-- Set Target Modal -->
    <div class="modal fade" id="addBudgetModal" tabindex="-1" aria-labelledby="addBudgetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="addBudgetModalLabel">
                        <i class="fas fa-bullseye me-2"></i> Set Budget Target
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('budget.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Select State</label>
                                <select name="state_id" class="form-control select2-modal" required>
                                    <option value="">Select State</option>
                                    @foreach($states as $st)
                                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Select Employee</label>
                                <select name="user_id" class="form-control select2-modal" required>
                                    <option value="">Select Employee</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">FY Year</label>
                                <select name="financial_year" class="form-control select2-modal" required>
                                    @foreach($years as $fy)
                                        <option value="{{ $fy }}">{{ $fy }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <hr>

                        <div class="row mt-4">
                            <div class="col-md-7">
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <thead class="bg-light">
                                            <tr>
                                                <th width="30%">Month</th>
                                                <th width="50%">Target Amount</th>
                                                <th width="20%">% Share</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            @foreach($monthList as $m)
                                                <tr>
                                                    <td class="fw-bold">{{ ucfirst($m) }}</td>
                                                    <td>
                                                        <div class="input-group">
                                                            <span class="input-group-text">₹</span>
                                                            <input type="number" name="monthly_targets[{{ $m }}]" class="form-control target-input" data-month="{{ $m }}" step="0.01" value="0" min="0">
                                                        </div>
                                                    </td>
                                                    <td>
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
                                    <div class="card-body d-flex flex-column justify-content-center text-center">
                                        <h5 class="text-muted mb-3">Yearly Total Target</h5>
                                        <div class="input-group input-group-lg mb-4">
                                            <span class="input-group-text bg-warning border-warning text-dark">₹</span>
                                            <input type="number" id="total-target-input" name="total_target" class="form-control border-warning fw-bold" placeholder="Enter Total Yearly Budget" step="0.01" min="0">
                                        </div>
                                        <p class="text-muted small mb-4">
                                            <i class="fas fa-info-circle me-1"></i> 
                                            Entering a total will automatically split it across all 12 months.
                                        </p>
                                        <div class="py-4 border-top border-bottom mb-4">
                                            <h6 class="text-muted small text-uppercase mb-2">Calculated Total</h6>
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
<script>
    $(document).ready(function() {
        // Initialize select2 for modal if needed
        $('#addBudgetModal').on('shown.bs.modal', function () {
            $('.select2-modal').select2({
                dropdownParent: $('#addBudgetModal')
            });
        });

        // Handle Edit Button Click
        $('.edit-budget-btn').on('click', function() {
            const btn = $(this);
            const userId = btn.data('user-id');
            const stateId = btn.data('state-id');
            const fy = btn.data('fy');
            const total = btn.data('total');
            const targets = btn.data('targets');

            // Set values
            $('#addBudgetModal select[name="user_id"]').val(userId).trigger('change');
            $('#addBudgetModal select[name="state_id"]').val(stateId).trigger('change');
            $('#addBudgetModal select[name="financial_year"]').val(fy).trigger('change');
            $('#total-target-input').val(total);

            // Set monthly targets
            Object.keys(targets).forEach(month => {
                $(`.target-input[data-month="${month}"]`).val(targets[month]);
            });

            updateBigDisplay(total);
            updateShares(total);
            $('#addBudgetModalLabel').html('<i class="fas fa-edit me-2"></i> Edit Budget Target');
        });

        // Reset Modal for New Entry
        $('[data-bs-target="#addBudgetModal"]:not(.edit-budget-btn)').on('click', function() {
            $('#addBudgetModal form')[0].reset();
            $('.select2-modal').val('').trigger('change');
            $('#total-target-input').val('');
            $('.target-input').val(0);
            updateBigDisplay(0);
            updateShares(0);
            $('#addBudgetModalLabel').html('<i class="fas fa-plus-circle me-2"></i> Set Budget Target');
        });

        // Prevent minus sign, 'e', and arrow keys
        $('input[type="number"]').on('keydown', function(e) {
            if (['-', 'e', 'E'].includes(e.key)) {
                e.preventDefault();
            }
            // Disable Up and Down arrow keys
            if (e.keyCode === 38 || e.keyCode === 40) {
                e.preventDefault();
            }
        });

        // Prevent scroll wheel from changing values
        $('input[type="number"]').on('wheel', function(e) {
            $(this).blur();
        });

        // Monthly input change
        $('.target-input').on('input', function() {
            calculateTotalFromMonthly();
        });

        // Total input change (Auto-distribute)
        $('#total-target-input').on('input', function() {
            let total = parseFloat($(this).val()) || 0;
            let monthly = (total / 12).toFixed(2);
            
            $('.target-input').val(monthly);
            
            // Adjust last month to be precise
            let lastMonth = (total - (monthly * 11)).toFixed(2);
            $('.target-input[data-month="march"]').val(lastMonth);
            
            updateBigDisplay(total);
            updateShares(total);
        });

        function calculateTotalFromMonthly() {
            let total = 0;
            $('.target-input').each(function() {
                let val = parseFloat($(this).val()) || 0;
                total += val;
            });

            $('#total-target-input').val(total.toFixed(2));
            updateBigDisplay(total);
            updateShares(total);
        }

        function updateBigDisplay(total) {
            $('#total-budget-big').text(total.toLocaleString('en-IN', {minimumFractionDigits: 2}));
        }

        function updateShares(total) {
            $('.target-input').each(function() {
                let val = parseFloat($(this).val()) || 0;
                let month = $(this).data('month');
                let share = total > 0 ? (val / total * 100).toFixed(1) : 0;
                $(`#share-${month}`).text(share + '%');
            });
        }

        // View Logs
        $('.view-logs').on('click', function() {
            const userId = $(this).data('user-id');
            const userName = $(this).data('user-name');
            const fy = $(this).data('fy');
            
            $('#logUserName').text(userName);
            $('#logsTableBody').html('<tr><td colspan="5" class="text-center py-3"><div class="spinner-border text-info" role="status"></div></td></tr>');
            $('#budgetLogsModal').modal('show');

            $.ajax({
                url: "{{ route('budget.logs') }}",
                type: 'GET',
                data: {
                    user_id: userId,
                    financial_year: fy
                },
                success: function(response) {
                    let html = '';
                    if (response.logs.length > 0) {
                        response.logs.forEach(log => {
                            html += `
                                <tr>
                                    <td>${log.date}</td>
                                    <td>${log.month}</td>
                                    <td class="text-end">${log.old_value}</td>
                                    <td class="text-end text-primary fw-bold">${log.new_value}</td>
                                    <td>${log.admin_name}</td>
                                </tr>
                            `;
                        });
                    } else {
                        html = '<tr><td colspan="5" class="text-center text-muted py-3">No change history found for this budget.</td></tr>';
                    }
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