<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Budget;
use App\Models\BudgetLog;
use App\Models\User;
use App\Models\State;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\TallyPartySync;
use App\Models\TallySalesBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $financial_year = $request->input('financial_year', date('Y') . '-' . (date('y') + 1));
        $state_id = $request->input('state_id');
        $employee_id = $request->input('employee_id');

        $filters = $this->getRoleBasedStateAndEmployeeFilters();
        extract($filters);

        $budgets = Budget::with(['user', 'state']);
        if ($financial_year) $budgets->where('financial_year', $financial_year);
        if ($state_id) $budgets->where('state_id', $state_id);
        if ($employee_id) $budgets->where('user_id', $employee_id);

        $budgets = $budgets->get();

        $months = ['april' => 4, 'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8, 'september' => 9,
            'october' => 10, 'november' => 11, 'december' => 12, 'january' => 1, 'february' => 2, 'march' => 3
        ];

        $years = explode('-', $financial_year);
        $startYear = $years[0];
        $endYear = count($years) > 1 ? '20' . $years[1] : $years[0] + 1;

        // --- Pre-load Tally data in bulk (avoids N+1 queries) ---

        // 1. Collect all unique user IDs from budget rows
        $userIds = $budgets->pluck('user_id')->unique()->toArray();

        // 2. Load all active web-type customers for these users, grouped by user_id
        $allCustomers = Customer::where('type', 'web')
            ->where('is_active', 1)
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        // 3. Load TallyPartySync keyed by master_id and by party_name (same as Party Performance)
        $tallyParties = TallyPartySync::get();
        $partiesByCode = $tallyParties->keyBy('master_id');
        $partiesByName = $tallyParties->keyBy('party_name');

        // 4. Build YYYY-MM key for each month in the financial year
        $monthYearMap = [];
        foreach ($months as $monthName => $monthNum) {
            $year = ($monthNum >= 4) ? $startYear : $endYear;
            $monthYearMap[$monthName] = sprintf('%04d-%02d', $year, $monthNum);
        }

        // 5. Load TallySalesBill totals for all FY months in one query
        //    grouped by party_name -> YYYY-MM
        $allYearMonths = array_values($monthYearMap);
        $salesData = TallySalesBill::selectRaw('party_name, DATE_FORMAT(invoice_date, "%Y-%m") as ym, SUM(grand_total) as total_amount')
            ->whereIn(DB::raw('DATE_FORMAT(invoice_date, "%Y-%m")'), $allYearMonths)
            ->groupBy('party_name', 'ym')
            ->get()
            ->groupBy('party_name')
            ->map(function ($items) {
                return $items->keyBy('ym');
            });

        // --- Calculate achievements from Tally Sales Bills ---

        foreach ($budgets as $budget) {
            $achievements = [];

            // Resolve Tally party names for this user's customers
            $userCustomers = $allCustomers->get($budget->user_id, collect());
            $tallyPartyNames = [];
            foreach ($userCustomers as $customer) {
                $party = $customer->party_code ? $partiesByCode->get($customer->party_code) : null;
                if (!$party) {
                    $party = $partiesByName->get($customer->agro_name);
                }
                if ($party) {
                    $tallyPartyNames[] = $party->party_name;
                }
            }

            // Sum sales for each month from pre-loaded data
            foreach ($months as $monthName => $monthNum) {
                $ym = $monthYearMap[$monthName];
                $achive = 0;
                foreach ($tallyPartyNames as $partyName) {
                    if (isset($salesData[$partyName]) && isset($salesData[$partyName][$ym])) {
                        $achive += $salesData[$partyName][$ym]->total_amount;
                    }
                }
                $achievements[$monthName] = $achive;
            }

            $budget->achievements = $achievements;
        }

        $monthList = array_keys($months);

        return view('admin.budget.index', compact(
            'financial_year', 'state_id', 'employee_id',
            'employees', 'states', 'budgets', 'months', 'monthList'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'state_id' => 'required',
            'financial_year' => 'required',
            'total_target' => 'nullable|numeric',
            'monthly_targets' => 'nullable|array',
        ]);

        $targets = $request->monthly_targets ?? [];
        $sumMonthly = array_sum($targets);
        $totalTarget = $request->total_target ?? $sumMonthly;

        $monthList = ['april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march'];

        if (($sumMonthly == 0) && $totalTarget > 0) {
            $monthlyValue = round($totalTarget / 12, 2);
            foreach ($monthList as $m) {
                $targets[$m] = $monthlyValue;
            }
            $targets['march'] = $totalTarget - ($monthlyValue * 11);
        } else {
            $totalTarget = $sumMonthly;
        }

        // Get existing budget to compare
        $existingBudget = Budget::where([
            'user_id' => $request->user_id,
            'state_id' => $request->state_id,
            'financial_year' => $request->financial_year,
        ])->first();

        $budget = Budget::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'state_id' => $request->state_id,
                'financial_year' => $request->financial_year,
            ],
            array_merge($targets, ['total_target' => $totalTarget])
        );

        // Record Logs
        foreach ($monthList as $month) {
            $oldValue = $existingBudget ? ($existingBudget->$month ?? 0) : 0;
            $newValue = isset($targets[$month]) ? $targets[$month] : 0;

            if (round($oldValue, 2) != round($newValue, 2)) {
                BudgetLog::create([
                    'budget_id' => $budget->id,
                    'user_id' => $request->user_id,
                    'admin_id' => auth()->id(),
                    'financial_year' => $request->financial_year,
                    'month' => $month,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Budget target set successfully.');
    }

    public function getLogs(Request $request)
    {
        $logs = BudgetLog::with(['admin'])
            ->where('user_id', $request->user_id)
            ->where('financial_year', $request->financial_year)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($log) {
                return [
                    'admin_name' => $log->admin->name ?? 'System',
                    'month' => ucfirst($log->month),
                    'old_value' => number_format($log->old_value, 2),
                    'new_value' => number_format($log->new_value, 2),
                    'date' => $log->created_at->format('d-M-Y h:i A'),
                ];
            });

        return response()->json(['logs' => $logs]);
    }
    
    public function show($id)
    {
        if ($id == 'report') {
            return redirect()->route('budget.report');
        }
        abort(404);
    }

    public function report(Request $request)
    {
        $financial_year = $request->input('financial_year', date('Y') . '-' . (date('y') + 1));
        
        $filters = $this->getRoleBasedStateAndEmployeeFilters();
        extract($filters);

        $budgets = Budget::with(['state'])
            ->where('financial_year', $financial_year);
            
        if (!empty($stateIds)) {
            $budgets->whereIn('state_id', $stateIds);
        }

        $budgets = $budgets->get();
        
        $months = [
            'april' => 4, 'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8, 'september' => 9,
            'october' => 10, 'november' => 11, 'december' => 12, 'january' => 1, 'february' => 2, 'march' => 3
        ];
        $monthList = array_keys($months);

        $stateReport = [];
        $years = explode('-', $financial_year);
        $startYear = $years[0];
        $endYear = count($years) > 1 ? '20' . $years[1] : $years[0] + 1;

        foreach ($budgets as $budget) {
            $stateId = $budget->state_id;
            $stateName = $budget->state->name ?? 'Unknown';

            if (!isset($stateReport[$stateId])) {
                $stateReport[$stateId] = [
                    'name'                 => $stateName,
                    'total_target'         => 0,
                    'monthly_targets'      => array_fill_keys($monthList, 0),
                    'monthly_achievements' => array_fill_keys($monthList, 0),
                ];
            }

            $stateReport[$stateId]['total_target'] += $budget->total_target;
            foreach ($monthList as $m) {
                $stateReport[$stateId]['monthly_targets'][$m] += $budget->$m ?? 0;
            }
        }

        // --- Pre-load Tally data in bulk for achievement calculation ---

        // 1. Collect all unique user IDs from budgets
        $userIds = $budgets->pluck('user_id')->unique()->toArray();

        // 2. Load customers grouped by user_id
        $allCustomers = Customer::where('type', 'web')
            ->where('is_active', 1)
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        // 3. TallyPartySync keyed by master_id and party_name
        $tallyParties = TallyPartySync::get();
        $partiesByCode = $tallyParties->keyBy('master_id');
        $partiesByName = $tallyParties->keyBy('party_name');

        // 4. Build YYYY-MM key for each month in the financial year
        $monthYearMap = [];
        foreach ($months as $monthName => $monthNum) {
            $year = ($monthNum >= 4) ? $startYear : $endYear;
            $monthYearMap[$monthName] = sprintf('%04d-%02d', $year, $monthNum);
        }

        // 5. Load TallySalesBill totals for all FY months in one query
        $allYearMonths = array_values($monthYearMap);
        $salesData = TallySalesBill::selectRaw('party_name, DATE_FORMAT(invoice_date, "%Y-%m") as ym, SUM(grand_total) as total_amount')
            ->whereIn(DB::raw('DATE_FORMAT(invoice_date, "%Y-%m")'), $allYearMonths)
            ->groupBy('party_name', 'ym')
            ->get()
            ->groupBy('party_name')
            ->map(function ($items) {
                return $items->keyBy('ym');
            });

        // 6. Accumulate achievements per state using pre-loaded data
        foreach ($budgets as $budget) {
            $stateId = $budget->state_id;

            // Resolve Tally party names for this user's customers
            $userCustomers = $allCustomers->get($budget->user_id, collect());
            $tallyPartyNames = [];
            foreach ($userCustomers as $customer) {
                $party = $customer->party_code ? $partiesByCode->get($customer->party_code) : null;
                if (!$party) {
                    $party = $partiesByName->get($customer->agro_name);
                }
                if ($party) {
                    $tallyPartyNames[] = $party->party_name;
                }
            }

            foreach ($monthList as $m) {
                $ym     = $monthYearMap[$m];
                $achive = 0;
                foreach ($tallyPartyNames as $partyName) {
                    if (isset($salesData[$partyName]) && isset($salesData[$partyName][$ym])) {
                        $achive += $salesData[$partyName][$ym]->total_amount;
                    }
                }
                $stateReport[$stateId]['monthly_achievements'][$m] += $achive;
            }
        }

        // 7. Compute overall totals across all states
        $overallTargets      = array_fill_keys($monthList, 0);
        $overallAchievements = array_fill_keys($monthList, 0);
        $overallTotalTarget  = 0;
        foreach ($stateReport as $data) {
            $overallTotalTarget += $data['total_target'];
            foreach ($monthList as $m) {
                $overallTargets[$m]      += $data['monthly_targets'][$m];
                $overallAchievements[$m] += $data['monthly_achievements'][$m];
            }
        }

        return view('admin.budget.report', compact(
            'stateReport', 'financial_year', 'months', 'monthList', 'states',
            'overallTargets', 'overallAchievements', 'overallTotalTarget'
        ));
    }
}
