@extends('admin.layout.layout')

@push('styles')
    <style>
        .dashboard-page {
            background:
                linear-gradient(180deg, #eef3f8 0, #f7f9fc 260px, #f7f9fc 100%);
            min-height: calc(100vh - 57px);
        }

        .dashboard-header {
            padding: 1.5rem 0 0;
        }

        .dashboard-hero {
            align-items: center;
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: .5rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .14);
            display: flex;
            justify-content: space-between;
            min-height: 128px;
            overflow: hidden;
            padding: 1.35rem 1.5rem;
            position: relative;
        }

        .dashboard-hero::after {
            background:
                radial-gradient(circle at 20% 20%, rgba(59, 130, 246, .25), transparent 30%),
                radial-gradient(circle at 75% 40%, rgba(20, 184, 166, .2), transparent 28%);
            content: "";
            inset: 0;
            opacity: .9;
            position: absolute;
        }

        .dashboard-hero > * {
            position: relative;
            z-index: 1;
        }

        .dashboard-title {
            color: #fff;
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: 0;
        }

        .dashboard-subtitle {
            color: #cbd5e1;
            font-size: .95rem;
            margin-bottom: 0;
        }

        .dashboard-breadcrumb {
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: .5rem;
            padding: .55rem .85rem;
        }

        .dashboard-breadcrumb,
        .dashboard-breadcrumb .breadcrumb-item,
        .dashboard-breadcrumb .breadcrumb-item.active {
            color: #dbeafe;
        }

        .dashboard-breadcrumb a {
            color: #fff;
            text-decoration: none;
        }

        .dashboard-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
            color: #93c5fd;
        }

        .stats-row {
            margin-top: -1.15rem;
            position: relative;
            z-index: 2;
        }

        .metric-card {
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: .5rem;
            box-shadow: 0 14px 30px rgba(15, 23, 42, .08);
            display: flex;
            flex-direction: column;
            min-height: 156px;
            overflow: hidden;
            position: relative;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .metric-card:hover {
            border-color: rgba(59, 130, 246, .28);
            box-shadow: 0 18px 38px rgba(15, 23, 42, .12);
            transform: translateY(-2px);
        }

        .metric-card::before {
            content: "";
            bottom: 0;
            height: auto;
            left: 0;
            position: absolute;
            top: 0;
            width: 4px;
        }

        .metric-card.primary::before {
            background: #2563eb;
        }

        .metric-card.success::before {
            background: #16a34a;
        }

        .metric-card.warning::before {
            background: #f59e0b;
        }

        .metric-card.danger::before {
            background: #dc2626;
        }

        .metric-card-body {
            align-items: flex-start;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            padding: 1.35rem 1.35rem .9rem;
        }

        .metric-label {
            color: #6b7280;
            font-size: .76rem;
            font-weight: 700;
            margin-bottom: .35rem;
            text-transform: uppercase;
        }

        .metric-value {
            color: #111827;
            font-size: 2.15rem;
            font-weight: 800;
            line-height: 1;
            margin: 0;
        }

        .metric-note {
            color: #94a3b8;
            font-size: .82rem;
            font-weight: 600;
            margin-top: .4rem;
        }

        .metric-icon {
            align-items: center;
            border-radius: .5rem;
            display: inline-flex;
            flex: 0 0 48px;
            font-size: 1.35rem;
            height: 48px;
            justify-content: center;
            width: 48px;
        }

        .metric-icon.primary {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .metric-icon.success {
            background: #dcfce7;
            color: #15803d;
        }

        .metric-icon.warning {
            background: #fef3c7;
            color: #b45309;
        }

        .metric-icon.danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .metric-link {
            align-items: center;
            color: #475569;
            display: flex;
            font-size: .9rem;
            font-weight: 700;
            gap: .45rem;
            justify-content: space-between;
            margin-top: auto;
            padding: .9rem 1.35rem 1.1rem;
            text-decoration: none;
        }

        .metric-link:hover {
            color: #0f172a;
        }

        .database-pill {
            align-items: center;
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: .5rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
            color: #334155;
            display: inline-flex;
            font-size: .85rem;
            font-weight: 700;
            gap: .5rem;
            padding: .75rem .95rem;
        }

        .database-pill i {
            color: #4f46e5;
        }

        .dashboard-card {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: .5rem;
            box-shadow: 0 14px 30px rgba(15, 23, 42, .08);
            overflow: hidden;
        }

        .dashboard-card .card-header {
            align-items: center;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            padding: 1.1rem 1.35rem;
        }

        .dashboard-card .card-title {
            color: #111827;
            font-size: 1.05rem;
            font-weight: 800;
            margin: 0;
        }

        .dashboard-card-subtitle {
            color: #64748b;
            font-size: .86rem;
            margin: .2rem 0 0;
        }

        .online-count {
            align-items: center;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 999px;
            color: #166534;
            display: inline-flex;
            font-size: .8rem;
            font-weight: 700;
            gap: .4rem;
            padding: .3rem .65rem;
        }

        .online-count::before {
            background: #22c55e;
            border-radius: 50%;
            content: "";
            height: 8px;
            width: 8px;
        }

        .dashboard-table {
            margin-bottom: 0;
        }

        .dashboard-table thead th {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            color: #475569;
            font-size: .78rem;
            letter-spacing: .02em;
            padding: .95rem 1.25rem;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .dashboard-table tbody td {
            border-color: #eef2f7;
            color: #334155;
            padding: 1rem 1.25rem;
            vertical-align: middle;
        }

        .dashboard-table tbody tr {
            transition: background-color .16s ease;
        }

        .dashboard-table tbody tr:hover {
            background: #f8fafc;
        }

        .user-cell {
            align-items: center;
            display: flex;
            gap: .75rem;
            min-width: 180px;
        }

        .user-avatar {
            align-items: center;
            background: linear-gradient(135deg, #dbeafe, #ccfbf1);
            border-radius: 50%;
            color: #0f172a;
            display: inline-flex;
            flex: 0 0 40px;
            font-weight: 800;
            height: 40px;
            justify-content: center;
            text-transform: uppercase;
            width: 40px;
        }

        .user-name {
            color: #111827;
            font-weight: 800;
            line-height: 1.2;
        }

        .user-email {
            color: #64748b;
            font-size: .85rem;
        }

        .mobile-value,
        .last-seen-value {
            color: #475569;
            font-weight: 650;
        }

        .badge-soft {
            border-radius: 999px;
            display: inline-flex;
            font-size: .78rem;
            font-weight: 700;
            margin: .1rem;
            padding: .4rem .6rem;
        }

        .badge-soft-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-soft-info {
            background: #e0f2fe;
            color: #075985;
        }

        .empty-state {
            color: #64748b;
            padding: 2.4rem 1rem;
            text-align: center;
        }

        .permission-btn {
            align-items: center;
            border-color: #bae6fd;
            color: #0369a1;
            display: inline-flex;
            font-weight: 700;
            gap: .35rem;
        }

        .permission-btn:hover {
            background: #e0f2fe;
            border-color: #7dd3fc;
            color: #075985;
        }

        @media (max-width: 575.98px) {
            .dashboard-hero {
                align-items: flex-start;
                flex-direction: column;
                gap: 1rem;
                padding: 1.2rem;
            }

            .dashboard-breadcrumb {
                width: 100%;
            }

            .stats-row {
                margin-top: .75rem;
            }

            .metric-value {
                font-size: 1.65rem;
            }

            .dashboard-card .card-header {
                align-items: flex-start;
                flex-direction: column;
                gap: .65rem;
            }
        }
    </style>
@endpush

@section('content')
    <main class="app-main dashboard-page">
        <div class="content-header dashboard-header">
            <div class="container-fluid">
                <div class="dashboard-hero mb-4">
                    <div>
                        <h3 class="dashboard-title mb-1">Dashboard</h3>
                        <p class="dashboard-subtitle">Overview of users, access control and active sessions.</p>
                    </div>
                    <ol class="breadcrumb dashboard-breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="content pb-4">
            <div class="container-fluid">

                {{-- Stats Summary --}}
                <div class="row g-3 stats-row">
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card primary">
                            <div class="metric-card-body">
                                <div>
                                    <div class="metric-label">Total Users</div>
                                    <h3 class="metric-value">{{ $totalUsers }}</h3>
                                    <div class="metric-note">Registered accounts</div>
                                </div>
                                <span class="metric-icon primary">
                                    <i class="fas fa-users"></i>
                                </span>
                            </div>
                            <a href="{{ url('admin/users') }}" class="metric-link">
                                <span>View users</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    @if($isMasterAdmin == true)
                        <div class="col-xl-3 col-md-6">
                            <div class="metric-card success">
                                <div class="metric-card-body">
                                    <div>
                                        <div class="metric-label">Total Roles</div>
                                        <h3 class="metric-value">{{ $totalRoles }}</h3>
                                        <div class="metric-note">Access groups</div>
                                    </div>
                                    <span class="metric-icon success">
                                        <i class="fas fa-user-tag"></i>
                                    </span>
                                </div>
                                <a href="{{ url('admin/roles') }}" class="metric-link">
                                    <span>Manage roles</span>
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="metric-card warning">
                                <div class="metric-card-body">
                                    <div>
                                        <div class="metric-label">Total Permissions</div>
                                        <h3 class="metric-value">{{ $totalPermissions }}</h3>
                                        <div class="metric-note">Assigned capabilities</div>
                                    </div>
                                    <span class="metric-icon warning">
                                        <i class="fas fa-key"></i>
                                    </span>
                                </div>
                                <a href="{{ url('admin/permissions') }}" class="metric-link">
                                    <span>Manage permissions</span>
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>

                        @if (!is_null($totalCustomers))
                            <div class="col-xl-3 col-md-6">
                                <div class="metric-card danger">
                                    <div class="metric-card-body">
                                        <div>
                                            <div class="metric-label">Total Customers</div>
                                            <h3 class="metric-value">{{ $totalCustomers }}</h3>
                                            <div class="metric-note">Customer records</div>
                                        </div>
                                        <span class="metric-icon danger">
                                            <i class="fas fa-user-friends"></i>
                                        </span>
                                    </div>
                                    <a href="{{ url('admin/customers') }}" class="metric-link">
                                        <span>View customers</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        @endif

                        @if (!empty($databaseName))
                            <div class="col-12">
                                <span class="database-pill">
                                    <i class="fas fa-database"></i>
                                    Database: {{ $databaseName }}
                                </span>
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Sales & Catalog --}}
                <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                    <h4 class="m-0 text-dark" style="font-weight: 700; font-size: 1.15rem;">Sales & Catalog</h4>
                </div>
                <div class="row g-3 stats-row" style="margin-top: 0;">
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card primary">
                            <div class="metric-card-body">
                                <div>
                                    <div class="metric-label">Total Orders</div>
                                    <h3 class="metric-value">{{ $totalOrders }}</h3>
                                    <div class="metric-note">System orders</div>
                                </div>
                                <span class="metric-icon primary"><i class="fas fa-shopping-cart"></i></span>
                            </div>
                            <a href="{{ url('admin/order') }}" class="metric-link">
                                <span>View orders</span><i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card success">
                            <div class="metric-card-body">
                                <div>
                                    <div class="metric-label">Products</div>
                                    <h3 class="metric-value">{{ $totalProducts }}</h3>
                                    <div class="metric-note">Catalog items</div>
                                </div>
                                <span class="metric-icon success"><i class="fas fa-box"></i></span>
                            </div>
                            <a href="{{ url('admin/products') }}" class="metric-link">
                                <span>View products</span><i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card warning">
                            <div class="metric-card-body">
                                <div>
                                    <div class="metric-label">Categories</div>
                                    <h3 class="metric-value">{{ $totalProductCategories }}</h3>
                                    <div class="metric-note">Product groups</div>
                                </div>
                                <span class="metric-icon warning"><i class="fas fa-tags"></i></span>
                            </div>
                            <a href="{{ url('admin/product-categories') }}" class="metric-link">
                                <span>View categories</span><i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Field Operations --}}
                <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                    <h4 class="m-0 text-dark" style="font-weight: 700; font-size: 1.15rem;">Field Operations</h4>
                </div>
                <div class="row g-3 stats-row" style="margin-top: 0;">
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card primary">
                            <div class="metric-card-body">
                                <div>
                                    <div class="metric-label">Trips</div>
                                    <h3 class="metric-value">{{ $totalTrips }}</h3>
                                    <div class="metric-note">Employee trips</div>
                                </div>
                                <span class="metric-icon primary"><i class="fas fa-route"></i></span>
                            </div>
                            <a href="{{ url('admin/trips') }}" class="metric-link">
                                <span>View trips</span><i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card success">
                            <div class="metric-card-body">
                                <div>
                                    <div class="metric-label">Expenses</div>
                                    <h3 class="metric-value">{{ $totalExpenses }}</h3>
                                    <div class="metric-note">Logged expenses</div>
                                </div>
                                <span class="metric-icon success"><i class="fas fa-wallet"></i></span>
                            </div>
                            <a href="{{ url('admin/expense') }}" class="metric-link">
                                <span>View expenses</span><i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card warning">
                            <div class="metric-card-body">
                                <div>
                                    <div class="metric-label">Party Visits</div>
                                    <h3 class="metric-value">{{ $totalPartyVisits }}</h3>
                                    <div class="metric-note">Customer meetings</div>
                                </div>
                                <span class="metric-icon warning"><i class="fas fa-handshake"></i></span>
                            </div>
                            <a href="{{ route('party-visit-report') }}" class="metric-link">
                                <span>View visits</span><i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card danger">
                            <div class="metric-card-body">
                                <div>
                                    <div class="metric-label">Attendances</div>
                                    <h3 class="metric-value">{{ $totalAttendances }}</h3>
                                    <div class="metric-note">Daily logs</div>
                                </div>
                                <span class="metric-icon danger"><i class="fas fa-clock"></i></span>
                            </div>
                            <a href="{{ route('attendance.index') }}" class="metric-link">
                                <span>View attendance</span><i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Geography & Setup --}}
                <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                    <h4 class="m-0 text-dark" style="font-weight: 700; font-size: 1.15rem;">Geography & Setup</h4>
                </div>
                <div class="row g-3 stats-row" style="margin-top: 0;">
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card primary">
                            <div class="metric-card-body">
                                <div>
                                    <div class="metric-label">Companies</div>
                                    <h3 class="metric-value">{{ $totalCompanies }}</h3>
                                    <div class="metric-note">Registered branches</div>
                                </div>
                                <span class="metric-icon primary"><i class="fas fa-building"></i></span>
                            </div>
                            <a href="{{ url('admin/companies') }}" class="metric-link">
                                <span>View companies</span><i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card success">
                            <div class="metric-card-body">
                                <div>
                                    <div class="metric-label">Depos</div>
                                    <h3 class="metric-value">{{ $totalDepos }}</h3>
                                    <div class="metric-note">Warehouses</div>
                                </div>
                                <span class="metric-icon success"><i class="fas fa-warehouse"></i></span>
                            </div>
                            <a href="{{ url('admin/depos') }}" class="metric-link">
                                <span>View depos</span><i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card warning">
                            <div class="metric-card-body">
                                <div>
                                    <div class="metric-label">States</div>
                                    <h3 class="metric-value">{{ $totalStates }}</h3>
                                    <div class="metric-note">Configured regions</div>
                                </div>
                                <span class="metric-icon warning"><i class="fas fa-map-marked-alt"></i></span>
                            </div>
                            <a href="{{ url('admin/states') }}" class="metric-link">
                                <span>View states</span><i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Online Users --}}
                <div class="card dashboard-card mt-4">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Online Users Details</h3>
                            <p class="dashboard-card-subtitle">Currently active users with roles, permissions and last activity.</p>
                        </div>
                        <span class="online-count">{{ $onlineUsers->count() }} Online</span>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover text-nowrap dashboard-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                    <th>Roles</th>
                                    <th>Permissions</th>
                                    <th>Last Seen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($onlineUsers as $user)
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <span class="user-avatar">{{ substr($user->name, 0, 1) }}</span>
                                                <div>
                                                    <div class="user-name">{{ $user->name }}</div>
                                                    <div class="user-email">{{ $user->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="mobile-value">{{ $user->mobile ?? 'N/A' }}</span></td>
                                        <td>
                                            @forelse ($user->getRoleNames() as $role)
                                                <span class="badge-soft badge-soft-success">{{ $role }}</span>
                                            @empty
                                                <span class="text-muted">N/A</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info permission-btn" data-bs-toggle="modal" data-bs-target="#permissionsModal-{{ $user->id }}">
                                                <i class="fas fa-eye"></i> View
                                            </button>

                                            {{-- Permissions Modal --}}
                                            <div class="modal fade" id="permissionsModal-{{ $user->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-scrollable">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title">Permissions for {{ $user->name }}</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            @if (count($user->getAllPermissionsList()))
                                                                @foreach ($user->getAllPermissionsList() as $permission)
                                                                    <span class="badge-soft badge-soft-info">{{ $permission }}</span>
                                                                @endforeach
                                                            @else
                                                                <p class="text-muted mb-0">No permissions assigned.</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="last-seen-value">{{ $user->last_seen ? $user->last_seen->diffForHumans() : 'N/A' }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="empty-state">No users online currently.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Session History Modal --}}
                <div class="modal fade" id="sessionHistoryModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="sessionHistoryModalLabel">Session History</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="sessionHistoryContent">Loading...</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
@endsection
