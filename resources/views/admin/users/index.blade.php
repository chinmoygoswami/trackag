@extends('admin.layout.layout')
@section('title', 'Users | Trackag')

@section('content')
<style>
    /* Remove browser's native spinner from number inputs */
    input[type=number]::-webkit-inner-spin-button, 
    input[type=number]::-webkit-outer-spin-button { 
        -webkit-appearance: none; 
        margin: 0; 
    }
    input[type=number] {
        -moz-appearance: textfield; /* Firefox */
    }

    td small strong {
        color: #444;
        min-width: 80px;
        display: inline-block;
    }
    td small {
        color: #555;
    }
    .table td {
        vertical-align: middle;
    }
    .user-settings {
        display: grid;
        gap: 6px;
        min-width: 128px;
        padding: 8px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #f8fafc;
    }
    .user-settings .btn {
        width: 100%;
        min-height: 34px;
        border: 0;
        border-radius: 5px;
        white-space: nowrap;
        color: #fff;
        font-weight: 600;
        transition: background-color 0.2s ease, box-shadow 0.2s ease;
    }
    .user-settings .btn-tada {
        background: #174b7a;
    }
    .user-settings .btn-tada:hover,
    .user-settings .btn-tada:focus {
        background: #10395e;
        color: #fff;
        box-shadow: 0 0 0 3px rgba(23, 75, 122, 0.18);
    }
    .user-settings .btn-state-access {
        background: #0f766e;
    }
    .user-settings .btn-state-access:hover,
    .user-settings .btn-state-access:focus {
        background: #0b5f59;
        color: #fff;
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.18);
    }
    .user-settings .btn-reset-password {
        background: #b45309;
    }
    .user-settings .btn-reset-password:hover,
    .user-settings .btn-reset-password:focus {
        background: #92400e;
        color: #fff;
        box-shadow: 0 0 0 3px rgba(180, 83, 9, 0.18);
    }
