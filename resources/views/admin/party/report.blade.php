@extends('admin.layout.layout')
@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
<style>
/* ---------- CLEAN TABLE DESIGN ---------- */

.table-custom {
    border-collapse: separate !important;
    border-spacing: 0 8px !important;
    width: 100%;
}

.table-custom thead tr {
    background: #f7f9fc;
    color: #2a3f54;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.table-custom thead th {
    padding: 12px;
    text-align: center;
    font-size: 13px;
    border-bottom: 2px solid #dee2e6 !important;
}

.table-custom tbody tr {
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transition: 0.2s;
}

.table-custom tbody tr:hover {
    transform: scale(1.01);
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

.table-custom tbody td {
    padding: 14px 12px;
    vertical-align: middle !important;
    font-size: 14px;
    border-top: none !important;
    text-align: center;
}

.table-custom tbody td:first-child {
    text-align: left;
    font-weight: 600;
}

.table-rounded {
    border-radius: 10px !important;
    overflow: hidden !important;
    border: 1px solid #e3e3e3 !important;
}

.clickable-count {
    color: #0d6efd;
    font-weight: bold;
    cursor: pointer;
    text-decoration: underline;
}

.clickable-count:hover {
    color: #0043a8;
}

</style>
@endpush
@section('content')
<main class="app-main">
    {{-- 🔷 Header --}}
    <div class="app-content-header py-3 bg-light border-bottom">
        <div class="container-fluid px-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div>
                    <h3 class="fw-bold mb-0 text-primary">
                        <i class="fas fa-chart-line me-2 text-secondary"></i> Party Visit Report
                    </h3>
                    <p class="text-muted small mb-0">Month-Wise Visit Summary for Financial Year</p>
                </div>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Party Visit Report</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="app-content py-4">
        <div class="container-fluid px-4">

            {{-- 🔍 Filters Section --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <form id="filterForm" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">State</label>
                            <select id="stateSelect" class="form-select">
                                <option value="">All</option>
                                @foreach ($states as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Employee Name</label>
                            <select id="employeeSelect" class="form-select">
                                <option value="">All</option>
                                @foreach ($employees as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Financial Year</label>
                            <select id="financialYearSelect" class="form-select">
                                @foreach ($financialYears as $fy)
                                    <option value="{{ $fy }}" {{ $fy == $currentFinancialYear ? 'selected' : '' }}>{{ $fy }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="button" id="btnFilter" class="btn btn-primary w-100 fw-bold">
                                <i class="fas fa-filter me-2"></i> Get Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- 📋 Report Table --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 text-primary fw-semibold">
                        <i class="fas fa-table me-2"></i>Visit Report
                    </h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-custom table-rounded dt-responsive" id="reportTableData" width="100%">
                        <!-- Table structure will be created dynamically via DataTables -->
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- 🔍 Visit Details Modal -->
    <div class="modal fade" id="visitDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i> Visit Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <div class="table-responsive">
                        <table class="table table-bordered bg-white table-hover" id="detailsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Visit Purpose</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="detailsData">
                                <!-- Details will be shown here -->
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
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
<script>
$(document).ready(function () {



    // 🔍 Get Report
    $('#filterForm').on('submit', function (event) {
        event.preventDefault();
        loadReportData();
    });
    $('#btnFilter').click(function () {
        loadReportData();
    });

    // Handle state change for employees
    $('#stateSelect').on('change', function () {
        let stateId = $(this).val();

        $.ajax({
            url: "{{ route('admin.getEmployeesByState') }}",
            type: "GET",
            data: { state_id: stateId },
            success: function (res) {
                let employeeSelect = $('#employeeSelect');
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

    let reportTable;
    let target = 0;

    // DataTables requests only the visible page; state and employee are optional.
    function loadReportData() {
        if (reportTable) {
            const pendingRequest = reportTable.settings()[0].jqXHR;
            if (pendingRequest && pendingRequest.readyState !== 4) pendingRequest.abort();
            reportTable.destroy();
            $('#reportTableData').empty();
        }
        const startYear = Number($('#financialYearSelect').val().split('-')[0]);
        const columns = [
            { title: 'Party Name', data: 'party_name', render: $.fn.dataTable.render.text() },
            { title: 'Employee Name', data: 'employee_name', render: $.fn.dataTable.render.text() }
        ];
        for (let index = 0; index < 12; index++) {
            const date = new Date(startYear, 3 + index, 1);
            columns.push({
                title: date.toLocaleString('en', { month: 'short' }) + '-' + String(date.getFullYear()).slice(-2),
                data: 'month_' + index,
                render: function (data, type, row) {
                    const count = data ? data.count : 0;
                    if (type !== 'display') return count;
                    const color = count < target ? 'text-danger' : 'text-success';
                    return count > 0
                        ? `<span class="clickable-count ${color}" title="Click to view details" data-customer="${row.customer_id}" data-year="${data.year}" data-month="${data.month}">${count}</span>`
                        : `<span class="${color} fw-bold">0</span>`;
                }
            });
        }
        reportTable = $('#reportTableData').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searchDelay: 400,
            ajax: {
                url: "{{ route('party-visit-report.data') }}",
                data: function (data) {
                    data.state_id = $('#stateSelect').val();
                    data.employee_id = $('#employeeSelect').val();
                    data.financial_year = $('#financialYearSelect').val();
                },
                dataSrc: function (response) {
                    target = response.target || 0;
                    return response.data;
                }
            },
            columns: columns,
            responsive: false,
            scrollX: true,
            scrollCollapse: true,
            fixedColumns: { leftColumns: 2 },
            autoWidth: false,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            language: { emptyTable: 'No records found for the selected criteria' }
        });
    }

    loadReportData();

    // Modal click handler for visits
    $(document).on('click', '.clickable-count', function() {
        let customerId = $(this).data('customer');
        let year = $(this).data('year');
        let month = $(this).data('month');

        $('#detailsData').html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');
        $('#visitDetailsModal').modal('show');

        $.ajax({
            url: "{{ route('party-visit-report.details') }}",
            type: 'GET',
            data: {
                customer_id: customerId,
                year: year,
                month: month
            },
            success: function(res) {
                if (res.data && res.data.length > 0) {
                    let html = '';
                    res.data.forEach(function(visit) {
                        html += `<tr>
                            <td>${visit.date}</td>
                            <td>${visit.check_in}</td>
                            <td>${visit.check_out}</td>
                            <td>${visit.visit_purpose}</td>
                            <td>${visit.remarks}</td>
                        </tr>`;
                    });
                    $('#detailsData').html(html);
                } else {
                    $('#detailsData').html('<tr><td colspan="5" class="text-center text-muted">No details found</td></tr>');
                }
            },
            error: function() {
                $('#detailsData').html('<tr><td colspan="5" class="text-center text-danger">Error loading details</td></tr>');
            }
        });
    });

});
</script>
@endpush
