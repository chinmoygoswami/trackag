<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\PartyVisit;
use App\Models\State;
use App\Models\User;
use App\Models\UserStateAccess;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PartyController extends Controller
{
    public function __construct()
    {
        // $this->middleware('permission:view_party_visit')->only(['index','show']);
        // $this->middleware('permission:view_new_party')->only(['newPartyList']);
        // $this->middleware('permission:edit_party_visit')->only(['edit','update']);
        // $this->middleware('permission:delete_party_visit')->only(['destroy']);
    }

    public function index()
    {
        $filters = $this->getRoleBasedStateAndEmployeeFilters();
        extract($filters);
        return view('admin.party.index',compact('states','employees','company'));

    }

    public function getPartyVisits(Request $request)
    {
        $user     = auth()->user();
        $roleName = $user->getRoleNames()->first();
        $type      = $request->get('type', 'daily'); // daily OR monthly
        $userId    = $request->get('user_id');
        $fromDate  = $request->get('from_date');
        $toDate    = $request->get('to_date');
        $agroName  = $request->get('agro_name');

        $query = PartyVisit::with(['customer', 'user'])->whereNotNull('check_in_time')->whereNotNull('check_out_time');

        if (!in_array($roleName, ['master_admin', 'sub_admin'])) {

            $userStateAccess = UserStateAccess::where('user_id', $user->id)->first();
            $stateIds = $userStateAccess->state_ids ?? [];

            if (empty($stateIds)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            // ✅ Filter via user relation
            $query->whereHas('user', function ($q) use ($user, $stateIds) {
                $q->whereIn('state_id', $stateIds)
                ->where('reporting_to', $user->id);
            });
        }
        // FILTER : Employee
        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }
        $today = now()->toDateString();
        if(empty($fromDate) && empty($toDate) && $type == "daily"){
            $fromDate = $today;
            $toDate   = $today;
        }

        // FILTER : Date
        if ($fromDate && $toDate) {
            $query->whereBetween('visited_date', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $query->whereDate('visited_date', '>=', $fromDate);
        } elseif ($toDate) {
            $query->whereDate('visited_date', '<=', $toDate);
        }

        // FILTER : Agro name
        if ($agroName) {
            $query->whereHas('customer', function ($q) use ($agroName) {
                $q->where('agro_name', 'LIKE', "%$agroName%");
            });
        }

        // -----------------------------------------------------
        // DAILY API RESPONSE
        // -----------------------------------------------------
        if ($type === 'daily') {

            $data = $query->orderByDesc('visited_date')->get()->map(function ($v) {

                // Calculate duration
                $duration = '-';
                if ($v->check_in_time && $v->check_out_time) {
                    $d = \Carbon\Carbon::parse($v->check_in_time)
                        ->diffInMinutes(\Carbon\Carbon::parse($v->check_out_time));

                    $duration = floor($d / 60) . "h " . ($d % 60) . "m";
                }

                return [
                    'id'                    => $v->id,
                    'visited_date'          => $v->visited_date ? $v->visited_date->format('d-m-Y') : null,
                    'employee_name'         => $v->user->name ?? '-',
                    'agro_name'             => $v->customer->agro_name ?? '-',
                    'check_in_out_duration' => $duration,
                    'visit_purpose'         => $v->visit_purpose ?? '-',
                    'followup_date'         => $v->followup_date ? $v->followup_date->format('d-m-Y') : '-',
                    'agro_visit_image'      => $v->agro_visit_image ? asset('storage/' . $v->agro_visit_image) : null,
                    'remarks'               => $v->remarks ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data
            ]);
        }

        // -----------------------------------------------------
        // MONTHLY API RESPONSE
        // -----------------------------------------------------
        $data = $query->get()
        ->groupBy('customer_id')
        ->map(function ($group) {

            $lastVisit = $group->sortByDesc('visited_date')->first();

            // Purpose Wise Count FIXED
            $purposeDetails = $group->groupBy('visit_purpose')->map(function ($rows, $purposeName) {

                return [
                    'purpose_name' => $purposeName ?? '-',
                    'count' => $rows->count(),
                ];
            })->values();

            return [
                'shop_name'         => $lastVisit->customer->agro_name ?? '-',
                'employee_name'     => $lastVisit->user->name ?? '-',
                'visit_count'       => $group->count(),
                'last_visit_date'   => $lastVisit->visited_date ? $lastVisit->visited_date->format('d-m-Y') : '-',
                'visit_purpose_count' => $purposeDetails,
            ];
        })
        ->values();


        return response()->json([
            'success' => true,
            'data'    => $data
        ]);
    }
    
    public function getEmployeesByState(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();
        $stateId = $request->state_id;

        // ✅ Master / Sub admin → all employees
        if (in_array($roleName, ['master_admin', 'sub_admin'])) {

            $employees = User::where('status', 'Active')->where('id', '!=', 1)
                ->when($stateId && $stateId !== 'all', function ($q) use ($stateId) {
                    $q->where('state_id', $stateId);
                })
                ->select('id', 'name')
                ->get();

            return response()->json($employees);
        }

        $userStateAccess = UserStateAccess::where('user_id', $user->id)->first();
        $stateIds = $userStateAccess->state_ids ?? [];

        if (empty($stateIds)) {
            return response()->json([]);
        }

        $employees = User::where('status', 'Active')->where('id', '!=', 1)
            ->whereIn('state_id', $stateIds)
            ->where('reporting_to', $user->id)
            ->when($stateId && $stateId !== 'all', function ($q) use ($stateId) {
                $q->where('state_id', $stateId);
            })
            ->select('id', 'name')
            ->get();

        return response()->json($employees);
    }

    
    public function newPartyList(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();
        
        $stateIds = [];
        $userStateAccess = UserStateAccess::where('user_id', $user->id)->first();
        if ($userStateAccess && !empty($userStateAccess->state_ids)) {
            $stateIds = $userStateAccess->state_ids;
        }

        if (in_array($roleName, ['master_admin', 'sub_admin'])) {
            $users = User::where('status', 'Active')->where('id', '!=', 1)->get();
        } else {
            $users = empty($stateIds)
                ? collect()
                : User::where('status', 'Active')->where('id', '!=', 1)
                    ->whereIn('state_id', $stateIds)
                    ->where('reporting_to', $user->id)
                    ->get();
        }

        $companyCount = Company::count();
        $company = null;

        if ($companyCount == 1) {
            $company = Company::first();
            if ($company && !empty($company->state)) {
                $companyStates = array_map('intval', explode(',', $company->state));

                if ($roleName === 'sub_admin') {
                    $states = State::where('status', 1)
                        ->whereIn('id', $companyStates)
                        ->get();
                } else {
                    $states = empty($stateIds)
                        ? collect()
                        : State::where('status', 1)
                            ->whereIn('id', $stateIds)
                            ->get();
                }

            } else {
                $states = in_array($roleName, ['master_admin', 'sub_admin'])
                    ? State::where('status', 1)->get()
                    : (empty($stateIds) ? collect() : State::where('status', 1)->whereIn('id', $stateIds)->get());
            }

        } else {
            $states = in_array($roleName, ['master_admin', 'sub_admin'])
                ? State::where('status', 1)->get()
                : (empty($stateIds) ? collect() : State::where('status', 1)->whereIn('id', $stateIds)->get());
        }

        $query = Customer::with('user')
            ->where('is_active', 1)
            ->where('type', 'mobile');

        if (!in_array($roleName, ['master_admin', 'sub_admin'])) {

            if (empty($stateIds)) {
                $customer = collect();

                return view('admin.new-party.index', compact('customer', 'users', 'states', 'company'));
            }

            $query->whereHas('user', function ($q) use ($user, $stateIds) {
                $q->whereIn('state_id', $stateIds)
                ->where('reporting_to', $user->id);
            });
        }

        if ($request->financial_year) {
            $dates = explode('-', $request->financial_year);
            $query->whereYear('visit_date', '>=', $dates[0])
                ->whereYear('visit_date', '<=', $dates[1]);
        }

        if ($request->from_date) {
            $query->whereDate('visit_date', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('visit_date', '<=', $request->to_date);
        }

        if ($request->state_id) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('state_id', $request->state_id);
            });
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->agro_name) {
            $query->where('agro_name', 'LIKE', '%' . $request->agro_name . '%');
        }

        $customer = $query->orderBy('visit_date', 'desc')->get();

        return view('admin.new-party.index', compact('customer', 'users', 'states', 'company'));
    }

    public function updateParty(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'agro_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
        ]);

        Customer::where('id', $request->id)->update([
            'agro_name' => $request->agro_name,
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        return back()->with('success', 'Party updated successfully');
    }

    public function deleteParty($id)
    {
        $customer = Customer::findOrFail($id);

        $customer->delete();

        return back()->with('success', 'Party deleted successfully');
    }

    public function newPartyPdf(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();

        $stateIds = [];
        $userStateAccess = UserStateAccess::where('user_id', $user->id)->first();
        if ($userStateAccess && !empty($userStateAccess->state_ids)) {
            $stateIds = $userStateAccess->state_ids;
        }

        $query = Customer::with('user')
            ->where('is_active', 1)
            ->where('type', 'mobile');

        /* 🔐 ROLE & STATE ACCESS */
        if (!in_array($roleName, ['master_admin', 'sub_admin'])) {
            if (!empty($stateIds)) {
                $query->whereHas('user', function ($q) use ($user, $stateIds) {
                    $q->whereIn('state_id', $stateIds)
                    ->where('reporting_to', $user->id);
                });
            } else {
                $query->whereRaw('1 = 0'); // no data
            }
        }

        /* 📅 FILTERS (same as list page) */
        if ($request->financial_year) {
            $dates = explode('-', $request->financial_year);
            $query->whereYear('visit_date', '>=', $dates[0])
                ->whereYear('visit_date', '<=', $dates[1]);
        }

        if ($request->from_date && $request->to_date) {
            $query->whereBetween('visit_date', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay(),
            ]);
        } elseif ($request->from_date) {
            $query->whereDate('visit_date', '>=', $request->from_date);
        } elseif ($request->to_date) {
            $query->whereDate('visit_date', '<=', $request->to_date);
        }

        if ($request->state_id) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('state_id', $request->state_id);
            });
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->agro_name) {
            $query->where('agro_name', 'LIKE', '%' . $request->agro_name . '%');
        }

        $customer = $query->orderBy('visit_date', 'desc')->get();

        $pdf = Pdf::loadView('admin.new-party.pdf', compact('customer'))
            ->setPaper('A4', 'landscape');

        return $pdf->download('new-party-list.pdf');
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer',
            'status' => 'required|string',
            'remark' => $request->status == 'approved' ? 'nullable|string' : 'required|string',
        ]);

        $customer = Customer::with('user')->findOrFail($request->customer_id);

        $customer->status = $request->status;
        $customer->remarks = $request->remark;
        $customer->save();

        $this->sendNewPartyStatusNotification($customer);

        return back()->with('success', 'Status updated successfully!');
    }

    private function sendNewPartyStatusNotification(Customer $customer): void
    {
        try {
            $customer->loadMissing('user');

            if (!$customer->user || empty($customer->user->fcm_token)) {
                return;
            }

            $firebaseService = app(\App\Services\FirebaseService::class);
            $partyName = $customer->agro_name ?? 'Your party';
            $statusText = ucfirst($customer->status);
            $title = "{$partyName} {$statusText}";

            $message = "Your new party status has been updated to {$customer->status}.";
            if ($customer->status === 'approved') {
                $message = "{$partyName} has been approved.";
            } elseif ($customer->status === 'rejected') {
                $message = "{$partyName} has been rejected.";
            } elseif ($customer->status === 'hold') {
                $message = "{$partyName} has been placed on hold.";
            }

            $firebaseService->sendNotification($customer->user->fcm_token, $title, $message, [
                'type' => 'new_party_status',
                'party_id' => (string) $customer->id,
                'status' => $customer->status,
                'remark' => $customer->remarks ?? '',
            ], $customer->user->id);
        } catch (\Exception $e) {
        }
    }

    public function partyVisitReport()
    {
        $filters = $this->getRoleBasedStateAndEmployeeFilters();
        extract($filters);

        // Generate Financial Years for Dropdown (e.g., 2024-2025, 2025-2026, 2026-2027)
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('n');
        $startYear = $currentMonth >= 4 ? $currentYear : $currentYear - 1;
        
        $financialYears = [];
        for ($i = -2; $i <= 1; $i++) {
            $y = $startYear + $i;
            $financialYears[] = $y . '-' . ($y + 1);
        }

        $currentFinancialYear = $startYear . '-' . ($startYear + 1);

        return view('admin.party.report', compact('states', 'employees', 'financialYears', 'currentFinancialYear'));
    }

    public function getPartyVisitReportData(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();
        
        $stateId = $request->get('state_id');
        $employeeId = $request->get('employee_id');
        $financialYear = $request->get('financial_year');

        // Parse Financial Year
        if ($financialYear) {
            $parts = explode('-', $financialYear);
            $startYear = $parts[0];
            $endYear = $parts[1] ?? ($startYear + 1);
        } else {
            $currentMonth = (int)date('n');
            $startYear = $currentMonth >= 4 ? (int)date('Y') : (int)date('Y') - 1;
            $endYear = $startYear + 1;
        }

        $startDate = $startYear . '-04-01';
        $endDate = $endYear . '-03-31';

        $stateIds = [];
        if (!in_array($roleName, ['master_admin', 'sub_admin'])) {
            $userStateAccess = UserStateAccess::where('user_id', $user->id)->first();
            if ($userStateAccess && !empty($userStateAccess->state_ids)) {
                $stateIds = $userStateAccess->state_ids;
            } else {
                return response()->json(['data' => []]);
            }
        }

        // Fetch visible customers based on role/filters
        $customerQuery = Customer::with('user')->where('is_active', 1)->where('type', 'web');

        if (!in_array($roleName, ['master_admin', 'sub_admin'])) {
            $customerQuery->whereHas('user', function ($q) use ($user, $stateIds) {
                $q->whereIn('state_id', $stateIds)->where('reporting_to', $user->id);
            });
        }

        if ($stateId) {
            $customerQuery->whereHas('user', function ($q) use ($stateId) {
                $q->where('state_id', $stateId);
            });
        }

        if ($employeeId) {
            $customerQuery->where('user_id', $employeeId);
        }

        $customers = $customerQuery->orderBy('agro_name')->get();
        if ($customers->isEmpty()) {
            return response()->json(['data' => []]);
        }
        $customerIds = $customers->pluck('id')->toArray();

        // Fetch Visits
        $visits = PartyVisit::whereIn('customer_id', $customerIds)
            ->whereNotNull('check_in_time')
            ->whereBetween('visited_date', [$startDate, $endDate])
            ->get();

        // Build columns strictly based on FY
        $months = [];
        for ($i = 4; $i <= 12; $i++) {
            $months[] = ['year' => $startYear, 'month' => $i];
        }
        for ($i = 1; $i <= 3; $i++) {
            $months[] = ['year' => $endYear, 'month' => $i];
        }

        $data = [];
        foreach ($customers as $customer) {
            $customerVisits = $visits->where('customer_id', $customer->id);
            
            // Format months keys
            $monthData = [];
            foreach ($months as $idx => $m) {
                $count = $customerVisits->filter(function($v) use ($m) {
                    $d = Carbon::parse($v->visited_date);
                    return $d->year == $m['year'] && $d->month == $m['month'];
                })->count();
                
                $monthKey = Carbon::create($m['year'], $m['month'], 1)->format('M-2y'); // e.g. Apr-26
                $monthData['month_' . $idx] = [
                    'label' => $monthKey,
                    'count' => $count,
                    'year' => $m['year'],
                    'month' => $m['month']
                ];
            }

            $data[] = array_merge([
                'party_name' => $customer->agro_name,
                'employee_name' => $customer->user->name ?? '-',
                'customer_id' => $customer->id,
            ], $monthData);
        }

        // Return columns config and data row
        $columns = [
            ['data' => 'party_name', 'name' => 'party_name', 'title' => 'Party Name'],
            ['data' => 'employee_name', 'name' => 'employee_name', 'title' => 'Employee Name']
        ];
        
        foreach ($months as $idx => $m) {
            $monthKey = Carbon::create($m['year'], $m['month'], 1)->format('M-y'); // e.g. Apr-26
            $columns[] = [
                'data' => 'month_' . $idx,
                'name' => 'month_' . $idx,
                'title' => $monthKey
            ];
        }

        $targetObj = \App\Models\PartyVisitTarget::first();
        $target = $targetObj ? $targetObj->target : 0;

        return response()->json(['data' => $data, 'columns' => $columns, 'target' => $target]);
    }

    public function getPartyVisitDetails(Request $request)
    {
        $customerId = $request->get('customer_id');
        $year = $request->get('year');
        $month = $request->get('month');

        if (!$customerId || !$year || !$month) {
            return response()->json(['data' => []]);
        }

        $visits = PartyVisit::with(['customer', 'user'])
            ->where('customer_id', $customerId)
            ->whereNotNull('check_in_time')
            ->whereYear('visited_date', $year)
            ->whereMonth('visited_date', $month)
            ->orderBy('visited_date', 'asc')
            ->get();

        $data = $visits->map(function ($v) {
            return [
                'date' => $v->visited_date ? Carbon::parse($v->visited_date)->format('d-m-Y') : '-',
                'check_in' => $v->check_in_time ? Carbon::parse($v->check_in_time)->format('H:i') : '-',
                'check_out' => $v->check_out_time ? Carbon::parse($v->check_out_time)->format('H:i') : '-',
                'visit_purpose' => $v->visit_purpose ?? '-',
                'remarks' => $v->remarks ?? '-',
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function partyPerformance(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();
        $isMasterAdmin = in_array($roleName, ['master_admin']);

        $userIds = \App\Models\User::where('reporting_to', $user->id)->pluck('id')->toArray();
        $userIds[] = $user->id;

        $customerQuery = \App\Models\Customer::with(['user', 'state'])->whereNotNull('party_code');
        if (!$isMasterAdmin) {
            $customerQuery->whereIn('user_id', $userIds);
        }
        $customers = $customerQuery->get()->keyBy('party_code');

        $partiesQuery = \App\Models\TallyPartySync::orderBy('party_name');
        if (!$isMasterAdmin) {
            $partiesQuery->whereIn('master_id', $customers->keys());
        }
        $parties = $partiesQuery->get();
        
        $sales = \App\Models\TallySalesBill::selectRaw('party_name, YEAR(invoice_date) as year, MONTH(invoice_date) as month, SUM(grand_total) as total_amount, SUM(qty) as total_qty')
            ->groupBy('party_name', 'year', 'month')
            ->get();
            
        $payments = \App\Models\TallyPartywisePaymentCredit::selectRaw('party_name, YEAR(payment_date) as year, MONTH(payment_date) as month, SUM(credit_amount) as total_amount')
            ->groupBy('party_name', 'year', 'month')
            ->get();
            
        $balances = \App\Models\TallyOpeningClosing::orderBy('date', 'desc')
            ->get()
            ->groupBy('party_name')
            ->map(function($items) {
                return $items->first();
            });

        $monthKeys = collect();
        foreach ($sales as $s) {
            $monthKeys->push(sprintf('%04d-%02d', $s->year, $s->month));
        }
        foreach ($payments as $p) {
            $monthKeys->push(sprintf('%04d-%02d', $p->year, $p->month));
        }
        
        $maxYearMonth = $monthKeys->max();
        if ($maxYearMonth) {
            list($year, $month) = explode('-', $maxYearMonth);
            $fyStartYear = ((int)$month < 4) ? (int)$year - 1 : (int)$year;
        } else {
            $currentMonth = (int)date('n');
            $currentYear = (int)date('Y');
            $fyStartYear = ($currentMonth < 4) ? $currentYear - 1 : $currentYear;
        }

        $uniqueMonths = collect();
        for ($i = 0; $i < 12; $i++) {
            $monthDate = \Carbon\Carbon::createFromDate($fyStartYear, 4, 1)->addMonths($i);
            $uniqueMonths->push($monthDate->format('Y-m'));
        }
        
        $salesGrouped = $sales->groupBy('party_name')->map(function($items) {
            return $items->keyBy(function($i) { return sprintf('%04d-%02d', $i->year, $i->month); });
        });
        $paymentsGrouped = $payments->groupBy('party_name')->map(function($items) {
            return $items->keyBy(function($i) { return sprintf('%04d-%02d', $i->year, $i->month); });
        });
            
        $performanceData = $parties->map(function($party) use ($customers, $balances, $salesGrouped, $paymentsGrouped, $uniqueMonths) {
            $tallyName = $party->party_name;
            $customer = $customers->get($party->master_id);
            
            $displayName = $customer ? $customer->agro_name : $tallyName;
            $employeeName = $customer && $customer->user ? $customer->user->name : '-';
            $stateName = $customer && $customer->state ? $customer->state->name : $party->state;
            
            $b = $balances->get($tallyName);
            
            $monthlyData = [];
            $totalSales = 0;
            $totalPayment = 0;
            $totalQty = 0;

            foreach ($uniqueMonths as $ym) {
                $sAmt = isset($salesGrouped[$tallyName][$ym]) ? $salesGrouped[$tallyName][$ym]->total_amount : 0;
                $sQty = isset($salesGrouped[$tallyName][$ym]) ? $salesGrouped[$tallyName][$ym]->total_qty : 0;
                $pAmt = isset($paymentsGrouped[$tallyName][$ym]) ? $paymentsGrouped[$tallyName][$ym]->total_amount : 0;
                
                $monthlyData[$ym] = [
                    'debit' => $sAmt,
                    'credit' => $pAmt
                ];
                
                $totalSales += $sAmt;
                $totalPayment += $pAmt;
                $totalQty += $sQty;
            }
            
            return (object) [
                'master_id' => $party->master_id,
                'party_name' => $displayName,
                'employee_name' => $employeeName,
                'state' => $stateName,
                'opening_balance' => $b ? $b->opening_balance_amt : 0,
                'credit_amt' => $b ? $b->credit_amt : 0,
                'debit_amt' => $b ? $b->debit_amt : 0,
                'closing_balance' => $b ? $b->closing_balance_amt : 0,
                'total_qty' => $totalQty,
                'total_sales' => $totalSales,
                'total_payment' => $totalPayment,
                'monthly' => $monthlyData
            ];
        });
        
        return view('admin.party.performance', compact('performanceData', 'uniqueMonths'));
    }
}
