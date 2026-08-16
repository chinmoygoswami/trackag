<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    @php
        $company = \App\Models\Company::first();
        $user = auth()->user();
    @endphp
    <div class="sidebar-brand px-3 py-4 d-flex align-items-center">
        <a href="{{ url('admin/dashboard') }}" class="brand-link d-flex align-items-center gap-2">
            @if($user && $user->hasRole('master_admin'))
                <img src="{{ asset('admin/images/AdminLTELogo.png') }}" alt="AdminLTE Logo" class="brand-image opacity-75 shadow" style="width: 32px; height: 32px;" />
                <span class="brand-text fw-light fs-5">Trackag</span>
            @else
                @if(!empty($company->logo))
                    <img src="{{ asset('storage/' . $company->logo) }}" alt="{{ $company->name }}" class="brand-image opacity-75 shadow rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" />
                @else
                    <img src="{{ asset('admin/images/AdminLTELogo.png') }}" alt="Default Logo" class="brand-image opacity-75 shadow" style="width: 32px; height: 32px;" />
                @endif
                <span class="brand-text fw-light fs-5">{{ $company->name ?? 'Company Name' }}</span>
            @endif
        </a>
    </div>

    <!-- Sidebar Menu -->
    <div class="sidebar-wrapper">
        <nav class="mt-3">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                @canany(['view_budget_plan','create_budget_plan','edit_budget_plan','delete_budget_plan','view_monthly_plan','create_monthly_plan','edit_monthly_plan','delete_monthly_plan','view_plan_vs_achievement','create_plan_vs_achievement','edit_plan_vs_achievement','delete_plan_vs_achievement'])
                <li class="nav-item {{ request()->is('admin/budget*') || request()->is('admin/monthly*') || request()->is('admin/achievement*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('admin/budget*') || request()->is('admin/monthly*') || request()->is('admin/achievement*') ? 'active' : '' }}">
                        <i class="bi bi-people-fill me-2"></i>
                        <p>
                            Planning
                            <i class="bi bi-chevron-right ms-auto"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview" 
                        style="{{ request()->is('admin/budget*') || request()->is('admin/monthly*') || request()->is('admin/achievement*') ? 'display: block;' : '' }}">
                        @canany(['view_budget_plan','create_budget_plan','edit_budget_plan','delete_budget_plan'])
                        <li class="nav-item">
                            <a href="{{ url('admin/budget') }}" class="nav-link {{ request()->is('admin/budget*') ? 'active' : '' }}">
                                <i class="bi bi-cash-stack me-2"></i>
                                <p>Budget Plan</p>
                            </a>
                        </li>
                        @endcanany
                        <li class="nav-item">
                            <a href="{{ route('budget.report') }}" class="nav-link {{ request()->routeIs('budget.report') ? 'active' : '' }}">
                                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                                <p>Budget Report</p>
                            </a>
                        </li>
                        @canany(['view_monthly_plan','create_monthly_plan','edit_monthly_plan','delete_monthly_plan'])
                        <li class="nav-item">
                            <a href="{{ url('admin/monthly') }}" class="nav-link {{ request()->is('admin/monthly*') ? 'active' : '' }}">
                                <i class="bi bi-calendar-month me-2"></i>
                                <p>Monthly Plan</p>
                            </a>
                        </li>
                        @endcanany
                        @canany(['view_plan_vs_achievement','create_plan_vs_achievement','edit_plan_vs_achievement','delete_plan_vs_achievement'])
                        <li class="nav-item">
                            <a href="{{ url('admin/achievement') }}" class="nav-link {{ request()->is('admin/achievement*') ? 'active' : '' }}">
                                <i class="bi bi-graph-up-arrow me-2"></i>
                                <p>Plan Vs Achievement</p>
                            </a>
                        </li>
                        @endcanany
                    </ul>
                </li>
                @endcanany

                @php
                    $partyActive = request()->is(
                        'admin/party',
                        'admin/party/*',
                        'admin/party-visit-report*',
                        'admin/new-party*',
                        'admin/party-payment*',
                        'admin/party-performance*',
                        'admin/party-ledger*'
                    );
                @endphp

                <!-- Party -->
                @canany(['view_party_visit','create_party_visit','edit_party_visit','delete_party_visit'])
                <li class="nav-item {{ $partyActive ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $partyActive ? 'active' : '' }}">
                        <i class="bi bi-people-fill me-2"></i>
                        <p>Party<i class="bi bi-chevron-right ms-auto"></i></p>
                    </a>

                    <ul class="nav nav-treeview" style="{{ $partyActive ? 'display:block;' : '' }}">
                        {{-- Party Visit --}}
                        <li class="nav-item">
                            <a href="{{ url('admin/party') }}"
                            class="nav-link {{ (request()->is('admin/party') || request()->is('admin/party/*')) ? 'active' : '' }}">
                                <i class="bi bi-cash-stack me-2"></i>
                                <p>Party Visit</p>
                            </a>
                        </li>

                        {{-- Party Visit Report --}}
                        <li class="nav-item">
                            <a href="{{ route('party-visit-report') }}"
                            class="nav-link {{ request()->routeIs('party-visit-report') ? 'active' : '' }}">
                                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                                <p>Party Visit Report</p>
                            </a>
                        </li>

                        {{-- New Party --}}
                        <li class="nav-item">
                            <a href="{{ url('admin/new-party') }}"
                            class="nav-link {{ request()->is('admin/new-party*') ? 'active' : '' }}">
                                <i class="bi bi-people-fill me-2"></i>
                                <p>New Party</p>
                            </a>
                        </li>

                        {{-- Party Payment --}}
                        <li class="nav-item">
                            <a href="{{ route('party-payment') }}"
                            class="nav-link {{ request()->is('admin/party-payment*') ? 'active' : '' }}">
                                <i class="bi bi-currency-rupee me-2"></i>
                                <p>Party Payment</p>
                            </a>
                        </li>

                        {{-- Party Performance --}}
                        <li class="nav-item">
                            <a href="{{ route('party.performance') }}"
                            class="nav-link {{ request()->routeIs('party.performance') ? 'active' : '' }}">
                                <i class="bi bi-bar-chart-fill me-2"></i>
                                <p>Party Performance</p>
                            </a>
                        </li>

                        {{-- Party Ledger --}}
                        <li class="nav-item">
                            <a href="{{ route('coming-soon') }}"
                            class="nav-link {{ request()->routeIs('coming-soon') ? 'active' : '' }}">
                                <i class="bi bi-journal-text me-2"></i>
                                <p>Party Ledger</p>
                            </a>
                        </li>

                    </ul>
                </li>

                @endcanany


                <!-- Order -->
                @canany(['view_order','create_order','edit_order','delete_order'])
                @php
                    $orderActive = request()->is(
                        'admin/order',
                        'admin/order/*',
                        'admin/order-report*'
                    );
                @endphp

                <li class="nav-item {{ $orderActive ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $orderActive ? 'active' : '' }}">
                        <i class="bi bi-bag-check-fill me-2"></i>
                        <p>Order<i class="bi bi-chevron-right ms-auto"></i></p>
                    </a>

                    <ul class="nav nav-treeview" style="{{ $orderActive ? 'display:block;' : '' }}">
                        {{-- Order List --}}
                        <li class="nav-item">
                            <a href="{{ url('admin/order') }}"
                            class="nav-link {{ (request()->is('admin/order') || request()->is('admin/order/*')) ? 'active' : '' }}">
                                <i class="bi bi-receipt me-2"></i>
                                <p>Order</p>
                            </a>
                        </li>

                        {{-- Order Report --}}
                        <li class="nav-item">
                            <a href="{{ url('admin/order-report') }}"
                            class="nav-link {{ request()->is('admin/order-report*') ? 'active' : '' }}">
                                <i class="bi bi-bar-chart-line-fill me-2"></i>
                                <p>Order Report</p>
                            </a>
                        </li>

                    </ul>
                </li>
                @endcanany

                <!-- Stock -->
                @canany(['view_stock','create_stock','edit_stock','delete_stock'])
                @php
                    $stockActive = request()->is(
                        'admin/stock',
                        'admin/stock/*',
                        'admin/stock-ageing*'
                    );
                @endphp

                <li class="nav-item {{ $stockActive ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $stockActive ? 'active' : '' }}">
                        <i class="bi bi-box-seam-fill me-2"></i>
                        <p>Stock<i class="bi bi-chevron-right ms-auto"></i></p>
                    </a>

                    <ul class="nav nav-treeview" style="{{ $stockActive ? 'display:block;' : '' }}">

                        {{-- Available Stock --}}
                        <li class="nav-item">
                            <a href="{{ url('admin/stock') }}"
                            class="nav-link {{ (request()->is('admin/stock') || request()->is('admin/stock/*')) ? 'active' : '' }}">
                                <i class="bi bi-boxes me-2"></i>
                                <p>Available Stock</p>
                            </a>
                        </li>

                        {{-- Stock Ageing --}}
                        <!-- <li class="nav-item">
                            <a href="{{ url('admin/stock-ageing') }}"
                            class="nav-link {{ request()->is('admin/stock-ageing*') ? 'active' : '' }}">
                                <i class="bi bi-hourglass-split me-2"></i>
                                <p>Stock Ageing</p>
                            </a>
                        </li> -->

                    </ul>
                </li>
                @endcanany

                <!-- Tracking -->
                {{-- @canany(['view_tracking','create_tracking','edit_tracking','delete_tracking']) --}}
                @php
                    $trackingActive = request()->is(
                        'admin/tracking',
                        'admin/tracking/*',
                        'admin/daily-trip*'
                    );
                @endphp

                <li class="nav-item {{ $trackingActive ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $trackingActive ? 'active' : '' }}">
                        <i class="bi bi-geo-alt-fill me-2"></i>
                        <p>
                            Tracking
                            <i class="bi bi-chevron-right ms-auto"></i>
                        </p>
                    </a>

                    <ul class="nav nav-treeview" style="{{ $trackingActive ? 'display:block;' : '' }}">

                        {{-- Emp On Map --}}
                        <li class="nav-item">
                            <a href="{{ url('admin/tracking') }}"
                            class="nav-link {{ (request()->is('admin/tracking') || request()->is('admin/tracking/*')) ? 'active' : '' }}">
                                <i class="bi bi-map-fill me-2"></i>
                                <p>Emp On Map</p>
                            </a>
                        </li>

                        {{-- Daily Trip --}}
                        <!-- <li class="nav-item">
                            <a href="{{ url('admin/daily-trip') }}"
                            class="nav-link {{ request()->is('admin/daily-trip*') ? 'active' : '' }}">
                                <i class="bi bi-truck-front-fill me-2"></i>
                                <p>Daily Trip</p>
                            </a>
                        </li> -->

                    </ul>
                </li>
                {{-- @endcanany --}}

                <!-- Attendance -->
                @canany(['view_attendance','create_attendance','edit_attendance','delete_attendance'])
                <li class="nav-item {{ request()->is('admin/hr/attendance*') ? 'menu-open' : '' }}">
                    
                    {{-- Main Attendance Menu --}}
                    <a href="#" class="nav-link {{ request()->is('admin/hr/attendance*') ? 'active' : '' }}">
                        <i class="bi bi-calendar-check-fill me-2"></i>
                        <p>Attendance<i class="bi bi-chevron-right ms-auto"></i></p>
                    </a>

                    {{-- Attendance List --}}
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('attendance.index') }}"
                            class="nav-link {{ request()->is('admin/hr/attendance') ? 'active' : '' }}">
                                <i class="bi bi-clipboard-check me-2"></i>
                                <p>Attendance</p>
                            </a>
                        </li>
                    </ul>

                    {{-- Leave Reports --}}
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('coming-soon') }}" class="nav-link">
                                <i class="bi bi-calendar-x me-2"></i>
                                <p>Leave Reports</p>
                            </a>
                        </li>
                    </ul>

                </li>
                @endcanany


                <!-- Expense -->
                @canany(['view_expense','create_expense','edit_expense','delete_expense'])
                <li class="nav-item {{ request()->is('admin/expense*') || request()->routeIs('expense.report*') ? 'menu-open' : '' }}">
                    
                    {{-- Parent --}}
                    <a href="#" class="nav-link {{ request()->is('admin/expense*') || request()->routeIs('expense.report*') ? 'active' : '' }}">
                        <i class="bi bi-wallet2 me-2"></i>
                        <p>
                            Expense
                            <i class="bi bi-chevron-right ms-auto"></i>
                        </p>
                    </a>

                    {{-- Expense List --}}
                    @canany(['view_expense','create_expense','edit_expense','delete_expense'])
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ url('admin/expense') }}"
                            class="nav-link {{ request()->is('admin/expense') ? 'active' : '' }}">
                                <i class="bi bi-receipt me-2"></i>
                                <p>Expense List</p>
                            </a>
                        </li>
                    </ul>
                    @endcanany

                    {{-- Monthly Expense Report --}}
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('expense.report') }}"
                            class="nav-link {{ request()->routeIs('expense.report*') ? 'active' : '' }}">
                                <i class="bi bi-calendar-month me-2"></i>
                                <p>Monthly Expense Report</p>
                            </a>
                        </li>
                    </ul>

                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('expense.state-wise-report') }}"
                            class="nav-link {{ request()->routeIs('expense.state-wise-report*') ? 'active' : '' }}">
                                <i class="bi bi-calendar-month me-2"></i>
                                <p>State Wise Expense Report</p>
                            </a>
                        </li>
                    </ul>


                    {{-- TA-DA Report --}}
                    <!-- <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('coming-soon') }}" class="nav-link">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                <p>TA-DA Report</p>
                            </a>
                        </li>
                    </ul> -->

                </li>
                @endcanany

                {{-- Filed Demo --}}
                <li class="nav-item {{ request()->routeIs('farmers.*') || request()->routeIs('farmer-visit-report*') ? 'menu-open' : '' }}">
                    {{-- Parent --}}
                    <a href="#" class="nav-link {{ request()->routeIs('farmers.*') || request()->routeIs('farmer-visit-report*') ? 'active' : '' }}">
                        <i class="bi bi-clipboard-check me-2"></i>
                        <p>Field Demo<i class="bi bi-chevron-right ms-auto"></i></p>
                    </a>

                    <ul class="nav nav-treeview">
                        {{-- Farmers List --}}
                        <li class="nav-item">
                            <a href="{{ route('farmers.index') }}"
                            class="nav-link {{ request()->routeIs('farmers.index') ? 'active' : '' }}">
                                <i class="bi bi-person-lines-fill me-2"></i>
                                <p>Farmers</p>
                            </a>
                        </li>

                        {{-- Daily Farm Demo --}}
                        <li class="nav-item">
                            <a href="{{ route('farmers.daily-farm-visits') }}"
                            class="nav-link {{ request()->routeIs('farmers.daily-farm-visits') ? 'active' : '' }}">
                                <i class="bi bi-calendar-check me-2"></i>
                                <p>Daily Farm Demo</p>
                            </a>
                        </li>

                        {{-- Farmer Visit Report --}}
                        <li class="nav-item">
                            <a href="{{ route('farmer-visit-report') }}"
                            class="nav-link {{ request()->routeIs('farmer-visit-report') ? 'active' : '' }}">
                                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                                <p>Farmer Visit Report</p>
                            </a>
                        </li>

                    </ul>
                </li>

                {{-- Daily Activity --}}
                <li class="nav-item {{ request()->is('admin/trips*') || request()->is('admin/party*') || request()->is('admin/new-party*') || request()->is('admin/expense*') || request()->is('admin/hr/attendance*') ? 'menu-open' : '' }}">
                    {{-- Parent --}}
                    <a href="#" class="nav-link {{ request()->is('admin/trips*') || request()->is('admin/party*') || request()->is('admin/new-party*') || request()->is('admin/expense*') || request()->is('admin/hr/attendance*') ? 'active' : '' }}">
                        <i class="bi bi-activity me-2"></i>
                        <p>Daily Activity<i class="bi bi-chevron-right ms-auto"></i></p>
                    </a>

                    {{-- Child Menu --}}
                    <ul class="nav nav-treeview">
                        {{-- All Trips --}}
                        @canany(['view_all_trip','create_all_trip','edit_all_trip','delete_all_trip'])
                        <li class="nav-item">
                            <a href="{{ url('admin/trips') }}"
                            class="nav-link {{ request()->is('admin/trips*') ? 'active' : '' }}">
                                <i class="bi bi-truck-front me-2"></i><p>All Trips</p>
                            </a>
                        </li>
                        @endcanany

                        {{-- Party Visit --}}
                        @canany(['view_party_visit','create_party_visit','edit_party_visit','delete_party_visit'])
                        <li class="nav-item">
                            <a href="{{ url('admin/party') }}"
                            class="nav-link {{ request()->is('admin/party*') ? 'active' : '' }}">
                                <i class="bi bi-shop me-2"></i><p>Party Visit</p>
                            </a>
                        </li>
                        @endcanany

                        {{-- New Party --}}
                        @canany(['view_new_party'])
                        <li class="nav-item">
                            <a href="{{ url('admin/new-party') }}"
                            class="nav-link {{ request()->is('admin/new-party*') ? 'active' : '' }}">
                                <i class="bi bi-person-plus-fill me-2"></i><p>New Party</p>
                            </a>
                        </li>
                        @endcanany

                        {{-- Expense --}}
                        <li class="nav-item">
                            <a href="{{ url('admin/expense') }}"
                            class="nav-link {{ request()->is('admin/expense*') ? 'active' : '' }}">
                                <i class="bi bi-wallet2 me-2"></i><p>Expense</p>
                            </a>
                        </li>

                        {{-- Attendance --}}
                        @canany(['view_attendance','create_attendance','edit_attendance','delete_attendance'])
                        <li class="nav-item">
                            <a href="{{ url('admin/hr/attendance') }}"
                            class="nav-link {{ request()->is('admin/hr/attendance*') ? 'active' : '' }}">
                                <i class="bi bi-clock-history me-2"></i><p>Attendance</p>
                            </a>
                        </li>
                        @endcanany

                    </ul>
                </li>


                <!-- Master Management -->
                @canany([
                'view_states','create_states','edit_states','delete_states',
                'view_districts','create_districts','edit_districts','delete_districts',
                'view_talukas','create_talukas','edit_talukas','delete_talukas',
                'view_vehicle_types','create_vehicle_types','edit_vehicle_types','delete_vehicle_types',
                'view_depo_master','create_depo_master','edit_depo_master','delete_depo_master',
                'view_holiday_master','create_holiday_master','edit_holiday_master','delete_holiday_master',
                'view_leave_master','create_leave_master','edit_leave_master','delete_leave_master'
                ])
                @php
                $masterActive =
                    request()->is('admin/states*')
                || request()->is('admin/districts*')
                || request()->is('admin/tehsils*')
                || request()->is('admin/tourtype*')
                || request()->is('admin/travelmode*')
                || request()->is('admin/purpose*')
                || request()->is('admin/hr/designations*')
                || request()->is('admin/leaves*')
                || request()->is('admin/holidays*')
                || request()->is('admin/vehicle*')
                || request()->is('admin/users*')
                || request()->is('admin/roles*')
                || request()->is('admin/permissions*')
                || request()->is('admin/products*')
                || request()->is('admin/product-categories*')
                || request()->is('admin/price*')
                || request()->is('admin/brochure*')
                || request()->is('admin/companies*')
                || request()->is('admin/customers*')
                || request()->is('admin/depos*')
                || request()->is('admin/messages*')
                || request()->is('admin/ta-da*')
                || request()->is('admin/ta-da-slab*')
                || request()->is('admin/ta-da-bill-master*')
                || request()->is('admin/crop-categories*')
                || request()->is('admin/crop-sub-categories*')
                || request()->is('admin/product-price-list*')
                || request()->routeIs('party.sync')
                || request()->routeIs('sales.bill.register')
                || request()->routeIs('party.opening.closing');
                @endphp
                <li class="nav-item {{ $masterActive ? 'menu-open' : '' }}">
                        
                    <a href="#" class="nav-link {{ $masterActive ? 'active' : '' }}">
                        <i class="bi bi-geo-alt-fill me-2"></i>
                        <p>Master<i class="bi bi-chevron-right ms-auto"></i></p>
                    </a>

                    <ul class="nav nav-treeview">
                        {{-- State Menu --}}
                        @php
                            $isStateMenu = request()->is(
                                'admin/states*','admin/districts*','admin/tehsils*'
                            );
                        @endphp

                        @canany([
                            'view_states','create_states','edit_states','delete_states',
                            'view_districts','create_districts','edit_districts','delete_districts',
                            'view_talukas','create_talukas','edit_talukas','delete_talukas'
                        ])
                        <li class="nav-item {{ $isStateMenu ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $isStateMenu ? 'active' : '' }}">
                                <i class="bi bi-flag me-2"></i>
                                <p>
                                    State
                                    <i class="bi bi-chevron-right ms-auto"></i>
                                </p>
                            </a>

                            <ul class="nav nav-treeview">

                                {{-- STATES --}}
                                @canany(['view_states','create_states','edit_states','delete_states'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/states') }}"
                                    class="nav-link {{ request()->is('admin/states*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i>
                                        <p>States</p>
                                    </a>
                                </li>
                                @endcanany

                                {{-- DISTRICTS --}}
                                @canany(['view_districts','create_districts','edit_districts','delete_districts'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/districts') }}"
                                    class="nav-link {{ request()->is('admin/districts*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i>
                                        <p>Districts</p>
                                    </a>
                                </li>
                                @endcanany

                                {{-- TALUKAS --}}
                                @canany(['view_talukas','create_talukas','edit_talukas','delete_talukas'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/tehsils') }}"
                                    class="nav-link {{ request()->is('admin/tehsils*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i>
                                        <p>Talukas</p>
                                    </a>
                                </li>
                                @endcanany

                            </ul>
                        </li>
                        @endcanany

                        {{-- Trip Master --}}
                        @php
                            $isTripMasterMenu = request()->is(
                                'admin/tourtype*','admin/travelmode*','admin/purpose*'
                            );
                        @endphp

                        <li class="nav-item {{ $isTripMasterMenu ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $isTripMasterMenu ? 'active' : '' }}">
                                <i class="bi bi-flag me-2"></i>
                                <p>
                                    Trip Master
                                    <i class="bi bi-chevron-right ms-auto"></i>
                                </p>
                            </a>

                            <ul class="nav nav-treeview">

                                {{-- TRIP TYPES --}}
                                @canany(['view_trip_types','create_trip_types','edit_trip_types','delete_trip_types'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/tourtype') }}"
                                    class="nav-link {{ request()->is('admin/tourtype*') ? 'active' : '' }}">
                                        <i class="bi bi-tag me-2"></i>
                                        <p>Trip Types</p>
                                    </a>
                                </li>
                                @endcanany

                                {{-- TRAVEL MODES --}}
                                @canany(['view_travel_modes','create_travel_modes','edit_travel_modes','delete_travel_modes'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/travelmode') }}"
                                    class="nav-link {{ request()->is('admin/travelmode*') ? 'active' : '' }}">
                                        <i class="bi bi-signpost me-2"></i>
                                        <p>Travel Modes</p>
                                    </a>
                                </li>
                                @endcanany

                                {{-- TRIP PURPOSES --}}
                                @canany(['view_trip_purposes','create_trip_purposes','edit_trip_purposes','delete_trip_purposes'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/purpose') }}"
                                    class="nav-link {{ request()->is('admin/purpose*') ? 'active' : '' }}">
                                        <i class="bi bi-bullseye me-2"></i>
                                        <p>Trip Purposes</p>
                                    </a>
                                </li>
                                @endcanany

                            </ul>
                        </li>


                        {{-- HR Master --}}
                        @php
                            $isHRMenu = request()->is(
                                'admin/hr/designations*','admin/leaves*','admin/holidays*','admin/vehicle*'
                            );
                        @endphp

                        <li class="nav-item {{ $isHRMenu ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $isHRMenu ? 'active' : '' }}">
                                <i class="bi bi-flag me-2"></i>
                                <p>HR<i class="bi bi-chevron-right ms-auto"></i></p>
                            </a>

                            <ul class="nav nav-treeview">
                                {{-- DESIGNATIONS --}}
                                @canany(['view_designations','create_designations','edit_designations','delete_designations'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/hr/designations') }}"
                                    class="nav-link {{ request()->is('admin/hr/designations*') ? 'active' : '' }}">
                                        <i class="bi bi-person-vcard me-2"></i>
                                        <p>Designations</p>
                                    </a>
                                </li>
                                @endcanany

                                {{-- LEAVE MASTER --}}
                                @canany(['view_leave_master','create_leave_master','edit_leave_master','delete_leave_master'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/leaves') }}"
                                    class="nav-link {{ request()->is('admin/leaves*') ? 'active' : '' }}">
                                        <i class="bi bi-calendar-check me-2"></i>
                                        <p>Leave Master</p>
                                    </a>
                                </li>
                                @endcanany

                                {{-- HOLIDAY MASTER --}}
                                @canany(['view_holiday_master','create_holiday_master','edit_holiday_master','delete_holiday_master'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/holidays') }}"
                                    class="nav-link {{ request()->is('admin/holidays*') ? 'active' : '' }}">
                                        <i class="bi bi-calendar-event me-2"></i>
                                        <p>Holiday Master</p>
                                    </a>
                                </li>
                                @endcanany

                                {{-- VEHICLE MASTER --}}
                                @canany(['view_vehicle_master','create_vehicle_master','edit_vehicle_master','delete_vehicle_master'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/vehicle') }}"
                                    class="nav-link {{ request()->is('admin/vehicle*') ? 'active' : '' }}">
                                        <i class="bi bi-car-front me-2"></i>
                                        <p>Vehicle Master</p>
                                    </a>
                                </li>
                                @endcanany

                            </ul>
                        </li>


                        @php
                            $isSalesPersonMenu = request()->is('admin/users*','admin/roles*','admin/permissions*');
                        @endphp

                        @canany([
                            'view_users','create_users','edit_users','delete_users',
                            'view_roles','create_roles','edit_roles','delete_roles',
                            'view_permissions','create_permissions','edit_permissions','delete_permissions'
                        ])
                        <li class="nav-item {{ $isSalesPersonMenu ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $isSalesPersonMenu ? 'active' : '' }}">
                                <i class="bi bi-people-fill me-2"></i>
                                <p>
                                    Sales Person
                                    <i class="bi bi-chevron-right ms-auto"></i>
                                </p>
                            </a>

                            <ul class="nav nav-treeview">

                                {{-- MANAGE USERS --}}
                                @canany(['view_users','create_users','edit_users','delete_users'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/users') }}"
                                    class="nav-link {{ request()->is('admin/users*') ? 'active' : '' }}">
                                        <i class="bi bi-person-fill me-2"></i>
                                        <p>Add Users</p>
                                    </a>
                                </li>
                                @endcanany

                                {{-- MANAGE ROLES --}}
                                @canany(['view_roles','create_roles','edit_roles','delete_roles'])
                                <li class="nav-item">
                                    <a href="{{ url('admin/roles') }}"
                                    class="nav-link {{ request()->is('admin/roles*') ? 'active' : '' }}">
                                        <i class="bi bi-shield-lock me-2"></i>
                                        <p>Manage Roles</p>
                                    </a>
                                </li>
                                @endcanany

                                {{-- MANAGE PERMISSIONS (ONLY MASTER ADMIN) --}}
                                @if(auth()->check() && auth()->user()->hasRole('master_admin'))
                                    @canany(['view_permissions','create_permissions','edit_permissions','delete_permissions'])
                                    <li class="nav-item">
                                        <a href="{{ url('admin/permissions') }}"
                                        class="nav-link {{ request()->is('admin/permissions*') ? 'active' : '' }}">
                                            <i class="bi bi-key me-2"></i>
                                            <p>Manage Permissions</p>
                                        </a>
                                    </li>
                                    @endcanany
                                @endif

                            </ul>
                        </li>
                        @endcanany


                        {{-- Depo Master --}}
                        @canany(['view_depo_master','create_depo_master','edit_depo_master','delete_depo_master'])
                        <!-- <li class="nav-item">
                            <a href="{{ url('admin/depos') }}" class="nav-link {{ request()->is('admin/depos*') ? 'active' : '' }}">
                                <i class="bi bi-person-lines-fill me-2"></i><p>Depo Master</p>
                            </a>
                        </li> -->
                        @endcanany

                        {{-- Companies --}}
                        @canany(['view_companies','create_companies','edit_companies','delete_companies'])
                        <li class="nav-item">
                            <a href="{{ url('admin/companies') }}" class="nav-link {{ request()->is('admin/companies*') ? 'active' : '' }}">
                                <i class="bi bi-buildings me-2"></i><p>Companies</p>
                            </a>
                        </li>
                        @endcanany

                        {{-- Customers --}}
                        @canany(['view_customers','create_customers','edit_customers','delete_customers'])
                        <li class="nav-item">
                            <a href="{{ url('admin/customers') }}" class="nav-link {{ request()->is('admin/customers*') ? 'active' : '' }}">
                                <i class="bi bi-person-lines-fill me-2"></i><p>Party (Customers)</p>
                            </a>
                        </li>
                        @endcanany

                        {{-- Product Master --}}
                        @php
                            $isProductMasterMenu = request()->is('admin/products*','admin/product-categories*') || request()->routeIs('products.price.list');
                        @endphp

                        <li class="nav-item {{ $isProductMasterMenu ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $isProductMasterMenu ? 'active' : '' }}">
                                <i class="bi bi-box-seam me-2"></i>
                                <p>Product Master<i class="bi bi-chevron-right ms-auto"></i></p>
                            </a>

                            <ul class="nav nav-treeview">
                                {{-- SALES PRODUCT MASTER --}}
                                <li class="nav-item">
                                    <a href="{{ url('admin/products') }}" class="nav-link {{ request()->is('admin/products*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Product Master</p>
                                    </a>
                                </li>

                                {{-- TECHNICAL MASTER --}}
                                <!-- <li class="nav-item">
                                    <a href="{{ route('coming-soon') }}" class="nav-link {{ request()->is('coming-soon*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Technical Master</p>
                                    </a>
                                </li> -->

                                {{-- PRODUCT CATEGORY --}}
                                <!-- <li class="nav-item">
                                    <a href="{{ url('admin/product-categories') }}" class="nav-link {{ request()->is('admin/product-categories*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Product Category</p>
                                    </a>
                                </li> -->

                                {{-- PRODUCT PRICE --}}
                                <li class="nav-item">
                                    <a href="{{ route('products.price.list') }}" class="nav-link {{ request()->routeIs('products.price.list') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Product Price</p>
                                    </a>
                                </li>

                                {{-- PRODUCT COLLECTION --}}
                                <!-- <li class="nav-item">
                                    <a href="{{ route('coming-soon') }}" class="nav-link {{ request()->is('coming-soon*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Product Collection</p>
                                    </a>
                                </li> -->

                                {{-- PRODUCT FINANCIAL MAPPING --}}
                                <!-- <li class="nav-item">
                                    <a href="{{ route('coming-soon') }}" class="nav-link {{ request()->is('coming-soon*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Product Financial Mapping</p>
                                    </a>
                                </li> -->

                            </ul>
                        </li>


                        @php
                            $isPriceMasterMenu = request()->is('admin/price*');
                        @endphp

                        <li class="nav-item {{ $isPriceMasterMenu ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $isPriceMasterMenu ? 'active' : '' }}">
                                <i class="bi bi-currency-exchange me-2"></i>
                                <p>Price<i class="bi bi-chevron-right ms-auto"></i></p>
                            </a>

                            <ul class="nav nav-treeview">
                                {{-- PRICE MASTER --}}
                                <!-- <li class="nav-item">
                                    <a href="{{ url('admin/price') }}" class="nav-link {{ request()->is('admin/price*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Price Master</p>
                                    </a>
                                </li> -->

                                {{-- PRICE LIST --}}
                                <li class="nav-item">
                                    <a href="{{ url('admin/price') }}" class="nav-link {{ request()->is('admin/price*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Price List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        {{-- Brochure --}}
                        @php
                            $isBrochureMenu = request()->is('admin/brochure*');
                        @endphp

                        <li class="nav-item {{ $isBrochureMenu ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $isBrochureMenu ? 'active' : '' }}">
                                <i class="bi bi-journal me-2"></i><p>Brochure<i class="bi bi-chevron-right ms-auto"></i></p>
                            </a>

                            <ul class="nav nav-treeview">
                                {{-- UPLOAD BROCHURE --}}
                                <li class="nav-item">
                                    <a href="{{ url('admin/brochure') }}" class="nav-link {{ request()->is('admin/brochure*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Upload Brochure</p>
                                    </a>
                                </li>
                            </ul>
                        </li>


                        @php
                            $isMessagesMenu = request()->is('admin/messages*');
                        @endphp

                        <li class="nav-item {{ $isMessagesMenu ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $isMessagesMenu ? 'active' : '' }}">
                                <i class="bi bi-journal me-2"></i><p>Messages<i class="bi bi-chevron-right ms-auto"></i></p>
                            </a>

                            <ul class="nav nav-treeview">
                                {{-- MESSAGES LIST --}}
                                <li class="nav-item">
                                    <a href="{{ url('admin/messages') }}" class="nav-link {{ request()->is('admin/messages*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Messages</p>
                                    </a>
                                </li>
                            </ul>
                        </li>


                        {{-- Expense Master --}}
                        @php
                            $isExpenseMasterMenu = request()->is('admin/ta-da-slab*','admin/ta-da-bill-master*');
                        @endphp

                        <li class="nav-item {{ $isExpenseMasterMenu ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $isExpenseMasterMenu ? 'active' : '' }}">
                                <i class="bi bi-wallet2 me-2"></i><p>Expense Master<i class="bi bi-chevron-right ms-auto"></i></p>
                            </a>

                            <ul class="nav nav-treeview">
                                {{-- TA-DA --}}
                                {{-- @can('view_ta_da') --}}
                                <li class="nav-item">
                                    <a href="{{ url('admin/ta-da-slab') }}" class="nav-link {{ request()->is('admin/ta-da-slab*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>TA-DA</p>
                                    </a>
                                </li>
                                {{-- @endcan --}}

                                {{-- TA-DA BILL MASTER --}}
                                {{-- @canany(['view_ta_da_bill_master','create_ta_da_bill_master','edit_ta_da_bill_master','delete_ta_da_bill_master']) --}}
                                <li class="nav-item">
                                    <a href="{{ url('admin/ta-da-bill-master') }}" class="nav-link {{ request()->is('admin/ta-da-bill-master*') ? 'active' : '' }}">
                                        <i class="bi bi-file-earmark-text me-2"></i><p>TA-DA Bill Master</p>
                                    </a>
                                </li>
                                {{-- @endcanany --}}
                            </ul>
                        </li>

                        @php
                            $isCropMasterMenu = request()->is('admin/crop-categories*','admin/crop-sub-categories*');
                        @endphp
                        <li class="nav-item {{ $isCropMasterMenu ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $isCropMasterMenu ? 'active' : '' }}">
                                <i class="bi bi-flower1 me-2"></i><p>Crop Master<i class="bi bi-chevron-right ms-auto"></i></p>
                            </a>

                            <ul class="nav nav-treeview">
                                {{-- Crop Category --}}
                                <li class="nav-item">
                                    <a href="{{ url('admin/crop-categories') }}" class="nav-link {{ request()->is('admin/crop-categories*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Crop Category</p>
                                    </a>
                                </li>

                                {{-- Crop Sub Category --}}
                                <li class="nav-item">
                                    <a href="{{ url('admin/crop-sub-categories') }}" class="nav-link {{ request()->is('admin/crop-sub-categories*') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Crop Sub Category</p>
                                    </a>
                                </li>

                            </ul>
                        </li>

                        {{-- Integration Menu --}}
                        @php
                            $isIntegrationMenu = request()->routeIs('party.sync') || request()->routeIs('sales.bill.register') || request()->routeIs('party.opening.closing') || request()->routeIs('partywise.payment.credit');
                        @endphp
                        <li class="nav-item {{ $isIntegrationMenu ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $isIntegrationMenu ? 'active' : '' }}">
                                <i class="bi bi-link-45deg me-2"></i><p>Integration<i class="bi bi-chevron-right ms-auto"></i></p>
                            </a>

                            <ul class="nav nav-treeview">
                                {{-- Party Sync --}}
                                <li class="nav-item">
                                    <a href="{{ route('party.sync') }}" class="nav-link {{ request()->routeIs('party.sync') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Party Sync</p>
                                    </a>
                                </li>

                                {{-- Sales Bill Register --}}
                                <li class="nav-item">
                                    <a href="{{ route('sales.bill.register') }}" class="nav-link {{ request()->routeIs('sales.bill.register') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Sales Bill Register</p>
                                    </a>
                                </li>

                                {{-- Partywise Opening & Closing --}}
                                <li class="nav-item">
                                    <a href="{{ route('party.opening.closing') }}" class="nav-link {{ request()->routeIs('party.opening.closing') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Party Opening/Closing</p>
                                    </a>
                                </li>
                                
                                {{-- Partywise Payment Credit --}}
                                <li class="nav-item">
                                    <a href="{{ route('partywise.payment.credit') }}" class="nav-link {{ request()->routeIs('partywise.payment.credit') ? 'active' : '' }}">
                                        <i class="bi bi-circle me-2"></i><p>Partywise Payment Credit</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                    </ul>
                </li>
                @endcanany
                
            </ul>
        </nav>
    </div>
</aside>
