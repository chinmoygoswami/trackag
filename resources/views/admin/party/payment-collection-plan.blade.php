@extends('admin.layout.layout')

@section('title', 'Payment Collection Plan | Trackag')

@push('styles')
<style>
    .collection-plan .month-card { min-width: 16rem; flex: 1 0 16rem; }
    .collection-plan .report-table th,
    .collection-plan .report-table td { padding: .65rem .8rem; }
    .collection-plan .party-column { min-width: 12rem; max-width: 16rem; white-space: normal; }
    @media screen {
        .collection-plan .report-table .party-column {
            position: sticky;
            inset-inline-start: 0;
            z-index: 1;
        }
    }
    .collection-plan .metric { font-variant-numeric: tabular-nums; }
    .collection-plan .plan-button { min-height: 32px; }
    .collection-plan .report-table .month-start { border-inline-start: 1px solid var(--bs-border-color); }
    @media print {
        .app-header, .app-sidebar, .app-footer, .collection-plan-filters, .collection-plan-actions { display: none !important; }
        .collection-plan .table-responsive, .collection-plan .overflow-auto { overflow: visible !important; }
        .collection-plan .report-table { font-size: 9pt; }
    }
</style>
@endpush

@php
    $formatter = new \NumberFormatter('en_IN', \NumberFormatter::DECIMAL);
    $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
    $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
    $money = fn ($value) => $value === null ? '—' : '₹' . $formatter->format((float) $value);
    $achievement = fn ($values) => $values['planned'] > 0 ? $values['actual'] / $values['planned'] * 100 : null;
    $percent = fn ($value) => $value === null ? '—' : number_format($value, 1) . '%';
    $monthTone = fn ($month) => $month->greaterThan($current) ? 'warning' : ($month->equalTo($current) ? 'success' : 'primary');
    $views = [
        'party' => ['Party Detail', 'bi-people'],
        'employee' => ['Employee Summary', 'bi-person-badge'],
        'state' => ['State Summary', 'bi-geo-alt'],
        'month' => ['Month Summary', 'bi-calendar3'],
    ];
@endphp