</style>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Users</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">User Management</a></li>
                        <li class="breadcrumb-item active">Users</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary card-outline">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title">User Control Panel</h3>
                            @can('create_users')
                            @php
                                $canCreateUser = true;

                                if(!auth()->user()->hasRole('master_admin')){
                                    $canCreateUser = $currentUsers < $maxUsers;
                                }
                            @endphp
                            @if($canCreateUser)
                            <a href="{{ route('users.create') }}" style="float: right;" class="btn  btn-primary ms-auto">
                                 Add New User
                            </a>
                            @endif
                            @endcan
                        </div>

                        <div class="card-body">

                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <strong>Success:</strong> {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            <div class="mb-3">
                            <form method="GET" action="{{ route('users.index') }}" id="filterForm">
                                <div class="row g-2 align-items-end">

                                    <!-- State -->
                                    <div class="col-md-2">
                                        <label class="form-label">State</label>
                                        <select name="state_id" class="form-select">
                                            <option value="">All</option>
                                            @foreach($states as $state)
                                                <option value="{{ $state->id }}" {{ request('state_id') == $state->id ? 'selected' : '' }}>
                                                    {{ $state->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Employee Name -->
                                    <div class="col-md-2">
                                        <label class="form-label">Employee Name</label>
                                        <input type="text" name="name" value="{{ request('name') }}" class="form-control" placeholder="Enter name">
                                    </div>

                                    <!-- Designation -->
                                    <div class="col-md-2">
                                        <label class="form-label">Designation</label>
                                        <select name="designation_id" class="form-select">
                                            <option value="">All</option>
                                            @foreach($designations as $desig)
                                                <option value="{{ $desig->id }}" {{ request('designation_id') == $desig->id ? 'selected' : '' }}>
                                                    {{ $desig->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Mobile No -->
                                    <div class="col-md-2">
                                        <label class="form-label">Mobile No</label>
                                        <input type="text" name="mobile" value="{{ request('mobile') }}" class="form-control" placeholder="Enter mobile">
                                    </div>

                                    <!-- Status -->
                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="">All</option>
                                            <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>

                                    <!-- Buttons -->
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2 px-3">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <a href="{{ route('users.index') }}" class="btn btn-secondary me-2 px-3">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                        <a href="{{ route('users.pdf', request()->query()) }}" class="btn btn-danger me-2 px-3">
                                            PDF
                                        </a>
                                    </div>

                                </div>
                            </form>
                        </div>


                            <div class="table-responsive" style="max-height: 600px;">
                                <table id="users-table" class="table table-hover align-middle mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr class="text-center">
                                            <th>No</th>
                                            <th>Employee Name</th>
                                            <th>Designation</th>
                                            <th>Reporting To</th>
                                            <th>Address</th>
                                            <th>Other Info</th>
                                            <th>Settings</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($users as $user)
                                            @php
                                                $loggedInUserId = Auth::id();
                                                $isOnline = $user->last_seen && \Carbon\Carbon::parse($user->last_seen)->gt(now()->subMinutes(5));
                                                $rowClass = $user->id === $loggedInUserId ? 'table-primary' : ($isOnline ? 'table-success' : (!$user->is_active ? 'table-secondary' : ''));
                                                $gender = strtolower($user->gender ?? '');
                                                $defaultImage = $gender === 'female' ? asset('admin/images/avatar-female.png') : asset('admin/images/avatar-male.png');
                                                $userImage = $user->image ? asset('storage/' . $user->image) : $defaultImage;
                                            @endphp
                                            <tr class="{{ $rowClass }}">
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="{{ $userImage }}" alt="avatar" class="rounded-circle me-2" width="45" height="45">
                                                        <div style="line-height: 1.3;">
                                                            <strong>{{ $user->name }}</strong><br>
                                                            <small><strong>Mobile:</strong> {{ $user->mobile ?? '-' }}</small><br>
                                                            @if ($user->id === $loggedInUserId)
                                                                <span class="badge bg-success ms-1">You</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $user->designation->name ?? '-' }}</td>
                                                <td>{{ $user->reportingManager->name ?? '-' }}</td>
                                                <td style="line-height: 1.3;">
                                                    <small><strong>State:</strong> {{ $user->state->name ?? '-' }}</small><br>
                                                    <small><strong>District:</strong> {{ $user->district->name ?? '-' }}</small><br>
                                                    <small><strong>Tehsil:</strong> {{ $user->tehsil->name ?? '-' }}</small><br>
                                                    <small><strong>Village:</strong> {{ $user->village ?? '-' }}</small>
                                                </td>

                                                <td style="line-height: 1.3;">
                                                    <small><strong>Joining Date:</strong> {{ $user->joining_date ? \Carbon\Carbon::parse($user->joining_date)->format('d-m-Y') : '-' }}</small><br>
                                                    <small><strong>Headquarter:</strong> {{ $user->headquarter ?? '-' }}</small><br>
                                                    
                                                    <small><strong>Roles:</strong>
                                                        @if ($user->roles && count($user->roles))
                                                            @foreach ($user->getRoleNames() as $role)
                                                                <span class="badge bg-info text-dark">{{ $role }}</span>
                                                            @endforeach
                                                        @else
                                                            <span class="text-muted">No Role</span>
                                                        @endif
                                                    </small><br>
                                                    <!-- <small><strong>Depo:</strong> {{ $user->depos?->depo_name ?? '-' }}</small> -->
                                                </td>
                                                <td>
                                                    <div class="user-settings">
                                                        @if($user->id != 1)
                                                            <button type="button" class="btn btn-sm btn-tada"
                                                                onclick="openSlabModal('{{ $user->id }}')">
                                                                TA/DA
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-state-access state_access"
                                                                data-user-id="{{ $user->id }}">
                                                                State Access
                                                            </button>
                                                        @endif
                                                        <button type="button" class="btn btn-sm btn-reset-password reset-password"
                                                            data-user-id="{{ $user->id }}">
                                                            Reset Password
                                                        </button>
                                                    </div>
                                                </td>

                                                
                                                <td class="text-center">
                                                    @if ($user->is_active)
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i> Active
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-times-circle me-1"></i> Inactive
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                @php
                                                    $authUser = auth()->user();
                                                    $isSubAdmin = $authUser->hasRole('sub_admin');
                                                    $isOwnRecord = $authUser->id === $user->id;
                                                @endphp

                                                {{-- ✅ Case 1: Logged-in user is SUB_ADMIN --}}
                                                @if($isSubAdmin)
                                                    {{-- Hide all buttons if it's their own record --}}
                                                    @if(!$isOwnRecord)
                                                        {{-- Sub_admin can only Edit + Activate/Deactivate other users --}}
                                                        <a href="{{ route('users.edit', $user) }}" 
                                                        class="btn btn-sm btn-outline-warning me-1" 
                                                        title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>

                                                        <form action="{{ route('users.toggle', $user) }}" 
                                                            method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" 
                                                                    class="btn btn-sm {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" 
                                                                    title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                                                <i class="fas {{ $user->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                                            </button>
                                                        </form>
                                                    @endif

                                                {{-- ✅ Case 2: Any other role (admin, superadmin, etc.) --}}
                                                @else
                                                    @can('view_users')
                                                    <a href="{{ route('users.show', $user) }}" 
                                                    class="btn btn-sm btn-outline-info me-1" 
                                                    title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    @endcan

                                                    @can('edit_users')
                                                    <a href="{{ route('users.edit', $user) }}" 
                                                    class="btn btn-sm btn-outline-warning me-1" 
                                                    title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    @endcan

                                                    @can('edit_users')
                                                    <form action="{{ route('users.toggle', $user) }}" 
                                                        method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" 
                                                                class="btn btn-sm {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" 
                                                                title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                                            <i class="fas {{ $user->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                                        </button>
                                                    </form>
                                                    @endcan

                                                    @can('delete_users')
                                                    @if(auth()->user()->hasRole('master_admin'))
                                                    <form action="{{ route('users.destroy', $user) }}" 
                                                        method="POST" class="d-inline" 
                                                        onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" 
                                                                class="btn btn-sm btn-outline-danger" 
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    @endif
                                                    @endcan
                                                @endif
                                            </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted">No users found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="resetPasswordModalLabel">Reset User Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="resetPasswordForm">
              @csrf
              <input type="hidden" name="user_id" id="modalUserId">
              <div class="mb-3">
                <label for="newPassword" class="form-label">New Password</label>
                <input type="password" class="form-control" id="newPassword" name="password" required>
              </div>
            </form>
            <div id="resetPasswordMessage"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" id="resetPasswordBtn">Reset Password</button>
          </div>
        </div>
      </div>
    </div>

    

{{-- Depo Access Modal --}}
<div class="modal fade" id="depoAccessModal" tabindex="-1" aria-labelledby="depoAccessModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="depoAccessModalLabel">Depo Access</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="depoAccessForm">
          @csrf
          <input type="hidden" name="user_id" id="depoModalUserId">

          {{-- State Dropdown --}}
          {{-- <div class="mb-3">
            <label for="stateId" class="form-label">State Name</label>
            <select class="form-select" name="state_id" id="stateId" required>
                <option value="">-- Select State --</option>
                @foreach($states as $state)
                    <option value="{{ $state->id }}">{{ $state->name }}</option>
                @endforeach
            </select>
          </div> --}}

          {{-- Depo Multiple Select --}}
          <div class="mb-3">
            <label for="depoId" class="form-label">Depo Name</label>
            <select class="form-select" name="depo_id[]" id="depoId" multiple="multiple" required></select>
          </div>

        </form>
        <div id="depoAccessMessage"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="saveDepoAccessBtn">Save</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="stateAccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Assign States</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="stateAccessForm">
          @csrf
          <input type="hidden" name="user_id" id="stateModalUserId">

          <div class="mb-3">
            <label class="form-label">Select States</label>
            <select name="state_ids[]" id="stateIds" class="form-select" multiple required>
              @foreach($states as $state)
                @if(empty($stateAccesCompany) || in_array($state->id, $stateAccesCompany))
                <option value="{{ $state->id }}">{{ $state->name }}</option>
                @endif
            @endforeach
            </select>
          </div>
        </form>
        <div id="stateAccessMessage"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="saveStateBtn">Save</button>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="slabModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User TA/DA Slab</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="slabForm">
                <div class="modal-body">
                    @csrf
                    <input type="hidden" name="user_id" id="user_id">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Slab Type</label>
                            <select class="form-control" id="slabSelect" name="slab">
                                <option value="Slab Wise">Slab Wise</option>
                                <option value="Individual">Individual</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Designation</label>
                            <select class="form-control" id="designation_id_modal" name="designation_id"></select>
                        </div>
                        <br><br>

                        <div class="col-md-12" id="approved_bills_in_da_slab_wise" style="display: none;">
                            <label class="form-label fw-bold">Approved Bills in DA</label>
                            <select name="approved_bills_in_da_slab_wise[]" id="approvedSlabBills" class="form-select" multiple>
                            <option value="Petrol">Petrol</option>
                            <option value="Food">Food</option>
                            <option value="Accommodation">Accommodation</option>
                            <option value="Travel">Travel</option>
                            <option value="Courier">Courier</option>
                            <option value="Hotel">Hotel</option>
                            <option value="Others">Others</option>
                            </select>
                        </div>
                    </div>
                    <div id="individualFields" class="row g-3 mb-3" style="display:none;">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Max Monthly Travel K.M.</label>
                        <select name="max_monthly_travel" id="max_monthly_travel" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">KM</label>
                        <input type="number" name="km" id="km" class="form-control" placeholder="Enter KM">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-bold">Approved Bills in DA</label>
                        <select name="approved_bills_in_da[]" id="approvedBills" class="form-select" multiple>
                        <option value="Petrol">Petrol</option>
                        <option value="Food">Food</option>
                        <option value="Accommodation">Accommodation</option>
                        <option value="Travel">Travel</option>
                        <option value="Courier">Courier</option>
                        <option value="Hotel">Hotel</option>
                        <option value="Others">Others</option>
                        </select>
                    </div>
                    </div>

                    <hr>
                    <div class="d-flex align-items-center mb-2">
                        <h6 class="mb-0 me-3">Travel Mode Allowance (Per KM)</h6>
                        <h6 class="mb-0 me-3">TA & DA minimum Travel KM</h6>
                        <div class="form-check me-3 mb-0">
                            <input class="form-check-input" type="checkbox" id="travelModeCheckbox" name="travel_mode_enabled" value="1">
                        </div>
                        <input type="number" step="0.01" min="0" onkeydown="return event.keyCode !== 38 && event.keyCode !== 40" class="form-control form-control-sm w-auto d-none" id="travelModeInput" name="travel_mode_limit" placeholder="KM" disabled>
                    </div>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Travel Mode</th>
                                <th>Allowance</th>
                            </tr>
                        </thead>
                        <tbody id="vehicleSlabBody"></tbody>
                    </table>

                    <hr>
                    <div class="d-flex align-items-center mb-2">
                        <h6 class="mb-0 me-3">Tour Type Allowance</h6>
                        
                        <div class="form-check me-3 mb-0">
                            <input class="form-check-input" type="checkbox" id="tourTypeCheckbox" style="display: none;" name="tour_type_enabled" value="1">
                        </div>
                        <input type="number" step="0.01" min="0" onkeydown="return event.keyCode !== 38 && event.keyCode !== 40" class="form-control form-control-sm w-auto d-none" id="tourTypeInput" name="tour_type_limit" placeholder="KM" disabled>
                    </div>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Tour Type</th>
                                <th>DA Amount</th>
                            </tr>
                        </thead>
                        <tbody id="tourSlabBody"></tbody>
                    </table>
                </div>

                <div class="modal-footer">
                    <button type="submit" id="saveSlabBtn" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>


</main>
@endsection
@push('scripts')
<script>
// function openSlabModal(userId) {
//     $('#user_id').val(userId);
//     $('#slabModal').modal('show');
// }
function openSlabModal(userId) {
    $('#user_id').val(userId);
    $('#slabModal').modal('show');
    $('#designation_id_modal').val(null).trigger('change.select2');

    // 🔹 Get user’s current slab (Individual / Slab Wise)
    $.ajax({
        url: "{{ route('admin.get-user-slab') }}",
        method: "GET",
        data: { user_id: userId },
        success: function (res) {
            // --- User slab select ---
            if (res.user_slab) {
                $('#slabSelect').val(res.user_slab).change();
            } else {
                
                $('#slabSelect').val('Slab Wise').change();
                if (res.ta_da_slab.approved_bills_in_da_slab_wise) {
                    let billsNew = Array.isArray(res.ta_da_slab.approved_bills_in_da_slab_wise)
                        ? res.ta_da_slab.approved_bills_in_da_slab_wise
                        : JSON.parse(res.ta_da_slab.approved_bills_in_da_slab_wise);
                    $('#approvedSlabBills').val(billsNew).trigger('change');
                    if(res.slab_designation_id){
                    $('#designation_id_modal').val(res.slab_designation_id).trigger('change.select2');
                }
                }
                    

            }
        }
    });
}
$('#slabModal').on('shown.bs.modal', function () {
    $('#approvedBills,#approvedSlabBills').select2({
        placeholder: "Select Approved Bills",
        width: '100%',
        dropdownParent: $('#slabModal') // dropdown modal ni andar show thaye
    });
});
$(document).ready(function() {
    // Globally disable ArrowUp and ArrowDown keys for all number inputs
    $(document).on('keydown', 'input[type="number"]', function(e) {
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown' || e.keyCode === 38 || e.keyCode === 40) {
            e.preventDefault();
        }
    });

    $('#slabSelect, #designation_id_modal').on('change', function () {
        loadSlabData();
    });

    function loadSlabData() {
        let userId = $('#user_id').val();
        let slab = $('#slabSelect').val();
        let designationId = $('#designation_id_modal').val();

        $.ajax({
            url: "{{ route('admin.get-user-slab') }}",
            method: "GET",
            data: { user_id: userId, slab: slab, designation_id: designationId },
            success: function (res) {
                console.log(res);
                // 🔹 Reset Designations
                $('#designation_id_modal').empty();
                $.each(res.designations, function (i, d) {
                    let selected = (parseInt(d.id) === parseInt(designationId)) ? 'selected' : '';
                    $('#designation_id_modal').append(`<option value="${d.id}" ${selected}>${d.name}</option>`);
                });
                $('#designation_id_modal').select2({
                    dropdownParent: $('#slabModal'),
                    width: '100%'
                });
                if(!designationId && res.slab_designation_id){
                    $('#designation_id_modal').val(res.slab_designation_id).trigger('change.select2');
                }
                


                // 🔹 Slab Type logic
                let isSlabWise = (slab === "Slab Wise");
                let readOnlyAttr = isSlabWise ? "readonly" : "";

                // 🔹 Vehicle slabs
                $('#vehicleSlabBody').html('');
                $.each(res.travel_modes, function (i, vt) {
                    let slabData = res.vehicle_slabs.find(s => s.travel_mode_id === vt.id);
                    let amount = slabData ? slabData.travelling_allow_per_km : '';
                    $('#vehicleSlabBody').append(`
                        <tr>
                            <td>${vt.name}</td>
                            <td>
                                <input type="hidden" name="travel_mode_id[]" value="${vt.id}">
                                <input step="0.01" type="number" class="form-control text-center vehicle-amount" name="travelling_allow_per_km[]" value="${amount}" ${readOnlyAttr}>
                            </td>
                        </tr>
                    `);
                });

                // 🔹 Tour slabs
                $('#tourSlabBody').html('');
                $.each(res.tour_types, function (i, tt) {
                    let slabData = res.tour_slabs.find(s => s.tour_type_id === tt.id);
                    let da = slabData ? slabData.da_amount : '';
                    $('#tourSlabBody').append(`
                        <tr>
                            <td>${tt.name}</td>
                            <td>
                                <input type="hidden" name="tour_type_id[]" value="${tt.id}">
                                <input type="number" step="0.01" class="form-control text-center tour-amount" name="da_amount[]" value="${da}" ${readOnlyAttr}>
                            </td>
                        </tr>
                    `);
                });

                // 🔹 Slab type toggle
                if (isSlabWise) {
                    $('#travelModeCheckbox').closest('.form-check').hide();
                    $('#tourTypeCheckbox').closest('.form-check').hide();
                    $('#individualFields').hide();
                    $('#approved_bills_in_da_slab_wise').show();
                    // $('#saveSlabBtn').hide();
                    $('#designation_id_modal').prop('disabled', false);
                    $('.vehicle-amount, .tour-amount').prop('readonly', true);
                    if (res.ta_da_slab.approved_bills_in_da_slab_wise) {
                    let billsNew = Array.isArray(res.ta_da_slab.approved_bills_in_da_slab_wise)
                        ? res.ta_da_slab.approved_bills_in_da_slab_wise
                        : JSON.parse(res.ta_da_slab.approved_bills_in_da_slab_wise);
                    $('#approvedSlabBills').val(billsNew).trigger('change');
                    $('#approvedSlabBills').prop('disabled', true);
                }

                } else {
                    $('#travelModeCheckbox').closest('.form-check').show();
                    $('#tourTypeCheckbox').closest('.form-check').show();
                    $('#individualFields').show();
                    $('#saveSlabBtn').show();
                    $('#approved_bills_in_da_slab_wise').hide();
                    $('#designation_id_modal').prop('disabled', true);
                    $('.vehicle-amount, .tour-amount').prop('readonly', false);

                    if (res.ta_da_slab) {
                        $('#max_monthly_travel').val(res.ta_da_slab.max_monthly_travel ?? '');
                        $('#km').val(res.ta_da_slab.km ?? '');

                        // ✅ Individual approved bills
                        if (res.ta_da_slab.approved_bills_in_da) {
                            let bills = Array.isArray(res.ta_da_slab.approved_bills_in_da)
                                ? res.ta_da_slab.approved_bills_in_da
                                : JSON.parse(res.ta_da_slab.approved_bills_in_da);
                            $('#approvedBills').val(bills).trigger('change');
                        }

                        // ✅ Pre-fill checkboxes and limits
                        if(res.ta_da_slab.travel_mode_enabled == 1) {
                            $('#travelModeCheckbox').prop('checked', true);
                            $('#travelModeInput').removeClass('d-none').prop('disabled', false).val(res.ta_da_slab.travel_mode_limit);
                        } else {
                            $('#travelModeCheckbox').prop('checked', false);
                            $('#travelModeInput').addClass('d-none').prop('disabled', true).val('');
                        }

                        if(res.ta_da_slab.tour_type_enabled == 1) {
                            $('#tourTypeCheckbox').prop('checked', true);
                            $('#tourTypeInput').removeClass('d-none').prop('disabled', false).val(res.ta_da_slab.tour_type_limit);
                        } else {
                            $('#tourTypeCheckbox').prop('checked', false);
                            $('#tourTypeInput').addClass('d-none').prop('disabled', true).val('');
                        }
                    } else {
                        // Reset if no record
                        $('#travelModeCheckbox').prop('checked', false);
                        $('#travelModeInput').addClass('d-none').prop('disabled', true).val('');
                        $('#tourTypeCheckbox').prop('checked', false);
                        $('#tourTypeInput').addClass('d-none').prop('disabled', true).val('');
                    }
                }
            }
        });
    }


   // Save Form
    $('#slabForm').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: "{{ route('admin.save.user.slab') }}",
            method: "POST",
            data: $(this).serialize(),
            success: function (res) {
                alert(res.message);
                $('#slabModal').modal('hide');
            }
        });
    });


    $('#depoAccessModal').on('shown.bs.modal', function () {
        $('#depoId').select2({
            placeholder: "Select Depos",
            width: '100%',
            dropdownParent: $('#depoAccessModal') // VERY IMPORTANT for Bootstrap modal
        });
    });
    $('#stateAccessModal').on('shown.bs.modal', function () {
        $('#stateIds').select2({
            placeholder: "Select State",
            width: '100%',
            dropdownParent: $('#stateAccessModal') 
        });
    });
    
    // Open modal and set user id
    $(document).on('click', '.reset-password', function() {
        let userId = $(this).data('user-id');
        $('#modalUserId').val(userId);
        $('#newPassword').val('');
        $('#resetPasswordMessage').html('');
        $('#resetPasswordModal').modal('show');
    });

    $(document).on('click', '.state_access', function() {
        let userId = $(this).data('user-id');
        $('#stateModalUserId').val(userId);

        // reset select
        $('#stateIds').val(null).trigger('change');
        $('#stateAccessMessage').html('');

        $('#stateAccessModal').modal('show');

        // load existing user state access
        $.ajax({
            url: '/admin/get-user-state-access',
            type: 'GET',
            data: { user_id: userId },
            success: function(res){
                if(res.state_ids){
                    $('#stateIds').val(res.state_ids).trigger('change');
                }
            }
        });
    });

    $(document).on('click', '#saveStateBtn', function() {
        let formData = $('#stateAccessForm').serialize();
        $.ajax({
            url: '/admin/save-user-state-access',
            type: 'POST',
            data: formData,
            success: function(res){
                $('#stateAccessMessage').html('<div class="alert alert-success">States saved successfully!</div>');
                setTimeout(() => { $('#stateAccessModal').modal('hide'); }, 1500);
            },
            error: function(){
                $('#stateAccessMessage').html('<div class="alert alert-danger">Error saving states.</div>');
            }
        });
    });

    $(document).on('click', '.depo_access', function() {
        $('#depoAccessMessage').html('');
        let userId = $(this).data('user-id');
        $('#depoModalUserId').val(userId);

        // Reset form & depo dropdown
        $('#depoAccessForm')[0].reset();
        $('#depoId').empty().trigger('change');

        // Show modal
        $('#depoAccessModal').modal('show');

        // 🔹 STEP 1: Load all depos first
        $.ajax({
            url: 'get-depos',
            type: 'GET',
            success: function (depos) {
                $('#depoId').empty();

                $.each(depos, function (i, depo) {
                    $('#depoId').append(
                        $('<option>', { value: depo.id, text: depo.depo_name })
                    );
                });

                // 🔹 STEP 2: Now fetch user depo access and preselect
                $.ajax({
                    url: 'get-user-depo-access',
                    type: 'GET',
                    data: { user_id: userId },
                    success: function (res) {
                        if (res.userAccess && res.userAccess.depo_ids) {
                            let selectedDepos = res.userAccess.depo_ids;
                            $('#depoId').val(selectedDepos).trigger('change');
                        }
                    }
                });
            }
        });
    });

    $(document).on('click', '#saveDepoAccessBtn', function() {
        let formData = $('#depoAccessForm').serialize();
        $.ajax({
            url: '{{ route("admin.save.depo.access") }}',
            type: 'POST',
            data: formData,
            success: function(res){
                $('#depoAccessMessage').html('<div class="alert alert-success">'+res.message+'</div>');
                setTimeout(function(){
                    $('#depoAccessModal').modal('hide');
                    $('#depoAccessMessage').html('');
                }, 1500);
            },
            error: function(xhr){
                let errors = xhr.responseJSON.errors;
                let msg = '';
                $.each(errors, function(k,v){ msg += v[0]+'<br>'; });
                $('#depoAccessMessage').html('<div class="alert alert-danger">'+msg+'</div>');
            }
        });
    });

    // Handle reset password AJAX
    $(document).on('click', '#resetPasswordBtn', function() {
        let formData = $('#resetPasswordForm').serialize();

        $.ajax({
            url: "{{ route('users.reset-password') }}",
            method: "POST",
            data: formData,
            success: function(res) {
                $('#resetPasswordMessage').html('<div class="alert alert-success">Password reset successfully!</div>');
                setTimeout(function() {
                    $('#resetPasswordModal').modal('hide');
                }, 1500);
            },
            error: function(xhr) {
                let errors = xhr.responseJSON.errors;
                let errorHtml = '<div class="alert alert-danger">';
                $.each(errors, function(key, value) {
                    errorHtml += value + '<br>';
                });
                errorHtml += '</div>';
                $('#resetPasswordMessage').html(errorHtml);
            }
        });
    });

    // Toggle Travel Mode Input
    $('#travelModeCheckbox').on('change', function() {
        if ($(this).is(':checked')) {
            $('#travelModeInput').removeClass('d-none').prop('disabled', false);
        } else {
            $('#travelModeInput').addClass('d-none').prop('disabled', true);
            $('#travelModeInput').val(''); // Clear value when hidden
        }
    });

    // Toggle Tour Type Input
    $('#tourTypeCheckbox').on('change', function() {
        if ($(this).is(':checked')) {
            $('#tourTypeInput').removeClass('d-none').prop('disabled', false);
        } else {
            $('#tourTypeInput').addClass('d-none').prop('disabled', true);
            $('#tourTypeInput').val(''); // Clear value when hidden
        }
    });

    $('#stateAccessModal').on('shown.bs.modal', function () {
        $('#stateIds').select2({
            dropdownParent: $('#stateAccessModal'),
            width: '100%'
        });
    });

    $('#depoAccessModal').on('shown.bs.modal', function () {
        $('#depoId').select2({
            dropdownParent: $('#depoAccessModal'),
            width: '100%'
        });
    });

    // =========================================================
    // ✅ 🔥 ARIA-HIDDEN ERROR FIX (MOST IMPORTANT)
    // =========================================================
    $('.modal').on('hidden.bs.modal', function () {
        document.activeElement.blur(); // remove focus
    });
});

</script>
@endpush
