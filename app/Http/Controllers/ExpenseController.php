<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpensePdf;
use App\Models\State;
use App\Models\TaDaTourSlab;
use App\Models\TaDaVehicleSlab;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserStateAccess;
use App\Models\TaDaSlab;
use Carbon\Carbon; 
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_expense')->only(['index','show']);
        // $this->middleware('permission:create_permissions')->only(['create','store']);
        // $this->middleware('permission:edit_permissions')->only(['edit','update']);
        // $this->middleware('permission:delete_permissions')->only(['destroy']);
    }
    
    public function index(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();

        $filters = $this->getRoleBasedStateAndEmployeeFilters();
        extract($filters);

        $query = Expense::with(['user', 'travelMode']);

        if (!in_array($roleName, ['master_admin', 'sub_admin'])) {
            if (empty($stateIds)) {
                $expenses = collect();
            } else {
                $query->whereHas('user', function ($q) use ($user, $stateIds) {
                    $q->whereIn('state_id', $stateIds)
                    ->where('reporting_to', $user->id);
                });
            }
        }

        if ($request->filled('from_date')) {
            $query->whereDate('bill_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('bill_date', '<=', $request->to_date);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('bill_type')) {
            $query->where('bill_type', $request->bill_type);
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->filled('state_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('state_id', $request->state_id);
            });
        }

        if (!isset($expenses)) {
            $expenses = $query->latest()->get();
        }



        return view('admin.expense.index', compact('expenses', 'states', 'employees'));
    }

    public function edit(string $id)
    {
        $expense = Expense::findOrFail($id);
        return view('admin.expense.edit', compact('expense'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'bill_date' => 'required|date',
            'bill_type' => 'required|string',
            'bill_title' => 'nullable|string',
            'bill_details_description' => 'nullable|string',
            'travel_mode' => 'nullable',
            'amount' => 'required|numeric',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        $expense = Expense::findOrFail($id);

        $expense->bill_date = $request->bill_date;
        $expense->bill_type = $request->bill_type;
        $expense->bill_title = $request->bill_title;
        $expense->bill_details_description = $request->bill_details_description;
        $expense->travel_mode = $request->travel_mode;
        $expense->amount = $request->amount;

        // IMAGE UPDATE
        if ($request->hasFile('image')) {
            if ($expense->image && file_exists(storage_path('app/public/'.$expense->image))) {
                unlink(storage_path('app/public/'.$expense->image));
            }
            $path = $request->file('image')->store('expenses', 'public');
            // $expense->image = $request->file('image')->store('expenses', 'public');
            $expense->image = basename($path);
        }

        $expense->save();

        return redirect()->route('expense.index')->with('success', 'Expense Updated Successfully!');
    }

    public function show($id, Request $request)
    {
        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();

        $year = $request->year ?? now()->year;

        $stateIds = [];
        $userStateAccess = UserStateAccess::where('user_id', $user->id)->first();
        if ($userStateAccess && !empty($userStateAccess->state_ids)) {
            $stateIds = $userStateAccess->state_ids;
        }

        $companyCount = Company::count();
        if ($companyCount == 1) {
            $company = Company::first();
            $companyStates = array_map('intval', explode(',', $company->state));
            $states = State::where('status', 1)->whereIn('id', $companyStates)->get();
        } else {
            $states = State::where('status', 1)->get();
        }

        // 🔹 Initialize final report (12 months)
        $finalReport = [];

        for ($m = 1; $m <= 12; $m++) {

            $monthKey = Carbon::create($year, $m, 1)->format('Y-m');
            $monthName = Carbon::create($year, $m, 1)->format('F');

            $from = Carbon::create($year, $m, 1)->startOfMonth()->toDateString();
            $to   = Carbon::create($year, $m, 1)->endOfMonth()->toDateString();

            // Init month row
            $finalReport[$monthKey] = ['month' => $monthName,'states' => []];

            foreach ($states as $state) {
                $finalReport[$monthKey]['states'][$state->id] = ['ta' => 0,'da' => 0,'other' => 0,'total' => 0];
            }

            // 🔹 Trip query
            $query = Trip::with('user')->where('approval_status', 'approved')->whereBetween('trip_date', [$from, $to]);

            if (!in_array($roleName, ['master_admin', 'sub_admin'])) {
                if (empty($stateIds)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('user', function ($q) use ($stateIds, $user) {
                        $q->whereIn('state_id', $stateIds)
                        ->where('reporting_to', $user->id);
                    });
                }
            }

            $trips = $query->get();

            // 🔹 Calculation (same logic)
            foreach ($trips as $item) {

                if (!$item->user || !$item->user->state_id) continue;

                $stateId = $item->user->state_id;
                $slabType = $item->user->slab ?? "";

                $da_amount = null;
                $ta_amount = null;

                if ($slabType == "Slab Wise") {
                    $da_amount = TaDaTourSlab::where('tour_type_id', $item->tour_type)->whereNull('user_id')->where('designation_id', $item->user->slab_designation_id)->first();

                    $ta_amount = TaDaVehicleSlab::where('travel_mode_id', $item->travel_mode)->whereNull('user_id')->where('designation_id', $item->user->slab_designation_id)->first();
                }

                if ($slabType == "Individual") {
                    $da_amount = TaDaTourSlab::where('tour_type_id', $item->tour_type)->where('user_id', $item->user->id)->first();

                    $ta_amount = TaDaVehicleSlab::where('travel_mode_id', $item->travel_mode)->where('user_id', $item->user->id)->first();
                }

                $expense = Expense::where('user_id', $item->user_id)->whereDate('bill_date', $item->trip_date)->where('approval_status', 'Approved')->sum('amount');

                $slabInfo = null;
                if ($slabType == 'Individual') {
                    $slabInfo = TaDaSlab::where('user_id', $item->user->id)->first();
                } else {
                    $slabInfo = TaDaSlab::whereNull('user_id')->first();
                }
                $limitEnabled = $slabInfo ? $slabInfo->travel_mode_enabled : 0;
                $limitValue = $slabInfo ? $slabInfo->travel_mode_limit : 0;

                $travelKm = ($item->end_km - $item->starting_km);

                if ($limitEnabled == 1 && $travelKm < $limitValue && $item->trip_limit_override == 0) {
                    $ta = 0;
                    $da = 0;
                } else if($limitEnabled == 1 && $travelKm < $limitValue && $item->trip_limit_override == 1){
                    $ta = ($ta_amount->travelling_allow_per_km ?? 0) * $travelKm;
                    $da = $da_amount->da_amount ?? 0;
                } else if($limitEnabled == 1 && $travelKm < $limitValue && $item->trip_limit_override == 2){
                    $ta = 0;
                    $da = $da_amount->da_amount ?? 0;
                } else if($limitEnabled == 1 && $travelKm < $limitValue && $item->trip_limit_override == 3){
                    $ta = ($ta_amount->travelling_allow_per_km ?? 0) * $travelKm;
                    $da = 0;
                } else {
                    $ta = ($ta_amount->travelling_allow_per_km ?? 0) * $travelKm;
                    $da = $da_amount->da_amount ?? 0;
                }

                
                $other = $expense ?? 0;

                $finalReport[$monthKey]['states'][$stateId]['ta'] += $ta;
                $finalReport[$monthKey]['states'][$stateId]['da'] += $da;
                $finalReport[$monthKey]['states'][$stateId]['other'] += $other;
                $finalReport[$monthKey]['states'][$stateId]['total'] += ($ta + $da + $other);
            }
        }

        return view(
            'admin.expense.state_wise_report',
            compact('states', 'finalReport', 'year')
        );
    }


    public function destroy(string $id)
    {
        try {
            $expense = Expense::find($id);
            if (!$expense) {
                return redirect()->back()->with('error', 'Expense not found.');
            }
            $expense->delete();
            return redirect()->back()->with('success', 'Expense deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function approve($id)
    {
        $expense = Expense::with('user')->findOrFail($id);
        $expense->approval_status = 'Approved';
        $expense->save();

        $this->sendExpenseStatusNotification($expense);

        return redirect()->back()->with('success', 'Expense approved successfully.');
    }
    public function reject(Request $request, $id)
    {
        $request->validate([
            'reject_reason' => 'required|string'
        ]);

        $expense = Expense::with('user')->findOrFail($id);
        $expense->approval_status = 'Rejected';
        $expense->reject_reason = $request->reject_reason;
        $expense->save();

        $this->sendExpenseStatusNotification($expense);

        return redirect()->back()->with('error', 'Expense rejected.');
    }

    private function sendExpenseStatusNotification(Expense $expense): void
    {
        try {
            $expense->loadMissing('user');

            if (!$expense->user || empty($expense->user->fcm_token)) {
                return;
            }

            $firebaseService = app(\App\Services\FirebaseService::class);
            $title = "Expense #{$expense->id} {$expense->approval_status}";
            $message = $expense->approval_status === 'Approved'
                ? 'Your expense has been approved.'
                : 'Your expense has been rejected.';

            $firebaseService->sendNotification($expense->user->fcm_token, $title, $message, [
                'type' => 'expense_status',
                'expense_id' => (string) $expense->id,
                'status' => $expense->approval_status,
                'reject_reason' => $expense->reject_reason ?? '',
            ], $expense->user->id);
        } catch (\Exception $e) {
        }
    }

    public function expenseReport(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();

        $stateIds = [];
        $userStateAccess = UserStateAccess::where('user_id', $user->id)->first();
        if ($userStateAccess && !empty($userStateAccess->state_ids)) {
            $stateIds = $userStateAccess->state_ids;
        }

        $month = $request->month ?? now()->format('Y-m');
        $from = Carbon::parse($month . '-01')->startOfMonth()->format('Y-m-d');
        $to   = Carbon::parse($month . '-01')->endOfMonth()->format('Y-m-d');

        $query = Trip::with(['user', 'company', 'approvedByUser', 'tripLogs', 'customers', 'travelMode', 'tourType'])
            ->where('approval_status', 'approved')
            ->whereBetween('trip_date', [$from, $to]);

        if (!in_array($roleName, ['master_admin', 'sub_admin'])) {
            if (empty($stateIds)) {
                $query->whereRaw('1 = 0'); 
            } else {
                $query->whereHas('user', function ($q) use ($user, $stateIds) {
                    $q->whereIn('state_id', $stateIds)
                    ->where('reporting_to', $user->id);
                });
            }
        }

        if ($request->filled('state_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('state_id', $request->state_id);
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $data = $query->latest()->get();

        foreach ($data as $item) {

            $slabType = $item->user->slab ?? "";

            $da_amount = null;
            $ta_amount = null;

            if ($slabType == "Slab Wise") {
                $da_amount = TaDaTourSlab::where('tour_type_id', $item->tour_type)->whereNull('user_id')->where('designation_id', $item->user->slab_designation_id)->first();

                $ta_amount = TaDaVehicleSlab::where('travel_mode_id', $item->travel_mode)->whereNull('user_id')
                    ->where('designation_id', $item->user->slab_designation_id)->first();
            }

            if ($slabType == "Individual") {
                $da_amount = TaDaTourSlab::where('tour_type_id', $item->tour_type)->where('user_id', $item->user->id)->first();

                $ta_amount = TaDaVehicleSlab::where('travel_mode_id', $item->travel_mode)->where('user_id', $item->user->id)->first();
            }

            $expense = Expense::where('user_id', $item->user_id)->whereDate('bill_date', $item->trip_date)
                ->where('approval_status', 'Approved')->get();

            $slabInfo = null;
            if ($slabType == 'Individual') {
                $slabInfo = TaDaSlab::where('user_id', $item->user->id)->first();
            } else {
                $slabInfo = \App\Models\TaDaSlab::whereNull('user_id')->first();
            }
            $limitEnabled = $slabInfo ? $slabInfo->travel_mode_enabled : 0;
            $limitValue = $slabInfo ? $slabInfo->travel_mode_limit : 0;

            $total_km = ($item->end_km - $item->starting_km);

            if ($limitEnabled == 1 && $total_km < $limitValue && $item->trip_limit_override == 0) {
                $item->ta_exp = 0;
                $item->da_exp = 0;
            } else if($limitEnabled == 1 && $total_km < $limitValue && $item->trip_limit_override == 1){
                $item->ta_exp = ($ta_amount->travelling_allow_per_km ?? 0) * $total_km;
                $item->da_exp = $da_amount->da_amount ?? 0;
            } else if($limitEnabled == 1 && $total_km < $limitValue && $item->trip_limit_override == 2){
                $item->ta_exp = 0;
                $item->da_exp = $da_amount->da_amount ?? 0;
            } else if($limitEnabled == 1 && $total_km < $limitValue && $item->trip_limit_override == 3){
                $item->ta_exp = ($ta_amount->travelling_allow_per_km ?? 0) * $total_km;
                $item->da_exp = 0;
            } else {
                $item->ta_exp = ($ta_amount->travelling_allow_per_km ?? 0) * $total_km;
                $item->da_exp = $da_amount->da_amount ?? 0;
            }

            
            $item->other_exp = $expense->sum('amount') ?? 0;

            $item->total_exp =
                $item->ta_exp +
                $item->da_exp +
                $item->other_exp;
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
                    : (empty($stateIds)
                        ? collect()
                        : State::where('status', 1)->whereIn('id', $stateIds)->get());
            }
        } else {
            $states = in_array($roleName, ['master_admin', 'sub_admin'])
                ? State::where('status', 1)->get()
                : (empty($stateIds)
                    ? collect()
                    : State::where('status', 1)->whereIn('id', $stateIds)->get());
        }

        if (in_array($roleName, ['master_admin', 'sub_admin'])) {
            $employees = User::where('status', 'Active')->where('id', '!=', 1)->get();
        } else {
            $employees = empty($stateIds)
                ? collect()
                : User::where('status', 'Active')->where('id', '!=', 1)
                    ->whereIn('state_id', $stateIds)
                    ->where('reporting_to', $user->id)
                    ->get();
        }

        $total_travel_km = $data->sum(fn ($i) => $i->end_km - $i->starting_km);
        $total_ta = $data->sum('ta_exp');
        $total_da = $data->sum('da_exp');
        $total_other = $data->sum('other_exp');
        $total_total = $data->sum('total_exp');

        return view('admin.expense.report', compact(
            'data',
            'states',
            'employees',
            'total_ta',
            'total_da',
            'total_other',
            'total_total',
            'month',
            'total_travel_km'
        ))->with(['from_date' => $from, 'to_date' => $to]);
    }

    public function bulkApprove(Request $request)
    {
        $ids = json_decode($request->trip_ids, true);
        $selected_user_id = $request->selected_user_id;

        if (empty($ids)) {
            return back()->with('error', 'No trips selected!');
        }

        $trips = Trip::whereIn('id', $ids)->where('pdf_status', 0)->with(['user','company','approvedByUser','tripLogs','customers','travelMode','tourType'])->get();

        if ($trips->isEmpty()) {
            return back()->with('error', 'All selected trips PDF already generated!');
        }

        $firstTrip = $trips->first();
        $userSlabType = $firstTrip->user ? $firstTrip->user->slab : "";
        
        $taDaSlabCheck = null;
        if ($userSlabType == 'Individual') {
            $taDaSlabCheck = TaDaSlab::where('user_id', $selected_user_id)->first();
            if (!$taDaSlabCheck) {
                $taDaSlabCheck = TaDaSlab::whereNull('user_id')->first();
            }
        } else {
            $taDaSlabCheck = TaDaSlab::whereNull('user_id')->first();
        }

        if ($taDaSlabCheck && $taDaSlabCheck->max_monthly_travel === 'yes' && $taDaSlabCheck->km > 0) {
            $total_travel_km_selected = $trips->sum(function ($item) {
                return ($item->end_km - $item->starting_km);
            });

            $firstTripDate = $firstTrip->trip_date;
            
            $previouslyApprovedTripsKm = Trip::where('user_id', $selected_user_id)
                ->where('pdf_status', 1)
                ->whereYear('trip_date', \Carbon\Carbon::parse($firstTripDate)->year)
                ->whereMonth('trip_date', \Carbon\Carbon::parse($firstTripDate)->month)
                ->get()
                ->sum(function($item) {
                    return ((float)$item->end_km - (float)$item->starting_km);
                });

            $newTotalKm = $previouslyApprovedTripsKm + $total_travel_km_selected;

            if ($newTotalKm > $taDaSlabCheck->km) {
                return back()->with('error', 'Cannot approve trips. Max monthly travel limit (' . $taDaSlabCheck->km . ' km) exceeded. Current month approved: ' . $previouslyApprovedTripsKm . ' km, Selected: ' . $total_travel_km_selected . ' km.');
            }
        }

        /* ================= CALCULATIONS ================= */
        foreach ($trips as $item) {

            $slabType = $item->user->slab ?? "";

            $da_amount = null;
            $ta_amount = null;

            if ($slabType == "Slab Wise") {
                $da_amount = TaDaTourSlab::where('tour_type_id', $item->tour_type)
                    ->whereNull('user_id')
                    ->where('designation_id', $item->user->slab_designation_id)
                    ->first();

                $ta_amount = TaDaVehicleSlab::where('travel_mode_id', $item->travel_mode)
                    ->whereNull('user_id')
                    ->where('designation_id', $item->user->slab_designation_id)
                    ->first();
            }

            if ($slabType == "Individual") {
                $da_amount = TaDaTourSlab::where('tour_type_id', $item->tour_type)
                    ->where('user_id', $item->user->id)
                    ->first();

                $ta_amount = TaDaVehicleSlab::where('travel_mode_id', $item->travel_mode)
                    ->where('user_id', $item->user->id)
                    ->first();
            }

            $expense = Expense::where('user_id', $item->user_id)
                ->whereDate('bill_date', $item->trip_date)
                ->where('approval_status','Approved')
                ->get();

            $slabInfo = null;
            if ($slabType == 'Individual') {
                $slabInfo = TaDaSlab::where('user_id', $item->user->id)->first();
            } else {
                $slabInfo = TaDaSlab::whereNull('user_id')->first();
            }
            $limitEnabled = $slabInfo ? $slabInfo->travel_mode_enabled : 0;
            $limitValue = $slabInfo ? $slabInfo->travel_mode_limit : 0;

            $total_km = ($item->end_km - $item->starting_km);

            if ($limitEnabled == 1 && $total_km < $limitValue && $item->trip_limit_override == 0) {
                $item->ta_exp = 0;
                $item->da_exp = 0;
            } else if($limitEnabled == 1 && $total_km < $limitValue && $item->trip_limit_override == 1){
                $item->ta_exp = ($ta_amount->travelling_allow_per_km ?? 0) * $total_km;
                $item->da_exp = $da_amount->da_amount ?? 0;
            } else if($limitEnabled == 1 && $total_km < $limitValue && $item->trip_limit_override == 2){
                $item->ta_exp = 0;
                $item->da_exp = $da_amount->da_amount ?? 0;
            } else if($limitEnabled == 1 && $total_km < $limitValue && $item->trip_limit_override == 3){
                $item->ta_exp = ($ta_amount->travelling_allow_per_km ?? 0) * $total_km;
                $item->da_exp = 0;
            } else {
                $item->ta_exp = ($ta_amount->travelling_allow_per_km ?? 0) * $total_km;
                $item->da_exp = $da_amount->da_amount ?? 0;
            }

            
            $item->other_exp = $expense->sum('amount') ?? 0;
            $item->total_exp = $item->ta_exp + $item->da_exp + $item->other_exp;
        }

        /* ================= TOTALS ================= */
        $total_travel_km = $trips->sum(function ($item) {
            return ($item->end_km - $item->starting_km);
        });

        $total_ta     = $trips->sum('ta_exp');
        $total_da     = $trips->sum('da_exp');
        $total_other  = $trips->sum('other_exp');
        $total_total  = $trips->sum('total_exp');

        /* ================= HEADER INFO ================= */
        $company = Company::first();
        $getUser = User::with('designation','reportingManager')
            ->where('id', $selected_user_id)
            ->first();

        $headerInfo = [
            'company_name' => $company->name ?? '-',
            'employee_name'=> $getUser->name ?? '-',
            'designation'  => $getUser->designation->name ?? '-',
            'reporting_to' => $getUser->reportingManager->name ?? '-',
            'hq'           => $getUser->headquarter ?? '-',
            'from_date'    => $trips->min('trip_date'),
            'to_date'      => $trips->max('trip_date'),
        ];

        /* ================= PDF ================= */
        $month = Carbon::parse($trips->min('trip_date'))->format('Y-m');

        $pdf = Pdf::loadView('admin.expense.pdf.report', compact(
            'trips',
            'total_travel_km',
            'total_ta',
            'total_da',
            'total_other',
            'total_total',
            'headerInfo'
        ));

        $fileName = 'expense_'.$selected_user_id.'_'.$month.'_'.time().'.pdf';
        $path = 'expense_pdfs/'.$fileName;

        Storage::disk('public')->put($path, $pdf->output());

        $expensePdf = ExpensePdf::create([
            'user_id' => $selected_user_id,
            'pdf_path'=> $path,
            'month'   => $month,
        ]);

        Trip::whereIn('id', $trips->pluck('id'))->update([
            'pdf_status' => 1
        ]);

        $this->sendExpenseReportApprovalNotification($expensePdf, $trips->count(), $total_total);

        return back()->with('success', 'Expense PDF generated successfully.');
    }

    private function sendExpenseReportApprovalNotification(ExpensePdf $expensePdf, int $tripCount, $totalAmount): void
    {
        try {
            $user = User::find($expensePdf->user_id);

            if (!$user || empty($user->fcm_token)) {
                return;
            }

            $firebaseService = app(\App\Services\FirebaseService::class);
            $monthLabel = Carbon::parse($expensePdf->month . '-01')->format('F Y');
            $title = 'Expense Report Approved';
            $message = "Your expense report for {$monthLabel} has been approved.";

            $firebaseService->sendNotification($user->fcm_token, $title, $message, [
                'type' => 'expense_report_approved',
                'expense_pdf_id' => (string) $expensePdf->id,
                'month' => $expensePdf->month,
                'trip_count' => (string) $tripCount,
                'total_amount' => (string) $totalAmount,
                'pdf_url' => asset('storage/' . $expensePdf->pdf_path),
            ], $user->id);
        } catch (\Exception $e) {
        }
    }

    public function expensePdfList(){
        $pdfs = ExpensePdf::with('user')
        ->latest()
        ->get();

        return view('admin.expense.pdf.list', compact('pdfs'));
    }

    public function stateWiseReport(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();

        // 🔹 State access
        $stateIds = [];
        $userStateAccess = UserStateAccess::where('user_id', $user->id)->first();
        if ($userStateAccess && !empty($userStateAccess->state_ids)) {
            $stateIds = $userStateAccess->state_ids;
        }

        // 🔹 Month
        $month = $request->month ?? now()->format('Y-m');
        $from = Carbon::parse($month . '-01')->startOfMonth()->toDateString();
        $to   = Carbon::parse($month . '-01')->endOfMonth()->toDateString();

        // 🔹 Base trip query (same as your expenseReport)
        $query = Trip::with(['user.state'])
            ->where('approval_status', 'approved')
            ->whereBetween('trip_date', [$from, $to]);

        if (!in_array($roleName, ['master_admin', 'sub_admin'])) {
            if (empty($stateIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('user', function ($q) use ($stateIds, $user) {
                    $q->whereIn('state_id', $stateIds)
                    ->where('reporting_to', $user->id);
                });
            }
        }

        $trips = $query->get();

        $companyCount = Company::count();
        $company = null;
        if ($companyCount == 1) {
            $company = Company::first();
            $companyStates = array_map('intval', explode(',', $company->state));
            $states = State::where('status', 1)
                        ->whereIn('id', $companyStates)
                        ->get();
        }else{
            $states = State::where('status', 1)->get();
        }

        $report = [];
        foreach ($states as $state) {
            $report[$state->id] = [
                'state' => $state->name,
                'ta' => 0,
                'da' => 0,
                'other' => 0,
                'total' => 0,
            ];
        }

        foreach ($trips as $item) {

            if (!$item->user || !$item->user->state_id) continue;

            $stateId = $item->user->state_id;

            $slabType = $item->user->slab ?? "";

            $da_amount = null;
            $ta_amount = null;

            if ($slabType == "Slab Wise") {
                $da_amount = TaDaTourSlab::where('tour_type_id', $item->tour_type)->whereNull('user_id')
                    ->where('designation_id', $item->user->slab_designation_id)->first();

                $ta_amount = TaDaVehicleSlab::where('travel_mode_id', $item->travel_mode)->whereNull('user_id')
                    ->where('designation_id', $item->user->slab_designation_id)->first();
            }

            if ($slabType == "Individual") {
                $da_amount = TaDaTourSlab::where('tour_type_id', $item->tour_type)->where('user_id', $item->user->id)->first();

                $ta_amount = TaDaVehicleSlab::where('travel_mode_id', $item->travel_mode)->where('user_id', $item->user->id)->first();
            }

            $expense = Expense::where('user_id', $item->user_id)->whereDate('bill_date', $item->trip_date)
                ->where('approval_status', 'Approved')->get();

            $slabInfo = null;
            if ($slabType == 'Individual') {
                $slabInfo = TaDaSlab::where('user_id', $item->user->id)->first();
            } else {
                $slabInfo = TaDaSlab::whereNull('user_id')->first();
            }
            $limitEnabled = $slabInfo ? $slabInfo->travel_mode_enabled : 0;
            $limitValue = $slabInfo ? $slabInfo->travel_mode_limit : 0;

            $travelKm = ($item->end_km - $item->starting_km);

            if ($limitEnabled == 1 && $travelKm < $limitValue && $item->trip_limit_override == 0) {
                $ta = 0;
                $da = 0;
            } else if($limitEnabled == 1 && $travelKm < $limitValue && $item->trip_limit_override == 1){
                $ta = ($ta_amount->travelling_allow_per_km ?? 0) * $travelKm;
                $da = $da_amount->da_amount ?? 0;
            } else if($limitEnabled == 1 && $travelKm < $limitValue && $item->trip_limit_override == 2){
                $ta = 0;
                $da = $da_amount->da_amount ?? 0;
            } else if($limitEnabled == 1 && $travelKm < $limitValue && $item->trip_limit_override == 3){
                $ta = ($ta_amount->travelling_allow_per_km ?? 0) * $travelKm;
                $da = 0;
            } else {
                $ta = ($ta_amount->travelling_allow_per_km ?? 0) * $travelKm;
                $da = $da_amount->da_amount ?? 0;
            }
            

            
            $other = $expense->sum('amount') ?? 0;

            $report[$stateId]['ta'] += $ta;
            $report[$stateId]['da'] += $da;
            $report[$stateId]['other'] += $other;
            $report[$stateId]['total'] += ($ta + $da + $other);
        }

        return view('admin.expense.state_wise_report',compact('states', 'report', 'month'));
    }

}
