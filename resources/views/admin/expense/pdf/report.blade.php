<!DOCTYPE html>
<html>
<head>
    <title>Expense Report PDF</title>

    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size:10px; 
            padding: 10px; 
        }

        h3 { margin: 0 0 5px 0; }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* VERY IMPORTANT */
        }

        th, td {
            border:1px solid #000;
            padding: 3px;
            font-size:9px;
            word-wrap: break-word;
        }

        th {
            background:#f1f1f1;
        }

        /* Header Info Table */
        .header-table td {
            border:none;
            padding: 2px 4px;
            font-size:10px;
        }

        .header-table tr td:first-child {
            width: 130px;
            font-weight: bold;
        }

        /* FIX EXTRA-WIDE COLUMNS */
        th:nth-child(1), td:nth-child(1) { width: 20px; }  /* Sr */
        th:nth-child(2), td:nth-child(2) { width: 70px; }  /* Name */
        th:nth-child(3), td:nth-child(3) { width: 45px; }  /* Date */
        th:nth-child(4), td:nth-child(4) { width: 70px; }  /* Tour Type */
        th:nth-child(5), td:nth-child(5) { width: 45px; }  /* Start Time */
        th:nth-child(6), td:nth-child(6) { width: 45px; }  /* End Time */
        th:nth-child(7), td:nth-child(7) { width: 70px; }  /* Visit Places */
        th:nth-child(8), td:nth-child(8) { width: 70px; }  /* Travel Mode */
        th:nth-child(9), td:nth-child(9) { width: 40px; }  /* Start KM */
        th:nth-child(10), td:nth-child(10) { width: 40px; } /* End KM */
        th:nth-child(11), td:nth-child(11) { width: 40px; } /* Travel KM */
        th:nth-child(12), td:nth-child(12) { width: 40px; } /* GPS KM */
        th:nth-child(13), td:nth-child(13) { width: 40px; } /* KM diff */
        th:nth-child(14), td:nth-child(14),
        th:nth-child(15), td:nth-child(15),
        th:nth-child(16), td:nth-child(16),
        th:nth-child(17), td:nth-child(17) { width: 55px; } /* EXP Columns */

    </style>
</head>

<body>

<h3>Approved Expense Report</h3>

<!-- HEADER INFO -->
<table class="header-table">
    <tr>
        <td>Company Name:</td>
        <td>{{ $headerInfo['company_name'] ?? '-' }}</td>
        <td>Employee Name:</td>
        <td>{{ $headerInfo['employee_name'] ?? '-' }}</td>
    </tr>
    <tr>
        <td>Designation:</td>
        <td>{{ $headerInfo['designation'] ?? '-' }}</td>
        <td>Reporting To:</td>
        <td>{{ $headerInfo['reporting_to'] ?? '-' }}</td>
    </tr>
    <tr>
        <td>HQ:</td>
        <td>{{ $headerInfo['hq'] ?? '-' }}</td>
        <td>Duration:</td>
        <td>{{ $headerInfo['from_date'] ?? '-' }} - {{ $headerInfo['to_date'] ?? '-' }}</td>
    </tr>
</table>

<!-- TRIPS TABLE -->
<table>
    <thead>
        <tr>
            <th>Sr</th>
            <th>Name</th>
            <th>Date</th>
            <th>Tour Type</th>
            <th>Start Time</th>
            <th>End Time</th>
            <th>Visit Places</th>
            <th>Travel Mode</th>
            <th>Start KM</th>
            <th>End KM</th>
            <th>Approved KM</th>
            <th>GPS KM</th>
            <th>KM diff</th>
            <th>TA EXP</th>
            <th>DA EXP</th>
            <th>Other EXP</th>
            <th>Total</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($trips as $k => $t)
        <tr>
            <td>{{ $k+1 }}</td>
            <td>{{ $t->user->name ?? '-' }}</td>
            <td>{{ \Carbon\Carbon::parse($t->trip_date)->format('d M Y') }}</td>
            <td>{{ $t->tourType->name ?? '-' }}</td>
            <td>{{ $t->start_time ?? '-' }}</td>
            <td>{{ $t->end_time ?? '-' }}</td>
            <td>{{ $t->place_to_visit ?? '-' }}</td>
            <td>{{ $t->travelMode->name ?? '-' }}</td>
            <td>{{ $t->starting_km ?? 0 }}</td>
            <td>{{ $t->end_km ?? 0 }}</td>
            <td>{{ $t->payable_km ?? (($t->end_km ?? 0) - ($t->starting_km ?? 0)) }}</td>
            <td>{{ $t->total_distance_km ?? 0 }}</td>
            {{-- <td>{{ ($t->end_km ?? 0) - ($t->starting_km ?? 0) }}</td> --}}
            <td>{{ ($t->payable_km ?? (($t->end_km ?? 0) - ($t->starting_km ?? 0))) - ($t->total_distance_km ?? 0) }}</td>
            <td>{{ number_format($t->ta_exp ?? 0, 2) }}</td>
            <td>{{ number_format($t->da_exp ?? 0, 2) }}</td>
            <td>{{ number_format($t->other_exp ?? 0, 2) }}</td>
            <td>{{ number_format($t->total_exp ?? 0, 2) }}</td>
        </tr>
        @endforeach

        @if($trips->isEmpty())
        <tr>
            <td colspan="17" style="text-align:center;">No trips found.</td>
        </tr>
        @endif
    </tbody>

    <tfoot>
        <tr>
            <th colspan="10" class="text-end">TOTAL :</th>
            <th>{{ number_format($total_travel_km, 2) }}</th>
            <th>-</th>
            <th>-</th>
            <th>{{ number_format($total_ta ?? 0, 2) }}</th>
            <th>{{ number_format($total_da ?? 0, 2) }}</th>
            <th>{{ number_format($total_other ?? 0, 2) }}</th>
            <th>{{ number_format($total_total ?? 0, 2) }}</th>
        </tr>
    </tfoot>
</table>

</body>
</html>
