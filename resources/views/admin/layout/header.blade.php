<nav class="app-header navbar navbar-expand bg-body shadow-sm">
    <div class="container-fluid">
        <!-- Start Navbar -->
        <ul class="navbar-nav">
            <!-- Sidebar Toggle -->
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="bi bi-list"></i>
                </a>
            </li>

            <!-- Main Links -->
            <li class="nav-item d-none d-md-block">
                <a href="{{ url('admin/dashboard') }}" class="nav-link">Dashboard</a>
            </li>
            <li class="nav-item d-none d-md-block">
                <a href="#" class="nav-link">Contact</a>
            </li>
        </ul>

        <!-- End Navbar -->
        <ul class="navbar-nav ms-auto align-items-center">
            <!-- Fullscreen Toggle -->
            <li class="nav-item">
                <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                    <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit d-none"></i>
                </a>
            </li>

            <!-- User Dropdown -->
            @php
                $authUser = Auth::user();
                $defaultImage = asset(
                    $authUser->gender === 'Female'
                        ? 'admin/images/avatar-female.png'
                        : 'admin/images/avatar-male.png'
                );
                $userImage = $authUser->image ? asset('storage/' . $authUser->image) : $defaultImage;
            @endphp

            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="{{ $userImage }}"
                         class="user-image rounded-circle shadow" alt="User Image" width="32" height="32" />
                    <span class="d-none d-md-inline">{{ $authUser->name }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <!-- User Info -->
                    <li class="user-header text-bg-primary text-center">
                        <img src="{{ $userImage }}"
                             class="rounded-circle shadow mb-2" alt="User Image" width="80" height="80" />
                        <p class="mb-0">{{ $authUser->name }}</p>
                        <small>Member since {{ $authUser->created_at->format('M Y') }}</small>
                    </li>

                    <!-- User Body -->
                    <li class="user-body px-3 py-2">
                        <div class="row text-center">
                            <div class="col-12">
                                <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                Change Password
                                </a>
                            </div>
                            {{-- <div class="col-4"><a href="#">Sales</a></div>
                            <div class="col-4"><a href="#">Friends</a></div> --}}
                        </div>
                    </li>

                    <!-- Footer -->
                    <li class="user-footer d-flex justify-content-between px-3 py-2">
                        @if($authUser && $authUser->hasRole('master_admin'))
                        <a href="{{ route('apk.create') }}" target="_blank" class="btn btn-outline-primary btn-sm">APK upload</a>
                        @endif
                        <a href="#" onclick="event.preventDefault(); event.stopPropagation(); new bootstrap.Modal(document.getElementById('profileDetailsModal')).show();" class="btn btn-outline-primary btn-sm">Profile</a>
                        <a href="{{ url('admin/logout') }}" class="btn btn-outline-danger btn-sm">Sign out</a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
    
</nav>
@include('admin.users.change-password-modal');

{{-- Modal --}}
<div class="modal fade" id="profileDetailsModal" tabindex="-1" aria-labelledby="profileDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileDetailsModalLabel">Admin Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th>ID</th>
                            <td>{{ $authUser->id }}</td>
                        </tr>
                        <tr>
                            <th>Name</th>
                            <td>{{ $authUser->name }}</td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>{{ $authUser->email }}</td>
                        </tr>
                        <tr>
                            <th>Mobile</th>
                            <td>{{ $authUser->mobile ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>User Type</th>
                            <td>{{ $authUser->user_type ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Designation</th>
                            <td>{{ $authUser->designation?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Company</th>
                            <td>{{ $authUser->company?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Reporting Manager</th>
                            <td>{{ $authUser->reportingManager?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Headquarter</th>
                            <td>{{ $authUser->headquarter ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Date of Birth</th>
                            <td>{{ $authUser->date_of_birth ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Gender</th>
                            <td>{{ $authUser->gender ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Marital Status</th>
                            <td>{{ $authUser->marital_status ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Joining Date</th>
                            <td>{{ $authUser->joining_date ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Emergency Contact No</th>
                            <td>{{ $authUser->emergency_contact_no ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                @if($authUser->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Is Self Sale</th>
                            <td>{{ $authUser->is_self_sale ? 'Yes' : 'No' }}</td>
                        </tr>
                        <tr>
                            <th>Multi-Day Start/End Allowed</th>
                            <td>{{ $authUser->is_multi_day_start_end_allowed ? 'Yes' : 'No' }}</td>
                        </tr>
                        <tr>
                            <th>Allow Tracking</th>
                            <td>{{ $authUser->is_allow_tracking ? 'Yes' : 'No' }}</td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td>{{ $authUser->address ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>State</th>
                            <td>{{ $authUser->state?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>District</th>
                            <td>{{ $authUser->district?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Tehsil</th>
                            <td>{{ $authUser->tehsil?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>City</th>
                            <td>{{ $authUser->city?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Pincode</th>
                            <td>{{ $authUser->pincode?->Pincode ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Latitude</th>
                            <td>{{ $authUser->latitude ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Longitude</th>
                            <td>{{ $authUser->longitude ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Image</th>
                            <td>
                                @if($authUser->image)
                                    <img src="{{ asset('storage/'.$authUser->image) }}" alt="Admin Image" class="img-thumbnail" style="width: 100px; height: auto;">
                                @else
                                    <span class="text-muted">No image uploaded</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td>{{ $authUser->created_at }}</td>
                        </tr>
                        <tr>
                            <th>Updated At</th>
                            <td>{{ $authUser->updated_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                @can('edit_users')
                <a href="{{ route('users.edit', $authUser) }}" class="btn btn-warning">
                    <i class="fas fa-edit me-1"></i> Edit Admin
                </a>
                @endcan
            </div>
        </div>
    </div>
</div>




