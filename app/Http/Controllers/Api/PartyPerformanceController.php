<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PartyVisit;
use App\Models\State;
use App\Models\TallyOpeningClosing;
use App\Models\TallyPartySync;
use App\Models\TallyPartywisePaymentCredit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PartyPerformanceController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'state_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
            'party_id' => 'nullable|integer',
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user.',
            ], 401);
        }

        $isAdmin = $user->hasAnyRole(['master_admin', 'sub_admin']);
        $stateId = $isAdmin ? ($validated['state_id'] ?? null) : $user->state_id;
        $employeeId = $isAdmin ? ($validated['employee_id'] ?? null) : $user->id;

        $partiesQuery = Customer::query()
            ->with([
                'state:id,name',
                'user:id,name,state_id',
                'user.state:id,name',
            ])
            ->whereNotNull('party_code')
            ->when($stateId, function (Builder $query, int $stateId) {
                $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('state_id', $stateId));
            })
            ->when($employeeId, fn (Builder $query, int $employeeId) => $query->where('user_id', $employeeId))
            ->when($validated['party_id'] ?? null, fn (Builder $query, int $partyId) => $query->whereKey($partyId))
            ->orderBy('agro_name');

        $parties = $partiesQuery->get([
            'id',
            'user_id',
            'party_code',
            'agro_name',
            'contact_person_name',
            'address',
            'city',
            'phone',
            'mobil_no_2',
            'state_id',
            'party_active_since',
            'credit_limit',
        ]);

        $tallyParties = TallyPartySync::query()
            ->whereIn('master_id', $parties->pluck('party_code')->filter())
            ->get()
            ->keyBy('master_id');

        $partyNames = $parties->map(function (Customer $party) use ($tallyParties) {
            return $tallyParties->get($party->party_code)?->party_name ?: $party->agro_name;
        })->filter()->unique()->values();

        $balanceRecords = TallyOpeningClosing::query()
            ->whereIn('party_name', $partyNames)
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->groupBy('party_name');

        [$financialYearStart, $financialYearEnd] = $this->currentFinancialYearDates();
        $previousFinancialYearEnd = $financialYearStart->copy()->subDay();
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();
        $partyIds = $parties->pluck('id');

        $visits = PartyVisit::query()
            ->whereIn('customer_id', $partyIds)
            ->whereBetween('visited_date', [$financialYearStart->toDateString(), $financialYearEnd->toDateString()])
            ->get(['customer_id', 'visited_date'])
            ->groupBy('customer_id');

        $orders = Order::query()
            ->whereIn('party_id', $partyIds)
            ->whereBetween('created_at', [$financialYearStart, $financialYearEnd])
            ->get(['party_id', 'created_at'])
            ->groupBy('party_id');

        $currentYearCredits = TallyPartywisePaymentCredit::query()
            ->whereIn('party_name', $partyNames)
            ->whereBetween('payment_date', [$financialYearStart->toDateString(), $financialYearEnd->toDateString()])
            ->selectRaw('party_name, SUM(credit_amount) as total_credit')
            ->groupBy('party_name')
            ->pluck('total_credit', 'party_name');

        $items = $parties->map(function (Customer $party) use (
            $tallyParties,
            $balanceRecords,
            $visits,
            $orders,
            $currentYearCredits,
            $financialYearStart,
            $financialYearEnd,
            $previousFinancialYearEnd,
            $currentMonthStart,
            $currentMonthEnd
        ) {
            $tallyParty = $tallyParties->get($party->party_code);
            $partyName = $tallyParty?->party_name ?: $party->agro_name;
            $joiningDate = $party->party_active_since ?? $tallyParty?->party_create_date;
            $partyBalances = $balanceRecords->get($partyName, collect());
            $latestBalance = $partyBalances->last();
            $previousClosingRecord = $partyBalances
                ->filter(fn (TallyOpeningClosing $balance) => $balance->date->lte($previousFinancialYearEnd))
                ->last();
            $currentYearBalances = $partyBalances
                ->filter(fn (TallyOpeningClosing $balance) => $balance->date->betweenIncluded($financialYearStart, $financialYearEnd));
            $currentOpeningRecord = $currentYearBalances->first();
            $currentClosingRecord = $currentYearBalances->last();
            $partyVisits = $visits->get($party->id, collect());
            $partyOrders = $orders->get($party->id, collect());

            return [
                'party_id' => $party->id,
                'party_code' => $party->party_code,
                'party_name' => $partyName,
                'employee_id' => $party->user_id,
                'employee_name' => $party->user?->name,
                'state_id' => $party->user?->state_id,
                'state_name' => $party->user?->state?->name ?? $party->state?->name ?? $tallyParty?->state,
                'contact_person_name' => $party->contact_person_name ?: $tallyParty?->contact_person_name,
                'address' => $party->address ?: $tallyParty?->address,
                'city' => $party->city,
                'mobile_1' => $party->phone ?: $tallyParty?->phone_1,
                'mobile_2' => $party->mobil_no_2 ?: $tallyParty?->phone_2,
                'joining_date' => $joiningDate?->format('Y-m-d'),
                'joining_year' => $joiningDate?->format('Y'),
                'credit_limit' => (float) ($party->credit_limit ?: $tallyParty?->credit_limit ?: 0),
                'receipt' => [
                    'opening' => $this->balanceValue($latestBalance?->opening_balance_amt),
                    'credit' => $this->balanceValue($latestBalance?->credit_amt),
                    'closing' => $this->balanceValue($latestBalance?->closing_balance_amt),
                    'as_on_date' => $latestBalance?->date?->format('Y-m-d'),
                ],
                'performance' => [
                    'financial_year' => $financialYearStart->format('Y').'-'.substr($financialYearEnd->format('Y'), -2),
                    'count_type' => 'Monthly/Yearly',
                    'visit_count' => [
                        'monthly' => $partyVisits->filter(fn (PartyVisit $visit) => $visit->visited_date->betweenIncluded($currentMonthStart, $currentMonthEnd))->count(),
                        'yearly' => $partyVisits->count(),
                    ],
                    'order_count' => [
                        'monthly' => $partyOrders->filter(fn (Order $order) => $order->created_at->betweenIncluded($currentMonthStart, $currentMonthEnd))->count(),
                        'yearly' => $partyOrders->count(),
                    ],
                    'previous_year_closing' => $this->balanceValue($previousClosingRecord?->closing_balance_amt),
                    'current_year_opening' => $this->balanceValue($currentOpeningRecord?->opening_balance_amt),
                    'current_year_credit' => $this->balanceValue($currentYearCredits->get($partyName, 0)),
                    'current_year_closing' => $this->balanceValue($currentClosingRecord?->closing_balance_amt),
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'selected_filters' => [
                    'state_id' => $stateId,
                    'employee_id' => $employeeId,
                    'party_id' => $validated['party_id'] ?? null,
                ],
                'filters' => [
                    'states' => $this->states($isAdmin, $user->state_id),
                    'employees' => $this->employees($isAdmin, $user->id, $stateId),
                    'parties' => $this->partyOptions($isAdmin, $user->id, $stateId, $employeeId),
                ],
                'summary' => [
                    'total_parties' => $items->count(),
                ],
                'items' => $items,
            ],
        ]);
    }

    private function states(bool $isAdmin, ?int $userStateId)
    {
        return State::query()
            ->where('status', 1)
            ->when(! $isAdmin, fn (Builder $query) => $query->whereKey($userStateId))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function employees(bool $isAdmin, int $userId, ?int $stateId)
    {
        return User::query()
            ->where('status', 'Active')
            ->where('is_active', 1)
            ->when(! $isAdmin, fn (Builder $query) => $query->whereKey($userId))
            ->when($stateId, fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->where(function (Builder $query) {
                $query->whereNull('user_level')
                    ->orWhereNotIn('user_level', ['master_admin', 'sub_admin']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'state_id']);
    }

    private function partyOptions(bool $isAdmin, int $userId, ?int $stateId, ?int $employeeId)
    {
        return Customer::query()
            ->whereNotNull('party_code')
            ->when(! $isAdmin, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($stateId, function (Builder $query, int $stateId) {
                $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('state_id', $stateId));
            })
            ->when($employeeId, fn (Builder $query, int $employeeId) => $query->where('user_id', $employeeId))
            ->orderBy('agro_name')
            ->get(['id', 'agro_name', 'party_code', 'user_id']);
    }

    private function currentFinancialYearDates(): array
    {
        $now = Carbon::now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;

        return [
            Carbon::create($startYear, 4, 1)->startOfDay(),
            Carbon::create($startYear + 1, 3, 31)->endOfDay(),
        ];
    }

    private function balanceValue($value): array
    {
        $amount = (float) ($value ?? 0);

        return [
            'amount' => abs($amount),
            'type' => $amount < 0 ? 'Cr' : 'Dr',
            'formatted' => $this->formatAmount(abs($amount)).' '.($amount < 0 ? 'Cr' : 'Dr'),
        ];
    }

    private function formatAmount(float $amount): string
    {
        return preg_replace('/\.00$/', '', number_format($amount, 2, '.', ','));
    }
}
