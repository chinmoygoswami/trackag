@extends('admin.layout.layout')
@section('title', 'Expense Report | Trackag')
@push('styles')
<style>
    .action-buttons {
        display: flex;
        gap: 5px;
        align-items: center;
        white-space: nowrap;
    }

    .action-buttons .btn-sm {
        padding: 2px 6px;
        font-size: 11px;
    }
</style>
@endpush
@section('content')
<main class="app-main">

    <!-- Header Section -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Expenses Report</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Expenses Report
                            </a></li>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Expenses Report List</h5>
                    <div class="ms-auto text-end text-danger" style="max-width: 520px; line-height:1.3;">
                        <strong>Notes:</strong> Please ensure that all data is reviewed before granting approval for the expense.
                        After approval, the data cannot be modified.
                    </div>
                    
                </div>

                <!-- Card Body -->
                <div class="card-body table-responsive">
                    <div class="mb-2" id="bulkApproveBox" style="display:none;">
                        <button type="button" class="btn btn-success btn-sm" id="approveSelected">
                            Approve Selected
                        </button>
                    </div>
                    <form id="bulkApproveForm" action="{{ route('expense.bulk.approve') }}" method="POST">
                        @csrf
                        <input type="hidden" id="trip_ids_input" name="trip_ids">
                        <input type="hidden" id="selected_user_id" name="selected_user_id">
                    </form>


                    <form action="{{ route('expense.report') }}" method="GET" class="row g-3 mb-3">

                        <div class="col-md-2">
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

                        <div class="col-md-2">
                            <label class="form-label">State</label>
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

                        <div class="col-md-2">
                            <label class="form-label">Employee</label>
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

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <a href="{{ route('expense.report') }}" class="btn btn-sm btn-secondary w-100">
                                <i class="fas fa-sync"></i> Reset
                            </a>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="{{ route('expense.pdf.list') }}" class="btn btn-sm btn-primary">
                                Expense PDF List
                            </a>
                        </div>

                    </form>

                    <!-- 📋 Expense Table -->
                    <table id="expenses-report-table" class="table table-bordered table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
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
                            @forelse($data as $key => $report)
                                <tr>
                                    <td>{{ $key + 1 }}&nbsp;
                                        @if($report->pdf_status == 0)
                                        <input type="checkbox" class="rowCheckbox" name="trip_ids[]" value="{{ $report->id }}">
                                        @endif
                                    </td>
                                    <td>{{ $report->user->name ?? "" }}</td>
                                    <td>{{ \Carbon\Carbon::parse($report->trip_date)->format('d M Y') }}</td>
                                    <td>{{ $report->tourType->name ?? "-" }}</td>
                                    <td>{{ $report->start_time ?? "-" }}</td>
                                    <td>{{ $report->end_time ?? "-" }}</td>
                                    <td>{{ $report->place_to_visit ?? "-" }}</td>
                                    <td>{{ $report->travelMode->name ?? "-" }}</td>
                                    <td>{{ $report->starting_km ?? "-" }}</td>
                                    <td>{{ $report->end_km ?? "-" }}</td>
                                    <td>{{ $report->end_km - $report->starting_km }}</td>
                                    <td>{{ $report->total_distance_km ?? "-" }}</td>
                                    {{-- <td>{{ $report->end_km - $report->starting_km }}</td> --}}
                                    <td>{{ (($report->end_km ?? 0) - ($report->starting_km ?? 0)) - ($report->total_distance_km ?? 0) }}</td>
                                    <td>{{ $report->ta_exp ?? 0 }}</td>
                                    <td>{{ $report->da_exp ?? 0 }}</td>
                                    <td>{{ $report->other_exp ?? 0 }}</td>
                                    <td>{{ $report->total_exp ?? 0 }}</td>

                               
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="17" class="text-center">No expenses found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="10" class="text-end">TOTAL :</th>
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

                </div> <!-- /.card-body -->
            </div> <!-- /.card -->
        </div> <!-- /.container-fluid -->
    </div> <!-- /.app-content -->
</main>
@endsection


@push('scripts')
<script>
$(document).ready(function() {
    // Toggle All Checkboxes
    $("#selectAll").on("change", function() {
        $(".rowCheckbox").prop("checked", this.checked);
        toggleApproveButton();
    });

    // Single row checkbox click
    $(document).on("change", ".rowCheckbox", function () {
        if ($(".rowCheckbox:checked").length === $(".rowCheckbox").length) {
            $("#selectAll").prop("checked", true);
        } else {
            $("#selectAll").prop("checked", false);
        }
        toggleApproveButton();
    });

    // Show/hide Approve button
    function toggleApproveButton() {
        if ($(".rowCheckbox:checked").length > 0) {
            $("#bulkApproveBox").show();
        } else {
            $("#bulkApproveBox").hide();
        }
    }
});

$("#approveSelected").on("click", function () {
    let selected = $(".rowCheckbox:checked");
    let selectedUserId = $("#user_id").val();

    if(selectedUserId == ""){
        alert("Please select Employee");
        return;
    }

    if (selected.length == 0) {
        alert("Please select at least one record!");
        return;
    }

    Swal.fire({
        title: 'Are you sure?',
        text: "Approve " + selected.length + (selected.length === 1 ? " trip" : " trips") + " and generate PDF?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, approve it!'
    }).then((result) => {
        if (result.value) {
            // Collect IDs
            let ids = [];
            selected.each(function () {
                ids.push($(this).val());
            });
            console.log(ids);
            // Put IDs in hidden input
            $("#trip_ids_input").val(JSON.stringify(ids));
            $('#selected_user_id').val(selectedUserId);

            // Submit form
            $("#bulkApproveForm").submit();
        }
    });
});

$('#stateSelect').on('change', function () {
    let stateId = $(this).val();

    $.ajax({
        url: "{{ route('admin.getEmployeesByState') }}",
        type: "GET",
        data: { state_id: stateId },
        success: function (res) {
            let employeeSelect = $('#user_id');
            employeeSelect.empty();
            employeeSelect.append('<option value="">All</option>');

            $.each(res, function (key, emp) {
                employeeSelect.append(
                    `<option value="${emp.id}">${emp.name}</option>`
                );
            });
        }
    });
});

</script>
@endpush
