<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PaymentCollectionPlan;
use App\Models\TallyOpeningClosing;
use App\Models\TallyPartySync;
use App\Models\TallyPartywisePaymentCredit;
use App\Models\TallySalesBill;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentCollectionPlanController extends Controller
{
    private const MONTH_PATTERN = 'regex:/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])\z/';

    private const AMOUNT_PATTERN = 'regex:/\A[0-9]{1,13}(?:\.[0-9]{1,2})?\z/';

    public function index(Request $request)
    {
        if (! $this->planStorageAvailable()) {
            return view('admin.party.payment-collection-plan-unavailable');
        }

        $filters = $request->validate([
            'from_month' => ['nullable', 'date_format:Y-m', self::MONTH_PATTERN],
            'to_month' => ['nullable', 'date_format:Y-m', self::MONTH_PATTERN],
            'employee' => 'nullable|integer|min:0',
            'state' => 'nullable|integer|min:0',
            'party' => 'nullable|integer|min:1',
            'view' => 'nullable|in:party,employee,state,month',
            'page' => 'nullable|integer|min:1',
        ]);

        $current = CarbonImmutable::now()->startOfMonth();
        if (! isset($filters['from_month'], $filters['to_month'])) {
            $financialYearStart = $this->defaultFinancialYearStart($current);
            $filters['from_month'] ??= $financialYearStart->format('Y-m');
            $filters['to_month'] ??= $financialYearStart->addMonths(11)->format('Y-m');
        }

        $from = CarbonImmutable::parse($filters['from_month'].'-01');
        $to = CarbonImmutable::parse($filters['to_month'].'-01');

        if ($to->lessThan($from) || $to->greaterThan($from->addMonths(11))) {
            throw ValidationException::withMessages(['to_month' => 'Select an end month within 12 months of the start month.'])
                ->redirectTo(route('payment-collection-plan.index'));
        }

        $months = collect();
        for ($month = $from; $month->lessThanOrEqualTo($to); $month = $month->addMonth()) {
            $months->put($month->format('Y-m'), $month);
        }

        $user = $request->user();
        $employees = User::query()
            ->where(fn (Builder $employees) => $employees->whereKey($user->id)->orWhere('reporting_to', $user->id))
            ->pluck('name', 'id')->sort(SORT_NATURAL | SORT_FLAG_CASE);

        $view = $filters['view'] ?? 'party';
        $reviews = $view === 'party'
            ? $this->reviewablePlans($user)->whereBetween('collection_month', [$from->toDateString(), $to->toDateString()])
                ->get(['id', 'customer_id', 'collection_month', 'pending_amount', 'planned_amount', 'submission_version'])
                ->groupBy('customer_id')->map(fn (Collection $plans) => $plans->keyBy('collection_month'))
            : collect();

        $assignedEmployees = $employees->keys();
        $customers = $this->activeParties()
            ->where(fn (Builder $parties) => $parties->whereIn('user_id', $assignedEmployees)->orWhereIn('id', $reviews->keys()))
            ->with(['user:id,name,reporting_to,company_id', 'user.reportingManager:id,name', 'state:id,name'])
            ->orderBy('agro_name')->orderBy('id')
            ->get(['id', 'agro_name', 'party_code', 'user_id', 'state_id']);

        $employees = $employees->union($customers->pluck('user.name', 'user_id'))->sort(SORT_NATURAL | SORT_FLAG_CASE);
        $states = $customers->mapWithKeys(fn ($party) => [
            ($party->state?->id ?? 0) => ($party->state?->name ?? 'Unassigned'),
        ])->sort(SORT_NATURAL | SORT_FLAG_CASE);

        $selected = $customers->filter(fn ($party) => (! isset($filters['employee']) || (int) $filters['employee'] === ($party->user?->id ?? 0))
            && (! isset($filters['state']) || (int) $filters['state'] === ($party->state?->id ?? 0))
            && (! isset($filters['party']) || (int) $filters['party'] === $party->id)
        )->values();

        $assigned = $selected->whereIn('user_id', $assignedEmployees);
        $rows = $this->reportRows($assigned, $months, $from, $to, $user);
        $reportPartyCount = $rows->count();
        $usesReportingManager = $this->reportingManagerExists($user);
        $totals = $this->totals($rows, $months);
        $completedMonths = $months->filter(fn (CarbonImmutable $month) => $month->lessThan($current));
        $completedPlans = $rows->flatMap(fn (array $row) => $row['monthly']->only($completedMonths->keys())->values())
            ->filter(fn (array $month) => $month['planned'] > 0);
        $completedTarget = $completedPlans->sum('planned');
        $accuracy = $completedTarget > 0
            ? max(0, 100 * (1 - $completedPlans->sum(fn (array $month) => abs($month['planned'] - $month['actual'])) / $completedTarget))
            : null;
        $groups = collect();
        if (in_array($view, ['employee', 'state'], true)) {
            $groups = $rows->groupBy($view.'_id')->map(fn (Collection $group) => [
                'name' => $group->first()[$view],
                'count' => $group->count(),
                ...$this->totals($group, $months),
            ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
        }

        // Same-company fallback reviews have a row for their pending cells only.
        $detailRows = $selected->map(function ($party) use ($rows, $months) {
            return $rows->get($party->id) ?? [
                'id' => $party->id,
                'name' => $party->agro_name,
                'code' => $party->party_code,
                'employee' => $party->user?->name ?? 'Unassigned',
                'state' => $party->state?->name ?? 'Unassigned',
                'closing' => null,
                'can_submit' => false,
                'monthly' => $months->map(fn (CarbonImmutable $month) => ['planned' => null, 'actual' => null, 'pending' => false, 'approved' => false, 'forwarded' => false]),
            ];
        });

        $perPage = 30;
        $page = min((int) ($filters['page'] ?? 1), max(1, (int) ceil($detailRows->count() / $perPage)));
        $parties = new LengthAwarePaginator(
            $detailRows->forPage($page, $perPage), $detailRows->count(), $perPage, $page, ['path' => $request->url()]
        );
        $parties->withQueryString();

        return view('admin.party.payment-collection-plan', compact(
            'filters', 'current', 'from', 'to', 'months', 'customers', 'employees', 'states',
            'parties', 'totals', 'groups', 'view', 'accuracy', 'reportPartyCount', 'reviews', 'usesReportingManager'
        ));
    }

    private function planStorageAvailable(): bool
    {
        $plan = new PaymentCollectionPlan;

        return $plan->getConnection()->getSchemaBuilder()->hasColumns($plan->getTable(), [
            'id', 'customer_id', 'collection_month', 'planned_amount', 'pending_amount',
            'submitted_by', 'next_reviewer_id', 'submission_version', 'approved_by', 'approved_at',
            'created_at', 'updated_at',
        ]);
    }

    private function defaultFinancialYearStart(CarbonImmutable $current): CarbonImmutable
    {
        // Party Performance selects the financial year containing the latest Tally sales or payment month.
        $latest = max(TallySalesBill::max('invoice_date'), TallyPartywisePaymentCredit::max('payment_date'));
        $reference = $latest ? CarbonImmutable::parse($latest) : $current;
        $year = $reference->month < 4 ? $reference->year - 1 : $reference->year;

        return $current->setDate($year, 4, 1);
    }

    public function store(Request $request)
    {
        if (! $this->planStorageAvailable()) {
            return redirect()->route('payment-collection-plan.index');
        }

        $validated = $request->validate([
            'customer_id' => 'required|integer|min:1',
            'collection_month' => ['required', 'date_format:Y-m', self::MONTH_PATTERN],
            'planned_amount' => ['required', self::AMOUNT_PATTERN],
        ], [
            'planned_amount.regex' => 'Enter a non-negative amount with up to 13 whole digits and two decimal places.',
        ]);

        $user = $request->user();
        $customerId = $validated['customer_id'];
        $month = $validated['collection_month'].'-01';
        $plans = PaymentCollectionPlan::query()->toBase();

        $plans->getConnection()->transaction(function () use ($plans, $customerId, $month, $user, $validated) {
            // Check ownership and lock the party on the same connection as the plan write.
            $customer = $plans->getConnection()->table('customers')
                ->where('id', $customerId)->where('user_id', $user->id)
                ->where('type', 'web')->where('is_active', 1)->lockForUpdate()->first(['id']);
            abort_unless($customer, 404);

            // A new owner can supersede a pending submission from the former owner.
            (clone $plans)->upsert([
                'customer_id' => $customerId,
                'collection_month' => $month,
                'planned_amount' => '0.00',
                'created_at' => now(),
                'updated_at' => now(),
            ], ['customer_id', 'collection_month'], ['customer_id']);

            $target = (clone $plans)->where('customer_id', $customerId)->where('collection_month', $month);
            $updated = $target->whereNull('approved_at')->where('planned_amount', 0)
                ->where(fn ($pending) => $pending->whereNull('next_reviewer_id')->orWhere('submitted_by', '!=', $user->id))
                ->update([
                    'pending_amount' => $validated['planned_amount'],
                    'submitted_by' => $user->id,
                    'next_reviewer_id' => null,
                    'submission_version' => (string) Str::uuid(),
                    'updated_at' => now(),
                ]);

            if (! $updated) {
                $current = $plans->getConnection()->table('payment_collection_plans')
                    ->where('customer_id', $customerId)->where('collection_month', $month)->first();
                $message = $current->approved_at !== null || $current->planned_amount > 0
                    ? 'This collection target is already approved and cannot be edited.'
                    : 'This target has moved to the next approver and cannot be edited.';
                throw ValidationException::withMessages(['planned_amount' => $message]);
            }
        });

        $reviewer = $this->reportingManagerExists($user) ? 'your reporting manager' : 'a sub admin';

        return redirect()->route('payment-collection-plan.index', $request->only([
            'from_month', 'to_month', 'employee', 'state', 'party', 'page',
        ]))->with('plan_status', 'Collection target submitted to '.$reviewer.' for approval.');
    }

    public function review(Request $request, int $plan)
    {
        if (! $this->planStorageAvailable()) {
            return redirect()->route('payment-collection-plan.index');
        }

        $validated = $request->validate([
            'submission_version' => 'required|uuid',
            'planned_amount' => ['required', self::AMOUNT_PATTERN],
            'decision' => 'required|in:approve',
        ]);

        $reviewer = $request->user();
        $target = $this->reviewablePlans($reviewer)->where('payment_collection_plans.id', $plan);
        $submission = (clone $target)->first(['submitted_by']);
        abort_unless($submission, 404);

        $next = $this->nextReviewer($reviewer, (int) $submission->submitted_by);
        $changes = $next
            ? [
                'pending_amount' => $validated['planned_amount'],
                'next_reviewer_id' => $next->id,
                'submission_version' => (string) Str::uuid(),
            ]
            : [
                'planned_amount' => $validated['planned_amount'],
                'pending_amount' => null,
                'submitted_by' => null,
                'next_reviewer_id' => null,
                'submission_version' => null,
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
            ];

        $updated = $target->where('submission_version', $validated['submission_version'])->toBase()
            ->update([...$changes, 'updated_at' => now()]);
        if (! $updated) {
            throw ValidationException::withMessages(['planned_amount' => 'This submission has changed. Reload the page and review the latest amount.']);
        }

        return back()->with('plan_status', $next
            ? 'Collection target forwarded to '.$next->name.' for approval.'
            : 'Collection target approved.');
    }

    private function nextReviewer(User $reviewer, int $submitterId): ?User
    {
        $next = null;
        $seen = [$submitterId => true, (int) $reviewer->id => true];
        $current = $reviewer;

        while ($current->reporting_to && (int) $current->reporting_to !== (int) $current->id) {
            $manager = User::query()->find($current->reporting_to, ['id', 'name', 'reporting_to']);

            if (! $manager) {
                break;
            }

            if (isset($seen[$manager->id])) {
                throw ValidationException::withMessages(['planned_amount' => 'The reporting chain contains a loop. Correct the reporting assignments in Users before approving this target.']);
            }

            $seen[$manager->id] = true;
            $next ??= $manager;
            $current = $manager;
        }

        return $next;
    }

    private function reportingManagerExists(User $user): bool
    {
        return $user->reporting_to && (int) $user->reporting_to !== (int) $user->id
            && User::query()->whereKey($user->reporting_to)->exists();
    }

    private function reviewableSubmitters(User $reviewer): Builder
    {
        $isSubAdmin = $reviewer->hasRole('sub_admin');

        return User::query()->select('users.id')->whereKeyNot($reviewer->id)
            ->where(function (Builder $submitters) use ($reviewer, $isSubAdmin) {
                $submitters->where('reporting_to', $reviewer->id);

                if ($isSubAdmin) {
                    $submitters->orWhere(function (Builder $unassigned) use ($reviewer) {
                        $unassigned->where('company_id', $reviewer->company_id)
                            ->where(function (Builder $manager) {
                                $manager->whereDoesntHave('reportingManager')
                                    ->orWhereColumn('users.reporting_to', 'users.id');
                            });
                    });
                }
            });
    }

    private function reviewablePlans(User $user): Builder
    {
        $submitters = $this->reviewableSubmitters($user);
        $assigned = $this->activeParties()->select('customers.id')
            ->whereColumn('customers.user_id', 'payment_collection_plans.submitted_by');

        return PaymentCollectionPlan::query()
            ->whereNull('payment_collection_plans.approved_at')
            ->where('payment_collection_plans.planned_amount', 0)
            ->where('payment_collection_plans.submitted_by', '!=', $user->id)
            ->where(function (Builder $plans) use ($submitters, $user) {
                $plans->where('payment_collection_plans.next_reviewer_id', $user->id)
                    ->orWhere(fn (Builder $initial) => $initial->whereNull('payment_collection_plans.next_reviewer_id')
                        ->whereIn('payment_collection_plans.submitted_by', $submitters));
            })
            ->whereIn('payment_collection_plans.customer_id', $assigned);
    }

    private function activeParties(): Builder
    {
        return Customer::query()->where('type', 'web')->where('is_active', 1);
    }

    private function reportRows(Collection $customers, Collection $months, CarbonImmutable $from, CarbonImmutable $to, User $user): Collection
    {
        if ($customers->isEmpty()) {
            return collect();
        }

        // Party Performance resolves the Tally ledger by party code first, then by name.
        $sync = TallyPartySync::query()
            ->where(function (Builder $query) use ($customers) {
                $query->whereIn('master_id', $customers->pluck('party_code')->filter()->unique())
                    ->orWhereIn('party_name', $customers->pluck('agro_name')->filter()->unique());
            })->get(['master_id', 'party_name']);
        $byCode = $sync->keyBy('master_id');
        $byName = $sync->keyBy('party_name');

        $ledgers = $customers->mapWithKeys(fn ($party) => [
            $party->id => ($party->party_code ? $byCode->get($party->party_code) : null)?->party_name
                ?? $byName->get($party->agro_name)?->party_name,
        ]);
        $ledgerNames = $ledgers->filter()->unique()->values();
        $balances = TallyOpeningClosing::query()->whereIn('party_name', $ledgerNames)
            ->orderByDesc('date')->get(['party_name', 'date', 'closing_balance_amt'])
            ->unique('party_name')->keyBy('party_name');

        $payments = TallyPartywisePaymentCredit::query()->whereIn('party_name', $ledgerNames)
            ->whereBetween('payment_date', [$from->toDateString(), $to->endOfMonth()->toDateString()])
            ->selectRaw("party_name, DATE_FORMAT(payment_date, '%Y-%m') as month_key, SUM(credit_amount) as amount")
            ->groupBy('party_name', 'month_key')->get()
            ->groupBy('party_name')->map(fn (Collection $entries) => $entries->pluck('amount', 'month_key'));

        $plans = PaymentCollectionPlan::query()->whereIn('customer_id', $customers->modelKeys())
            ->whereBetween('collection_month', [$from->toDateString(), $to->toDateString()])->get()
            ->groupBy('customer_id')->map(fn (Collection $entries) => $entries->keyBy('collection_month'));

        $nextReviewerIds = $plans->flatMap(fn (Collection $entries) => $entries->pluck('next_reviewer_id'))
            ->filter()->unique();
        $nextReviewerNames = User::query()->whereIn('id', $nextReviewerIds)->pluck('name', 'id');

        $subAdminsByCompany = $plans->isEmpty() ? collect() : User::query()
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'sub_admin'))
            ->orderBy('name')->get(['id', 'name', 'company_id'])->groupBy('company_id');

        return $customers->map(function ($party) use ($ledgers, $balances, $payments, $plans, $months, $user, $nextReviewerNames, $subAdminsByCompany) {
            $ledger = $ledgers[$party->id];
            $balance = $balances->get($ledger);
            $owner = $party->user;
            $manager = $owner?->reportingManager;
            $firstApprover = $manager && (int) $manager->id !== (int) $owner->id
                ? $manager->name
                : $subAdminsByCompany->get((string) $owner?->company_id)?->where('id', '!=', $party->user_id)->pluck('name')->join(', ', ' or ');

            return [
                'id' => $party->id,
                'name' => $party->agro_name,
                'code' => $party->party_code,
                'employee_id' => $party->user_id,
                'can_submit' => (int) $party->user_id === (int) $user->id,
                'employee' => $party->user?->name ?? 'Unassigned',
                'state_id' => $party->state?->id ?? 0,
                'state' => $party->state?->name ?? 'Unassigned',
                'closing' => $balance?->closing_balance_amt,
                'balance_date' => $balance?->date?->format('d M Y'),
                'monthly' => $months->map(function (CarbonImmutable $month, string $key) use ($plans, $party, $user, $payments, $ledger, $nextReviewerNames, $firstApprover) {
                    $plan = $plans->get($party->id)?->get($month->toDateString());
                    $approved = $plan?->approved_at !== null || ($plan?->planned_amount ?? 0) > 0;
                    $pending = ! $approved && $plan?->submitted_by !== null && (int) $plan->submitted_by === (int) $party->user_id;
                    $forwarded = $pending && $plan->next_reviewer_id !== null;
                    $ownSubmission = $pending && (int) $party->user_id === (int) $user->id;

                    return [
                        'planned' => $plan?->planned_amount ?? '0.00',
                        'actual' => (float) ($payments->get($ledger)?->get($key) ?? 0),
                        'approved' => $approved,
                        'forwarded' => $forwarded,
                        'forwarded_amount' => $forwarded ? $plan->pending_amount : null,
                        'pending_approver' => $forwarded ? $nextReviewerNames->get($plan->next_reviewer_id) : $firstApprover,
                        'pending' => $pending,
                        'own_pending_amount' => $ownSubmission ? $plan->pending_amount : null,
                    ];
                }),
            ];
        })->keyBy('id');
    }

    private function totals(Collection $rows, Collection $months): array
    {
        return [
            'closing' => $rows->sum('closing'),
            'monthly' => $months->map(fn (CarbonImmutable $month, string $key) => [
                'planned' => $rows->sum("monthly.$key.planned"),
                'actual' => $rows->sum("monthly.$key.actual"),
            ]),
        ];
    }
}
