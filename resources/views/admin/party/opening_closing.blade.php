@extends('admin.layout.layout')
@section('title', 'Partywise Opening & Closing | Trackag')

@section('content')
<main class="app-main">
    <div class="app-content-header py-3 bg-light border-bottom">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-primary"><i class="bi bi-wallet2 me-2"></i>Partywise Opening & Closing</h3>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Partywise Opening & Closing</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content py-4">
        <div class="container-fluid px-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 text-primary fw-semibold">Partywise Opening & Closing Details</h5>
                </div>
                <div class="card-body table-responsive">
                    <div class="row mb-3 align-items-end">
                        <div class="col-md-2">
                            <label for="min-date" class="form-label">From Date</label>
                            <input type="date" id="min-date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label for="max-date" class="form-label">To Date</label>
                            <input type="date" id="max-date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <button id="btn-bulk-delete" class="btn btn-danger btn-sm" style="display: none;">
                                <i class="bi bi-trash"></i> Bulk Delete
                            </button>
                        </div>
                    </div>
                    <table id="data-table" class="table table-bordered table-hover table-striped align-middle">
                        <thead class="table-light text-uppercase" style="font-size: 13px;">
                            <tr>
                                <th class="text-center" style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                                <th>Sr. No.</th>
                                <th>Master ID</th>
                                <th>Date</th>
                                <th>Party Name</th>
                                <th>Opening Balance Amt.</th>
                                <th>Debit Amt.</th>
                                <th>Credit Amt.</th>
                                <th>Closing Balance Amt.</th>
                                <th>Created At</th>
                                <th>Updated At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $records = \App\Models\TallyOpeningClosing::orderBy('id', 'desc')->get();
                            @endphp
                            @forelse($records as $index => $record)
                            <tr>
                                <td class="text-center"><input type="checkbox" class="row-checkbox" value="{{ $record->id }}"></td>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $record->master_id }}</td>
                                <td>{{ $record->date ? $record->date->format('d-m-Y') : '' }}</td>
                                <td>{{ $record->party_name }}</td>
                                <td>{{ number_format($record->opening_balance_amt, 2) }}</td>
                                <td>{{ number_format($record->debit_amt, 2) }}</td>
                                <td>{{ number_format($record->credit_amt, 2) }}</td>
                                <td>{{ number_format($record->closing_balance_amt, 2) }}</td>
                                <td>{{ $record->created_at ? $record->created_at->format('d-m-Y H:i:s') : '' }}</td>
                                <td>{{ $record->updated_at ? $record->updated_at->format('d-m-Y H:i:s') : '' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No data available</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var recordsCount = @json($records->count());
    if (recordsCount > 0) {
        
        $.fn.dataTable.ext.search.push(
            function( settings, data, dataIndex ) {
                var minDateStr = $('#min-date').val();
                var maxDateStr = $('#max-date').val();
                
                var min = minDateStr ? new Date(minDateStr) : null;
                if (min) min.setHours(0,0,0,0);
                
                var max = maxDateStr ? new Date(maxDateStr) : null;
                if (max) max.setHours(23,59,59,999);
                
                var dateStr = data[2] || ""; 
                var rowDate = null;
                var parts = dateStr.split('-');
                if (parts.length === 3) {
                    rowDate = new Date(parts[2], parts[1] - 1, parts[0]);
                }
                
                if (min && rowDate && rowDate < min) { return false; }
                if (max && rowDate && rowDate > max) { return false; }
                return true;
            }
        );

        var table = $('#data-table').DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 50,
            lengthMenu: [15, 25, 50, 100],
        });

        $('#min-date, #max-date').on('change', function () {
            table.draw();
        });

        $('#selectAll').on('click', function() {
            var rows = table.rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"].row-checkbox', rows).prop('checked', this.checked);
            toggleBulkDeleteBtn();
        });

        $('#data-table tbody').on('change', 'input[type="checkbox"].row-checkbox', function() {
            if (!this.checked) {
                var el = $('#selectAll').get(0);
                if (el && el.checked && ('indeterminate' in el)) {
                    el.indeterminate = true;
                }
            }
            toggleBulkDeleteBtn();
        });

        function toggleBulkDeleteBtn() {
            var checkedCount = table.$('input[type="checkbox"].row-checkbox:checked').length;
            if (checkedCount > 0) {
                $('#btn-bulk-delete').show();
            } else {
                $('#btn-bulk-delete').hide();
            }
        }

        $('#btn-bulk-delete').on('click', function() {
            var selectedIds = [];
            table.$('input[type="checkbox"].row-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length > 0) {
                if (confirm('Are you sure you want to delete the selected records?')) {
                    $.ajax({
                        url: "{{ route('tally.bulkDelete.openingClosing') }}",
                        type: 'POST',
                        data: {
                            _token: "{{ csrf_token() }}",
                            ids: selectedIds
                        },
                        success: function(response) {
                            if(response.success) {
                                Swal.fire('Success', 'Records deleted successfully.', 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', 'Failed to delete records.', 'error');
                            }
                        },
                        error: function(xhr) {
                            Swal.fire('Error', 'An error occurred while deleting records.', 'error');
                        }
                    });
                }
            }
        });
    }
});
</script>
@endpush
