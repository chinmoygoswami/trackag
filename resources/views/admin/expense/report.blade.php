@extends('admin.layout.layout')
@section('title', 'Expense Report | Trackag')
@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
<style>
    .expense-report-table-wrap { width: 100%; }
    #expenses-report-table { width: 100% !important; margin-bottom: 0; }
    #expenses-report-table th { white-space: nowrap; }
    #expenses-report-table td { vertical-align: middle; }
    #expenses-report-table td:nth-child(n+9),
    #expenses-report-table tfoot th:not(:first-child) {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    #expenses-report-table td:nth-child(7) { min-width: 180px; max-width: 300px; overflow-wrap: anywhere; }
    .expense-report-footer .pagination { margin-bottom: 0; flex-wrap: wrap; }
    .expense-report-note { max-width: 520px; }
    @media (max-width: 575.98px) {
        .expense-report-footer > * { width: 100%; }
    }
</style>
@endpush
@section('content')
<main class="app-main expense-report">

    <!-- Header Section -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Expenses Report</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Expenses Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Section -->
    <div class="app-content">
        <div class="container-fluid">
            <div class="card card-primary card-outline mb-4">

                <!-- Card Header -->
                <div class="card-header d-flex flex-column flex-lg-row justify-content-between align-items-start gap-2">
                    <h5 class="card-title mb-0">Expenses Report List</h5>
                    <div class="expense-report-note small text-danger">
                        <strong>Notes:</strong> Please ensure that all data is reviewed before granting approval for the expense.
                        After approval, the data cannot be modified.
                    </div>
                    
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <div class="mb-2" id="bulkApproveBox" style="display:none;">
                        <button type="button" class="btn btn-success btn-sm" id="approveSelected">
                            Approve Selected (<span id="selectedCount">0</span>)
                        </button>
                    </div>
                    <form id="bulkApproveForm" action="{{ route('expense.bulk.approve') }}" method="POST">
                        @csrf
                        <input type="hidden" id="trip_ids_input" name="trip_ids">
                        <input type="hidden" id="selected_user_id" name="selected_user_id">
                        <input type="hidden" id="override_limit_input" name="override_limit" value="">
                    </form>
                    {{-- Pass limit data to JS --}}
                    <script>
                        var monthlyLimit = {{ $monthlyLimit ?? 0 }};
                        var previouslyApprovedKm = {{ $previouslyApprovedKm ?? 0 }};
                    </script>


                    <form action="{{ route('expense.report') }}" method="GET" id="expenseFilters" class="row g-3 mb-3 align-items-end">

                        <div class="col-12 col-sm-6 col-xl-4">
                        <label for="month" class="form-label">Select Month</label>
                        <select name="month" id="month" class="form-select form-select-sm">
                            @php
                                $currentYear = now()->year;
                                $years = [$currentYear, $currentYear - 1];
                            @endphp

                            @foreach ($years as $year)
                                @for ($i = 1; $i <= 12; $i++)
                                    @php
                                        $value = $year . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                                        $monthName = DateTime::createFromFormat('!m', $i)->format('F');
                                    @endphp

                                    <option value="{{ $value }}" {{ $month == $value ? 'selected' : '' }}>
                                        {{ $monthName }} {{ $year }}
                                    </option>
                                @endfor
                            @endforeach
                        </select>
                        </div>

                        <div class="col-12 col-sm-6 col-xl-4">
                            <label for="stateSelect" class="form-label">State</label>
                            <select id="stateSelect" name="state_id" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->id }}"
                                        {{ request('state_id') == $state->id ? 'selected' : '' }}>
                                        {{ $state->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-xl-4">
                            <label for="user_id" class="form-label">Employee</label>
                            <select name="user_id" id="user_id" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach($employees as $user)
                                    <option value="{{ $user->id }}"
                                        {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" id="applyExpenseFilters" class="btn btn-sm btn-primary">
                                <i class="fas fa-filter me-1" aria-hidden="true"></i> Apply filters
                            </button>
                            <a href="{{ route('expense.report') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-sync me-1" aria-hidden="true"></i> Reset
                            </a>
                            <a href="{{ route('expense.pdf.list') }}" class="btn btn-sm btn-outline-primary ms-sm-auto">
                                Expense PDF List
                            </a>
                        </div>
                        <div class="col-12">
                            <p id="employeeLoadStatus" class="small text-danger mb-0" role="status" hidden></p>
                        </div>

                    </form>

                    <!-- 📋 Expense Table -->
                    <div class="expense-report-table-wrap border rounded" role="region" aria-label="Expense report table" tabindex="0">
                    <table id="expenses-report-table" class="table table-bordered table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col"><span class="me-1">#</span><input type="checkbox" id="selectAll" aria-label="Select all eligible trips on this page"></th>
                                <th>Name</th>
                                <th>Date</th>
                                <th>Tour Type</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Visit Places</th>
                                <th>Travel Mode</th>
                                <th>Start KM</th>
                                <th>End KM</th>
                                <th>Travel KM</th>
                                <th>GPS KM</th>
                                <th>KM diff</th>
                                <th>TA EXP</th>
                                <th>DA EXP</th>
                                <th>Other EXP</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @include('admin.expense.report_rows')
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="10" class="text-end">PAGE TOTAL :</th>
                                <th>{{ number_format($total_travel_km, 2) }}</th>
                                <th>-</th>
                                <th>-</th>
                                <th>{{ number_format($total_ta, 2) }}</th>
                                <th>{{ number_format($total_da, 2) }}</th>
                                <th>{{ number_format($total_other, 2) }}</th>
                                <th>{{ number_format($total_total, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                    <p class="text-muted small">Totals and “Select all” apply to this page.</p>


                </div> <!-- /.card-body -->
            </div> <!-- /.card -->
        </div> <!-- /.container-fluid -->
    </div> <!-- /.app-content -->
</main>
@endsection


@push('scripts')
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
<script>
$(document).ready(function() {
    function updateSelection() {
        const rows = $('.rowCheckbox');
        const count = rows.filter(':checked').length;
        $('#selectAll').prop('disabled', rows.length === 0)
            .prop('checked', rows.length > 0 && count === rows.length)
            .prop('indeterminate', count > 0 && count < rows.length);
        $('#selectedCount').text(count);
        $('#bulkApproveBox').toggle(count > 0);
    }
    $('#selectAll').on('change', function () {
        $('.rowCheckbox').prop('checked', this.checked);
        updateSelection();
    });
    $(document).on('change', '.rowCheckbox', updateSelection);
    updateSelection();

    const appliedFilters = {
        month: @json($month),
        state_id: @json(request('state_id', '')),
        user_id: @json(request('user_id', ''))
    };
    let pageTotals = null;
    $('#expenses-report-table').DataTable({
        processing: true,
        serverSide: true,
        ordering: false,
        searching: false,
        deferLoading: {{ $data->total() }},
        displayStart: {{ max(0, ($data->currentPage() - 1) * $data->perPage()) }},
        pageLength: {{ $data->perPage() }},
        lengthMenu: [10, 25, 50, 100],
        responsive: false,
        scrollX: true,
        scrollCollapse: true,
        fixedColumns: { leftColumns: 2 },
        autoWidth: false,
        ajax: {
            url: "{{ route('expense.report') }}",
            data: function (data) { Object.assign(data, appliedFilters); },
            dataSrc: function (response) {
                pageTotals = response.totals;
                const body = $('<tbody>').html(response.rows);
                return body.children('tr').map(function () {
                    return [$(this).children('td').map(function () { return this.innerHTML; }).get()];
                }).get();
            }
        },
        drawCallback: function () {
            updateSelection();
            if (pageTotals) {
                const cells = $(this.api().table().footer()).find('th');
                const format = $.fn.dataTable.render.number(',', '.', 2).display;
                cells.eq(1).text(format(pageTotals.travel_km));
                ['ta', 'da', 'other', 'total'].forEach(function (key, index) {
                    cells.eq(index + 4).text(format(pageTotals[key]));
                });
            }
        },
        language: { emptyTable: 'No expenses found.' }
    });


});

$("#approveSelected").on("click", function () {
    let selected = $(".rowCheckbox:checked");
    let selectedUserId = @json(request('user_id', ''));

    if(selectedUserId == ""){
        alert("Please select Employee");
        return;
    }

    if (selected.length == 0) {
        alert("Please select at least one record!");
        return;
    }

    // Calculate total KM of selected trips
    let selectedKm = 0;
    selected.each(function () {
        let startKm = parseFloat($(this).data('start-km')) || 0;
        let endKm   = parseFloat($(this).data('end-km')) || 0;
        selectedKm += (endKm - startKm);
    });

    function collectAndSubmit(overrideLimit) {
        let ids = [];
        selected.each(function () {
            ids.push($(this).val());
        });
        $("#trip_ids_input").val(JSON.stringify(ids));
        $('#selected_user_id').val(selectedUserId);
        $('#override_limit_input').val(overrideLimit);
        $("#bulkApproveForm").submit();
    }

    // Check if monthly limit is active and would be exceeded
    if (monthlyLimit > 0) {
        let remainingKm = monthlyLimit - previouslyApprovedKm;
        let newTotal = previouslyApprovedKm + selectedKm;

        if (newTotal > monthlyLimit) {
            // Limit will be exceeded — show override dialog
            Swal.fire({
                title: 'Monthly Limit Exceeded!',
                html: `<p>Monthly limit: <strong>${monthlyLimit} km</strong><br>
                       Already approved: <strong>${previouslyApprovedKm.toFixed(2)} km</strong><br>
                       Selected trips: <strong>${selectedKm.toFixed(2)} km</strong><br>
                       Remaining allowed: <strong>${remainingKm.toFixed(2)} km</strong></p>
                       <p>How would you like to proceed?</p>`,
                icon: 'warning',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonColor: '#3085d6',
                denyButtonColor: '#f0ad4e',
                cancelButtonColor: '#d33',
                confirmButtonText: `Approve up to limit (${remainingKm.toFixed(2)} km)`,
                denyButtonText: 'Enter Custom Limit',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Approve up to the configured limit
                    collectAndSubmit(monthlyLimit);
                } else if (result.isDenied) {
                    // Ask for a custom limit
                    Swal.fire({
                        title: 'Enter Custom KM Limit',
                        html: `<p>Currently approved: <strong>${previouslyApprovedKm.toFixed(2)} km</strong>. Enter the new total monthly limit for this approval:</p>`,
                        input: 'number',
                        inputAttributes: { min: 1, step: 1 },
                        inputPlaceholder: 'e.g. 2000',
                        showCancelButton: true,
                        confirmButtonText: 'Approve with Custom Limit',
                        inputValidator: (value) => {
                            if (!value || value <= 0) return 'Please enter a valid KM value!';
                        }
                    }).then((customResult) => {
                        if (customResult.isConfirmed) {
                            collectAndSubmit(parseFloat(customResult.value));
                        }
                    });
                }
            });
            return;
        }
    }

    // No limit issue — show normal confirmation
    Swal.fire({
        title: 'Are you sure?',
        text: "Approve " + selected.length + (selected.length === 1 ? " trip" : " trips") + " and generate PDF?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, approve it!'
    }).then((result) => {
        if (result.isConfirmed) {
            collectAndSubmit('');
        }
    });
});

let employeeRequest;
$('#stateSelect').on('change', function () {
    if (employeeRequest) employeeRequest.abort();
    const employeeSelect = $('#user_id');
    const filterButton = $('#applyExpenseFilters');
    const status = $('#employeeLoadStatus');
    employeeSelect.empty().append(new Option('Loading employees…', '')).prop('disabled', true);
    filterButton.prop('disabled', true);
    status.prop('hidden', true);
    employeeRequest = $.ajax({
        url: "{{ route('admin.getEmployeesByState') }}",
        type: 'GET',
        data: { state_id: $(this).val() },
        success: function (res) {
            employeeSelect.empty().append(new Option('All', ''));
            res.forEach(function (employee) {
                employeeSelect.append(new Option(employee.name, employee.id));
            });
        },
        error: function (xhr, state) {
            if (state === 'abort') return;
            employeeSelect.empty().append(new Option('All', ''));
            status.text('Could not load employees. Change the state to try again, or apply filters with All employees.').prop('hidden', false);
        },
        complete: function () {
            employeeSelect.prop('disabled', false);
            filterButton.prop('disabled', false);
        }
    });
});


</script>
@endpush