@section('content')
<main class="app-main collection-plan">
    <div class="app-content-header py-3 bg-light border-bottom">
        <div class="container-fluid px-3 px-lg-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center flex-wrap gap-3">
                    <h3 class="mb-0 text-primary"><i class="bi bi-calendar-check me-2"></i>Payment Collection Plan</h3>
                    @if ($pendingApprovalCount > 0)
                        <span class="badge rounded-pill text-bg-warning px-3 py-2">
                            <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
                            {{ $pendingApprovalCount }} pending your approval
                        </span>
                    @endif
                </div>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Payment Collection Plan</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="app-content py-4">
        <div class="container-fluid px-3 px-lg-4">
            @if (session('plan_status'))
                <div class="alert alert-success" role="status">{{ session('plan_status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            <form class="card shadow-sm mb-3 collection-plan-filters" method="GET" action="{{ route('payment-collection-plan.index') }}">
                <input type="hidden" name="view" value="{{ $view }}">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="from_month">From month</label>
                            <input class="form-control" type="month" id="from_month" name="from_month" value="{{ $from->format('Y-m') }}" required>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="to_month">To month</label>
                            <input class="form-control" type="month" id="to_month" name="to_month" value="{{ $to->format('Y-m') }}" required>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="employee">Employee</label>
                            <select class="form-select" id="employee" name="employee">
                                <option value="">All employees</option>
                                @foreach ($employees as $id => $name)
                                    <option value="{{ $id }}" @selected(isset($filters['employee']) && (int) $filters['employee'] === $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="state">State</label>
                            <select class="form-select" id="state" name="state">
                                <option value="">All states</option>
                                @foreach ($states as $id => $name)
                                    <option value="{{ $id }}" @selected(isset($filters['state']) && (int) $filters['state'] === $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <label class="form-label" for="party">Party</label>
                            <select class="form-select" id="party" name="party">
                                <option value="">All parties</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected((int) ($filters['party'] ?? 0) === $customer->id)>
                                        {{ $customer->agro_name }}{{ $customer->party_code ? ' · ' . $customer->party_code : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-2 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>View</button>
                            <a class="btn btn-outline-secondary" href="{{ route('payment-collection-plan.index') }}">Reset</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="d-flex flex-wrap gap-3 mb-3">
                <div class="card shadow-sm border-0 flex-fill">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <i class="bi bi-wallet2 fs-3 text-primary" aria-hidden="true"></i>
                        <div>
                            <div class="small text-body-secondary">Latest closing balance · {{ $reportPartyCount }} parties</div>
                            <div class="fs-4 fw-bold metric">{{ $money($totals['closing']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm border-0 flex-fill">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <i class="bi bi-bullseye fs-3 text-primary" aria-hidden="true"></i>
                        <div>
                            <div class="small text-body-secondary">
                                Plan accuracy <span tabindex="0" title="Completed months with a plan. 100% minus total absolute variance divided by total planned amount, with a minimum of 0%."><i class="bi bi-info-circle ms-1"></i></span>
                            </div>
                            <div class="fs-4 fw-bold metric">{{ $percent($accuracy) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3 overflow-auto pb-2 mb-3" aria-label="Monthly totals" tabindex="0">
                @foreach ($months as $key => $month)
                    @php($values = $totals['monthly'][$key])
                    <div class="card month-card border-0 bg-{{ $monthTone($month) }}-subtle">
                        <div class="card-body py-3">
                            <h6 class="fw-bold mb-2"><i class="bi bi-calendar3 me-2"></i>{{ $month->format('M Y') }}</h6>
                            <div class="d-flex flex-wrap gap-3">
                                <div><div class="small text-body-secondary">Outstanding</div><strong class="metric">{{ $money($values['monthly_outstanding']) }}</strong></div>
                                <div><div class="small text-body-secondary">Planned</div><strong class="metric">{{ $money($values['planned']) }}</strong></div>
                                <div><div class="small text-body-secondary">Actual</div><strong class="metric">{{ $money($values['actual']) }}</strong></div>
                                <div><div class="small text-body-secondary">Achieved</div><strong class="metric">{{ $percent($achievement($values)) }}</strong></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 collection-plan-actions">
                <nav class="d-flex flex-wrap gap-2" aria-label="Collection plan report views">
                    @foreach ($views as $key => [$label, $icon])
                        <a class="btn btn-sm {{ $view === $key ? 'btn-primary' : 'btn-outline-primary' }}"
                            @if ($view === $key) aria-current="page" @endif
                            href="{{ route('payment-collection-plan.index', array_merge(\Illuminate\Support\Arr::except($filters, ['page']), ['view' => $key])) }}">
                            <i class="bi {{ $icon }} me-1"></i>{{ $label }}
                        </a>
                    @endforeach
                </nav>
                <button class="btn btn-sm btn-outline-secondary" type="button" id="printCollectionPlan"><i class="bi bi-printer me-1"></i>Print</button>
            </div>

            <section class="card shadow-sm" aria-labelledby="collection-report-title">
                <div class="card-header py-3">
                    <h5 class="mb-1 text-primary fw-semibold" id="collection-report-title">{{ $views[$view][0] }}</h5>
                    <div class="small text-body-secondary">
                        Next month’s outstanding updates with Tally until the current month closes. Each month then shows the saved closing balance from the last day of the previous month.
                        @if ($view === 'party')
                            Add or edit targets for upcoming months only. Select Review in the Planned column for any submission you can approve. Scroll horizontally to see more months.
                        @endif
                    </div>
                </div>
                <div class="table-responsive" tabindex="0" aria-label="{{ $views[$view][0] }} table">
                    <table class="report-table table table-sm table-hover align-middle text-nowrap mb-0">
                        @if ($view === 'month')
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Month</th>
                                    <th scope="col" class="text-end">Outstanding</th>
                                    <th scope="col" class="text-end">Planned</th>
                                    <th scope="col" class="text-end">Actual</th>
                                    <th scope="col" class="text-end">Achieved</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($months as $key => $month)
                                    @php($values = $totals['monthly'][$key])
                                    <tr>
                                        <th scope="row">{{ $month->format('F Y') }}</th>
                                        <td class="text-end metric">{{ $money($values['monthly_outstanding']) }}</td>
                                        <td class="text-end metric">{{ $money($values['planned']) }}</td>
                                        <td class="text-end metric">{{ $money($values['actual']) }}</td>
                                        <td class="text-end metric">{{ $percent($achievement($values)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @else
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2" scope="col" class="party-column">{{ ucfirst($view) }}</th>
                                    @if ($view === 'party')
                                        <th rowspan="2" scope="col">Employee / State</th>
                                    @endif
                                    <th rowspan="2" scope="col" class="text-end">Latest closing balance</th>
                                    @foreach ($months as $month)
                                        <th scope="colgroup" colspan="4" class="month-start text-center bg-{{ $monthTone($month) }}-subtle">{{ $month->format('M Y') }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach ($months as $month)
                                        <th scope="col" class="month-start text-end">Outstanding</th>
                                        <th scope="col" class="text-end">Planned</th>
                                        <th scope="col" class="text-end">Actual</th>
                                        <th scope="col" class="text-end">Achieved</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($view === 'party' ? $parties : $groups) as $row)
                                    <tr>
                                        <th scope="row" class="party-column">
                                            {{ $row['name'] }}
                                            @if ($view === 'party' && $row['code'])
                                                <div class="small fw-normal text-body-secondary">{{ $row['code'] }}</div>
                                            @elseif ($view !== 'party')
                                                <div class="small fw-normal text-body-secondary">{{ $row['count'] }} {{ Str::plural('party', $row['count']) }}</div>
                                            @endif
                                        </th>
                                        @if ($view === 'party')
                                            <td>{{ $row['employee'] }}<div class="small text-body-secondary">{{ $row['state'] }}</div></td>
                                        @endif
                                        <td class="text-end metric">
                                            {{ $money($row['closing']) }}
                                            @if (!empty($row['balance_date']))
                                                <div class="small text-body-secondary">{{ $row['balance_date'] }}</div>
                                            @endif
                                        </td>
                                        @foreach ($row['monthly'] as $key => $values)
                                            @php($review = $view === 'party' ? $reviews->get($row['id'])?->get($months[$key]->toDateString()) : null)
                                            <td class="month-start text-end metric">{{ $money($values['monthly_outstanding']) }}</td>
                                            <td class="text-end metric">
                                                @if ($review)
                                                    <button type="button" class="btn btn-sm btn-outline-warning plan-button"
                                                        data-bs-toggle="modal" data-bs-target="#collectionPlanReview"
                                                        data-action="{{ route('payment-collection-plan.review', $review->id) }}"
                                                        data-version="{{ $review->submission_version }}" data-amount="{{ $review->pending_amount }}"
                                                        data-editable="{{ $months[$key]->greaterThan($current) ? '1' : '0' }}"
                                                        data-party="{{ $row['name'] }}" data-month="{{ $months[$key]->format('F Y') }}"
                                                        data-current="{{ $money($review->planned_amount) }}"
                                                        aria-label="Review plan for {{ $row['name'] }} in {{ $months[$key]->format('F Y') }}">
                                                        Review {{ $money($review->pending_amount) }}
                                                    </button>
                                                    <div class="small text-body-secondary mt-1">Awaiting approval from {{ auth()->user()->name }}</div>
                                                @elseif ($view === 'party' && $values['approved'])
                                                    {{ $money($values['planned']) }}
                                                    <div class="mt-1"><span class="badge text-bg-success">Approved</span></div>
                                                @elseif ($view === 'party' && $row['can_submit'] && $months[$key]->greaterThan($current) && !$values['forwarded'])
                                                    @php($displayAmount = $values['own_pending_amount'] ?? $values['planned'])
                                                    @php($hasTarget = $values['own_pending_amount'] !== null || $values['planned'] > 0)
                                                    <button type="button" class="btn btn-sm btn-outline-primary plan-button"
                                                        data-bs-toggle="modal" data-bs-target="#collectionPlanEditor"
                                                        data-customer="{{ $row['id'] }}" data-party="{{ $row['name'] }}"
                                                        data-month="{{ $key }}" data-month-label="{{ $months[$key]->format('F Y') }}"
                                                        data-balance="{{ $money($values['outstanding'] ?? $row['closing']) }}" data-amount="{{ $displayAmount }}" data-pending="{{ $values['own_pending_amount'] !== null ? '1' : '0' }}"
                                                        aria-label="{{ $hasTarget ? 'Edit' : 'Add' }} plan for {{ $row['name'] }} in {{ $months[$key]->format('F Y') }}">
                                                        <i class="bi {{ $hasTarget ? 'bi-pencil' : 'bi-plus-lg' }} me-1" aria-hidden="true"></i>{{ $hasTarget ? $money($displayAmount) : 'Add plan' }}
                                                    </button>
                                                @else
                                                    {{ $money($values['forwarded_amount'] ?? $values['own_pending_amount'] ?? $values['planned']) }}
                                                @endif
                                                @if ($view === 'party' && $values['pending'] && !$review)
                                                    <div class="mt-1"><span class="badge text-bg-warning">{{ $values['forwarded'] ? 'Pending next approval' : 'Pending approval' }}</span></div>
                                                    <div class="small text-body-secondary mt-1">
                                                        @if ($values['pending_approver'])
                                                            Awaiting approval from {{ $values['pending_approver'] }}
                                                        @else
                                                            Approver unavailable
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-end metric">{{ $money($values['actual']) }}</td>
                                            <td class="text-end metric">{{ $percent($achievement($values)) }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ ($view === 'party' ? 3 : 2) + 4 * $months->count() }}" class="text-center text-body-secondary py-4">No parties match these filters.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($reportPartyCount > 0)
                                <tfoot class="table-success fw-semibold">
                                    <tr>
                                        <th scope="row" colspan="{{ $view === 'party' ? 2 : 1 }}">Total for all filtered parties</th>
                                        <td class="text-end metric">{{ $money($totals['closing']) }}</td>
                                        @foreach ($totals['monthly'] as $values)
                                            <td class="month-start text-end metric">{{ $money($values['monthly_outstanding']) }}</td>
                                            <td class="text-end metric">{{ $money($values['planned']) }}</td>
                                            <td class="text-end metric">{{ $money($values['actual']) }}</td>
                                            <td class="text-end metric">{{ $percent($achievement($values)) }}</td>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            @endif
                        @endif
                    </table>
                </div>
                @if ($view === 'party' && $parties->hasPages())
                    <div class="card-footer collection-plan-actions">{{ $parties->links('pagination::bootstrap-5') }}</div>
                @endif
            </section>
        </div>
    </div>

    @if ($view === 'party')
        <div class="modal fade" id="collectionPlanEditor" tabindex="-1" aria-labelledby="collectionPlanEditorTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('payment-collection-plan.store') }}">
                    @csrf
                    <input type="hidden" name="customer_id">
                    <input type="hidden" name="collection_month">
                    @foreach (\Illuminate\Support\Arr::only($filters, ['from_month', 'to_month', 'employee', 'state', 'party', 'page']) as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <div class="modal-header">
                        <h5 class="modal-title" id="collectionPlanEditorTitle">Monthly collection target</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="fw-semibold" data-plan-party></div>
                        <div class="text-body-secondary mb-3" data-plan-month></div>
                        <div class="d-flex justify-content-between bg-body-tertiary rounded p-3 mb-3">
                            <span>Outstanding at planning</span>
                            <strong data-plan-balance></strong>
                        </div>
                        <label for="planned_amount" class="form-label">Planned amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input class="form-control" id="planned_amount" name="planned_amount" type="text"
                                inputmode="decimal" pattern="[0-9]{1,13}([.][0-9]{1,2})?" maxlength="16" aria-describedby="planAmountHint" required>
                        </div>
                        <div class="form-text" id="planAmountHint">{{ $usesReportingManager ? 'Your reporting manager' : 'A sub admin' }} will review this amount. The target is locked after approval.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit for approval</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal fade" id="collectionPlanReview" tabindex="-1" aria-labelledby="collectionPlanReviewTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="submission_version">
                    <div class="modal-header">
                        <h5 class="modal-title" id="collectionPlanReviewTitle">Review monthly target</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="fw-semibold" data-review-party></div>
                        <div class="text-body-secondary mb-3" data-review-month></div>
                        <div class="d-flex justify-content-between bg-body-tertiary rounded p-3 mb-3">
                            <span>Current approved target</span>
                            <strong data-review-current></strong>
                        </div>
                        <label for="review_amount" class="form-label">Proposed amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input class="form-control" id="review_amount" name="planned_amount" type="text"
                                inputmode="decimal" pattern="[0-9]{1,13}([.][0-9]{1,2})?" maxlength="16" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" name="decision" value="approve">{{ $usesReportingManager ? 'Approve and forward' : 'Approve' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</main>
@endsection

@push('scripts')
<script>
    document.getElementById('printCollectionPlan').addEventListener('click', () => window.print());

    const planEditor = document.getElementById('collectionPlanEditor');
    if (planEditor) {
        const amount = planEditor.querySelector('[name="planned_amount"]');
        planEditor.addEventListener('show.bs.modal', event => {
            const plan = event.relatedTarget.dataset;
            planEditor.querySelector('[name="customer_id"]').value = plan.customer;
            planEditor.querySelector('[name="collection_month"]').value = plan.month;
            planEditor.querySelector('[data-plan-party]').textContent = plan.party;
            planEditor.querySelector('[data-plan-month]').textContent = plan.monthLabel;
            planEditor.querySelector('[data-plan-balance]').textContent = plan.balance;
            amount.value = plan.pending === '1' || Number(plan.amount) > 0 ? plan.amount : '';
        });
        planEditor.addEventListener('shown.bs.modal', () => amount.focus());
    }

    const planReview = document.getElementById('collectionPlanReview');
    if (planReview) {
        const form = planReview.querySelector('form');
        const amount = form.elements.planned_amount;
        planReview.addEventListener('show.bs.modal', event => {
            const plan = event.relatedTarget.dataset;
            form.action = plan.action;
            form.elements.submission_version.value = plan.version;
            amount.value = plan.amount;
            amount.readOnly = plan.editable !== '1';
            planReview.querySelector('[data-review-party]').textContent = plan.party;
            planReview.querySelector('[data-review-month]').textContent = plan.month;
            planReview.querySelector('[data-review-current]').textContent = plan.current;
        });
        planReview.addEventListener('shown.bs.modal', () => amount.focus());
    }
</script>
@endpush
