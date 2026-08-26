@extends('admin.layout.layout')
@section('title', 'Party Sync | Trackag')

@section('content')
<main class="app-main">
    <div class="app-content-header py-3 bg-light border-bottom">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-primary"><i class="bi bi-arrow-repeat me-2"></i>Party Sync</h3>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Party Sync</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content py-4">
        <div class="container-fluid px-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 text-primary fw-semibold">Party Sync Details</h5>
                </div>
                <div class="card-body table-responsive">
                    <div class="row mb-3">
                        <div class="col-md-2">
                            <label for="min-date" class="form-label">From Date</label>
                            <input type="date" id="min-date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label for="max-date" class="form-label">To Date</label>
                            <input type="date" id="max-date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-8 text-end align-self-end">
                            <button id="btnBulkDelete" class="btn btn-danger btn-sm" style="display:none;"><i class="fas fa-trash me-1"></i> Bulk Delete</button>
                            <button id="btnAssignModal" class="btn btn-primary btn-sm ms-2" style="display:none;" data-bs-toggle="modal" data-bs-target="#assignModal">Assign to Customer</button>
                        </div>
                    </div>
                    <table id="data-table" class="table table-bordered table-hover table-striped align-middle">
                        <thead class="table-light text-uppercase" style="font-size: 13px;">
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>Sr. No.</th>
                                <th>Master ID</th>
                                <th>Group Name</th>
                                <th>Party Name</th>
                                <th>Phone 1</th>
                                <th>Phone 2</th>
                                <th>Contact Person</th>
                                <th>State</th>
                                <th>District</th>
                                <th>GST No</th>
                                <th>Party Create Date</th>
                                <th>Address</th>
                                <th>Email</th>
                                <th>PAN No</th>
                                <th>Credit Days</th>
                                <th>Credit Limit</th>
                                <th>Created At</th>
                                <th>Updated At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $records = \App\Models\TallyPartySync::whereNotExists(function ($query) {
                                    $query->select(\Illuminate\Support\Facades\DB::raw(1))
                                          ->from('customers')
                                          ->whereColumn('customers.party_code', 'tally_party_syncs.master_id')
                                          ->whereNotNull('customers.party_code');
                                })->orderBy('id', 'desc')->get();
                            @endphp
                            @forelse($records as $index => $record)
                            <tr>
                                <td><input type="checkbox" class="row-checkbox" value="{{ $record->id }}"></td>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $record->master_id }}</td>
                                <td>{{ $record->group_name }}</td>
                                <td>{{ $record->party_name }}</td>
                                <td>{{ $record->phone_1 }}</td>
                                <td>{{ $record->phone_2 }}</td>
                                <td>{{ $record->contact_person_name }}</td>
                                <td>{{ $record->state }}</td>
                                <td>{{ $record->district }}</td>
                                <td>{{ $record->gst_no }}</td>
                                <td>{{ $record->party_create_date ? $record->party_create_date->format('d-m-Y') : '' }}</td>
                                <td>{{ $record->address }}</td>
                                <td>{{ $record->email }}</td>
                                <td>{{ $record->pan_no }}</td>
                                <td>{{ $record->credit_days }}</td>
                                <td>{{ $record->credit_limit }}</td>
                                <td>{{ $record->created_at ? $record->created_at->format('d-m-Y H:i:s') : '' }}</td>
                                <td>{{ $record->updated_at ? $record->updated_at->format('d-m-Y H:i:s') : '' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="19" class="text-center text-muted py-4">No data available</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assignModalLabel">Assign Party to Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="assignForm">
            <div class="mb-3">
                <label for="assign_user_id" class="form-label">Assign Person</label>
                <select class="form-select" id="assign_user_id" required>
                    <option value="">Select Person...</option>
                    @foreach(\App\Models\User::where('id', '!=', 1)->get() as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnConfirmAssign">Assign</button>
      </div>
    </div>
  </div>
</div>

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
                
                var dateStr = data[11] || ""; 
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
            dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            buttons: [
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv"></i> Export CSV',
                    className: 'btn btn-success btn-sm mb-2'
                }
            ],
            columnDefs: [
                { orderable: false, targets: 0 }
            ]
        });

        $('#min-date, #max-date').on('change', function () {
            table.draw();
        });

        $('#selectAll').on('click', function() {
            var rows = table.rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"].row-checkbox', rows).prop('checked', this.checked);
            toggleAssignButton();
        });

        $('#data-table tbody').on('change', 'input[type="checkbox"].row-checkbox', function(){
            if(!this.checked){
                var el = $('#selectAll').get(0);
                if(el && el.checked && ('indeterminate' in el)){
                    el.indeterminate = true;
                }
            }
            toggleAssignButton();
        });

        function toggleAssignButton() {
            if ($('input[type="checkbox"].row-checkbox:checked').length > 0) {
                $('#btnAssignModal, #btnBulkDelete').show();
            } else {
                $('#btnAssignModal, #btnBulkDelete').hide();
            }
        }

        $('#btnBulkDelete').click(function() {
            if (!confirm('Are you sure you want to delete the selected parties?')) return;
            var selectedIds = $('input[type="checkbox"].row-checkbox:checked').map(function(){
                return $(this).val();
            }).get();

            $(this).prop('disabled', true).text('Deleting...');

            $.ajax({
                url: '{{ route("tally.bulkDelete.partySync") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    ids: selectedIds
                },
                success: function(response) {
                    if(response.success) {
                        alert('Parties deleted successfully!');
                        location.reload();
                    }
                },
                error: function(xhr) {
                    alert('An error occurred while deleting parties.');
                    $('#btnBulkDelete').prop('disabled', false).html('<i class="fas fa-trash me-1"></i> Bulk Delete');
                }
            });
        });

        $('#btnConfirmAssign').click(function() {
            var selectedIds = $('input[type="checkbox"].row-checkbox:checked').map(function(){
                return $(this).val();
            }).get();

            var userId = $('#assign_user_id').val();

            if (selectedIds.length === 0) {
                alert('Please select at least one party.');
                return;
            }
            if (!userId) {
                alert('Please select a person to assign.');
                return;
            }

            $(this).prop('disabled', true).text('Assigning...');

            $.ajax({
                url: '{{ route("party.sync.assign") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    party_ids: selectedIds,
                    user_id: userId
                },
                success: function(response) {
                    if(response.success) {
                        alert(response.message);
                        location.reload();
                    }
                },
                error: function(xhr) {
                    alert('An error occurred while assigning parties.');
                    $('#btnConfirmAssign').prop('disabled', false).text('Assign');
                }
            });
        });
    }
});
</script>
@endpush
