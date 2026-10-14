<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\TallyPartySync;
use App\Models\TallyPartywisePaymentCredit;
use App\Models\TallySalesBill;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PartyMonthlyCollectionReportController extends Controller
{
    public function employees(Request $request)
    {
        return $this->report($request, 'employee');
    }

    public function states(Request $request)
    {
        return $this->report($request, 'state');
    }

    private function report(Request $request, string $groupBy)
    {
        $validated = $request->validate([
            'financial_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();
        $isAdmin = in_array($roleName, ['master_admin', 'sub_admin'], true);
        $employeeIds = \App\Models\User::where('reporting_to', $user->id)->pluck('id')->push($user->id)->unique();

        $customers = Customer::query()
            ->with(['user:id,name,state_id', 'user.state:id,name', 'state:id,name'])
            ->where('type', 'web')
            ->where('is_active', 1)
            ->when(! $isAdmin, fn ($query) => $query->whereIn('user_id', $employeeIds))
            ->get(['id', 'user_id', 'party_code', 'agro_name', 'state_id']);

        $syncByCode = TallyPartySync::query()
            ->whereIn('master_id', $customers->pluck('party_code')->filter()->unique())
            ->get(['master_id', 'party_name'])
            ->keyBy('master_id');

        $salesMin = TallySalesBill::min('invoice_date');
        $salesMax = TallySalesBill::max('invoice_date');
        $paymentsMin = TallyPartywisePaymentCredit::min('payment_date');
        $paymentsMax = TallyPartywisePaymentCredit::max('payment_date');
        $dateValues = collect([$salesMin, $salesMax, $paymentsMin, $paymentsMax])->filter();
        $defaultYear = $dateValues->isNotEmpty()
            ? $this->financialYearStart(Carbon::parse($dateValues->max()))
            : (now()->month >= 4 ? now()->year : now()->year - 1);
        $financialYear = (int) ($validated['financial_year'] ?? $defaultYear);

        $start = Carbon::create($financialYear, 4, 1)->startOfDay();
        $end = Carbon::create($financialYear + 1, 3, 31)->endOfDay();
        $months = collect(range(0, 11))->map(fn (int $offset) => $start->copy()->addMonths($offset));
        $monthKeys = $months->map(fn (Carbon $month) => $month->format('Y-m'));

        $minDate = $dateValues->min();
        $maxDate = $dateValues->max();
        $minYear = $minDate ? $this->financialYearStart(Carbon::parse($minDate)) : $financialYear;
        $maxYear = $maxDate ? $this->financialYearStart(Carbon::parse($maxDate)) : $financialYear;
        $minYear = min($minYear, $financialYear);
        $maxYear = max($maxYear, $financialYear);
        $financialYears = collect(range($maxYear, $minYear))->values();

        $aliases = [];
        $rows = [];
        foreach ($customers as $customer) {
            $tallyParty = $syncByCode->get($customer->party_code);
            $employeeId = $customer->user_id ?: 0;
            $employeeName = $customer->user?->name ?: 'Unassigned';
            $stateName = $customer->state?->name ?: $customer->user?->state?->name ?: 'Unassigned';
            $key = $groupBy === 'employee'
                ? (string) $employeeId
                : $this->normalizePartyKey(preg_replace('/\s+/', ' ', $stateName));

            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'name' => $groupBy === 'employee' ? $employeeName : $stateName,
                    'state' => $groupBy === 'employee' ? $stateName : null,
                    'monthly' => $monthKeys->mapWithKeys(fn (string $month) => [
                        $month => ['debit' => 0.0, 'payment' => 0.0],
                    ])->all(),
                ];
            }

            $partyName = $tallyParty?->party_name ?: $customer->agro_name;
            foreach ([$customer->party_code, $tallyParty?->party_name, $customer->agro_name] as $alias) {
                $normalizedAlias = $this->normalizePartyKey($alias);
                if ($normalizedAlias !== '' && ! isset($aliases[$normalizedAlias])) {
                    $aliases[$normalizedAlias] = $key;
                }
            }
            // Tally sales bills are normally keyed by the synchronized party name.
            $normalizedName = $this->normalizePartyKey($partyName);
            if ($normalizedName !== '' && ! isset($aliases[$normalizedName])) {
                $aliases[$normalizedName] = $key;
            }
        }

        $sales = TallySalesBill::query()
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("party_name, DATE_FORMAT(invoice_date, '%Y-%m') as month_key, SUM(grand_total) as total_amount")
            ->groupBy('party_name', 'month_key')
            ->get();
        foreach ($sales as $sale) {
            $key = $aliases[$this->normalizePartyKey($sale->party_name)] ?? null;
            if ($key !== null && isset($rows[$key]['monthly'][$sale->month_key])) {
                $rows[$key]['monthly'][$sale->month_key]['debit'] += (float) $sale->total_amount;
            }
        }

        $payments = TallyPartywisePaymentCredit::query()
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("party_name, DATE_FORMAT(payment_date, '%Y-%m') as month_key, SUM(credit_amount) as total_amount")
            ->groupBy('party_name', 'month_key')
            ->get();
        foreach ($payments as $payment) {
            $key = $aliases[$this->normalizePartyKey($payment->party_name)] ?? null;
            if ($key !== null && isset($rows[$key]['monthly'][$payment->month_key])) {
                $rows[$key]['monthly'][$payment->month_key]['payment'] += (float) $payment->total_amount;
            }
        }

        $rows = collect($rows)->sortBy('name')->values();
        $title = $groupBy === 'employee' ? 'Monthly Employee Debit / Credit' : 'Monthly State Debit / Credit';

        return view('admin.party.monthly-debit-credit', compact(
            'rows', 'months', 'financialYear', 'financialYears', 'title', 'groupBy'
        ));
    }

    private function normalizePartyKey(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function financialYearStart(Carbon $date): int
    {
        return $date->month >= 4 ? $date->year : $date->year - 1;
    }
}
