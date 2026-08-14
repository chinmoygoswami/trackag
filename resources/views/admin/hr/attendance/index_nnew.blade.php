@extends('admin.layout.layout')
@section('title', 'Attendance Status | Trackag')

@push('styles')
{{-- <style>
    .attendance-table th,
    .attendance-table td {
        font-size: 13px;
        padding: 4px;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }

    .user-name {
        text-align: left !important;
        font-weight: 600;
        min-width: 220px;
        background: #fff;
        position: sticky;
        left: 0;
        z-index: 2;
    }

    thead th:first-child {
        position: sticky;
        left: 0;
        z-index: 3;
        background: #f8f9fa;
    }

    select.attendance-select {
        border: none;
        font-weight: 600;
        font-size: 13px;
        padding: 2px 6px;
        width: 55px;
        text-align: center;
        border-radius: 4px;
        cursor: pointer;
    }

    /* .status-P  { background: #e8f5e9; color: #1b5e20; }
    .status-A  { background: #fdecea; color: #b71c1c; }
    .status-PN { background: #fff8e1; color: #e65100; }
    .status-NA { background: #f1f3f5; color: #6c757d; }
    .status-WO { background: #e9ecef; color: #495057; font-weight: 600; }
    .status-H {
    background: #e3f2fd;
    color: #0d47a1;
    font-weight: 600;
} */
 .status-P_FULL { background:#e8f5e9; color:#1b5e20; }
.status-P_HALF { background:#fff3cd; color:#856404; }
.status-A { background:#fdecea; color:#b71c1c; }
.status-NA { background:#f1f3f5; color:#6c757d; }
.status-WO { background:#e9ecef; color:#495057; font-weight:600; }
.status-H { background:#e3f2fd; color:#0d47a1; font-weight:600; }
</style> --}}
<style>
/* ====== TABLE BASE ====== */
.attendance-table {
    border-collapse: separate;
    border-spacing: 0;
}

.attendance-table th,
.attendance-table td {
    font-size: 12.5px;
    padding: 6px;
    text-align: center;
    vertical-align: middle;
    white-space: nowrap;
    border-color: #e5e7eb;
}

/* ====== HEADER ====== */
.attendance-table thead th {
    background: #f8fafc;
    font-weight: 700;
    color: #334155;
    border-bottom: 2px solid #e2e8f0;
}

/* Sticky Name Column */
.user-name {
    text-align: left !important;
    font-weight: 600;
    min-width: 220px;
    background: #ffffff;
    position: sticky;
    left: 0;
    z-index: 5;
    border-right: 2px solid #e5e7eb;
}

/* Sticky Header First Column */
thead th:first-child {
    position: sticky;
    left: 0;
    z-index: 6;
    background: #f1f5f9;
}

/* ====== SELECT BADGE STYLE ====== */
select.attendance-select {
    appearance: none;
    border: 1px solid transparent;
    font-weight: 700;
    font-size: 12px;
    padding: 4px 6px;
    width: 62px;
    border-radius: 6px;
    cursor: pointer;
    text-align: center;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.03);
}

select.attendance-select:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(59,130,246,.25);
}

/* ====== STATUS COLORS ====== */
.status-P_FULL {
    background: #ecfdf5;
    color: #047857;
    border-color: #6ee7b7;
}

.status-P_HALF {
    background: #fffbeb;
    color: #92400e;
    border-color: #fde68a;
}

.status-A {
    background: #fef2f2;
    color: #991b1b;
    border-color: #fecaca;
}

.status-NA {
    background: #f1f5f9;
    color: #475569;
}

.status-WO {
    background: #e2e8f0;
    color: #334155;
    font-weight: 700;
}

.status-H {
    background: #eff6ff;
    color: #1e40af;
    font-weight: 700;
}

/* Disabled (Holiday / WO) */
select[disabled] {
    opacity: 0.9;
    cursor: not-allowed;
}

/* ====== ROW HOVER ====== */
.attendance-table tbody tr:hover td {
    background: #f8fafc;
}

/* ====== FILTER BAR ====== */
.app-content-header {
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    padding: 10px 0;
}

.app-content-header h3 {
    font-weight: 700;
    color: #0f172a;
}

/* Dropdowns & Inputs */
.form-select,
.form-control {
    border-radius: 8px;
    font-size: 13px;
}

/* ====== BUTTONS ====== */
.btn-success {
    background: linear-gradient(135deg, #16a34a, #22c55e);
    border: none;
    font-weight: 600;
}

.btn-primary {
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    border: none;
    font-weight: 600;
}

/* ====== SCROLLBAR ====== */
.table-responsive::-webkit-scrollbar {
    height: 8px;
}
.table-responsive::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.table-responsive::-webkit-scrollbar-track {
    background: #f1f5f9;
}
</style>

@endpush

@section('content')
<main class="app-main">
    <div class="app-content">
        <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">Attendance Calendar</h3>
                </div>
                <div class="col-sm-6 text-end">
                    <form method="GET" action="{{ route('attendance.index') }}" class="d-inline-flex align-items-center">
                        <select id="stateSelect" name="state" class="form-select me-2">
                            <option value="">All States</option>
                            @foreach($states as $state)
                                <option value="{{ $state->id }}" {{ $stateFilter==$state->id?'selected':'' }}>
                                    {{ $state->name }}
                                </option>
                            @endforeach
                        </select>
                        <select id="employeeSelect" name="user_id" class="form-select me-2">
                            <option value="">All Users</option>
                            @foreach($users as $u)
                                @if($u->id != 1)
                                <option value="{{ $u->id }}" {{ $userFilter==$u->id?'selected':'' }}>
                                    {{ $u->name }}
                                </option>
                                @endif
                            @endforeach
                        </select>
                        <input type="month" name="month" value="{{ $month }}" class="form-control me-2" style="max-width: 160px;">
                        <a href="{{ route('attendance.export', request()->query()) }}"
                        class="btn btn-success me-2">
                        Export
                        </a>
                        <button type="submit" class="btn btn-primary me-2">Go</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
        <div class="container-fluid">

            <div class="card card-outline card-primary">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered attendance-table mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    @php $date = $startDate->copy(); @endphp
                                    @while ($date <= $endDate)
                                        <th>
                                            {{ $date->format('D') }} <br>
                                            <small>{{ $date->format('d-m') }}</small>
                                        </th>
                                        @php $date->addDay(); @endphp
                                    @endwhile
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($users as $user)
                                    <tr>
                                        <td class="user-name">{{ $user->name }}</td>

                                        @php $date = $startDate->copy(); @endphp
                                        @while ($date <= $endDate)
                                            @php
                                                $dateKey = $date->format('Y-m-d');
                                                $status = $attendance[$user->id][$dateKey] ?? 'NA';
                                            @endphp

                                            <td>
                                            @if(in_array($status,['WO','H']))
                                                <select class="attendance-select status-{{ $status }}" disabled>
                                                    <option>{{ $status }}</option>
                                                </select>
                                            @else
                                                <select
                                                    class="attendance-select status-{{ $status }}"
                                                    data-user="{{ $user->id }}"
                                                    data-date="{{ $dateKey }}"
                                                >
                                                    <option value="P_FULL" {{ $status=='P_FULL' ? 'selected' : '' }}>P (Full)</option>
                                                    <option value="P_HALF" {{ $status=='P_HALF' ? 'selected' : '' }}>P (Half)</option>
                                                    <option value="A" {{ $status=='A' ? 'selected' : '' }}>A</option>

                                                    @foreach($leaves as $leave)
                                                        <option value="{{ $leave->leave_code }}"
                                                            {{ $status==$leave->leave_code ? 'selected' : '' }}>
                                                            {{ $leave->leave_name }}
                                                        </option>
                                                    @endforeach

                                                    <option value="NA" {{ $status=='NA' ? 'selected' : '' }}>NA</option>
                                                </select>
                                            @endif
                                            </td>

                                            @php $date->addDay(); @endphp
                                        @endwhile
                                    </tr>
                                @endforeach
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
<script>
document.querySelectorAll('.attendance-select').forEach(el => {
    el.addEventListener('change', function () {

        let status = this.value;
        let userId = this.dataset.user;
        let date   = this.dataset.date;

        this.className = 'attendance-select status-' + status;

        fetch("{{ route('attendance.save') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                user_id: userId,
                date: date,
                status: status
            })
        });
    });
});

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
</script>
@endpush

