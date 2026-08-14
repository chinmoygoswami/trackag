<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Customer;
use App\Models\Company;
use App\Models\Depo;
use App\Models\PartyVisitTarget;
use App\Models\District;
use App\Models\State;
use App\Models\Tehsil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\Failure;
use App\Imports\CustomersImport;
use App\Models\UserStateAccess;
use App\Models\TallyPartySync;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_customers')->only(['index','show']);
        $this->middleware('permission:create_customers')->only(['create','store']);
        $this->middleware('permission:edit_customers')->only(['edit','update']);
        $this->middleware('permission:delete_customers')->only(['destroy']);
    }
    public function index(Request $request)
    {
        $user = Auth::user();
        $roleName = $user->getRoleNames()->first();

        $stateIds = [];
        $userStateAccess = UserStateAccess::where('user_id', $user->id)->first();
        if ($userStateAccess && !empty($userStateAccess->state_ids)) {
            $stateIds = $userStateAccess->state_ids;
        }

        $query = Customer::with(['user', 'company', 'state', 'district', 'tehsil'])->where('type','web');
        
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
        if ($request->filled('financial_year')) {
            // $query->where('financial_year', $request->financial_year);
        }
        if ($request->filled('party_code')) {
            $query->where('party_code', 'like', "%{$request->party_code}%");
        }
        if ($request->filled('agro_name')) {
            $query->where('agro_name', 'like', "%{$request->agro_name}%");
        }
        if ($request->filled('state_id')) {
            $query->where('state_id', $request->state_id);
        }
        if ($request->filled('contact_person')) {
            $query->where('contact_person_name', 'like', "%{$request->contact_person}%");
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }
        if ($request->filled('pending_party_mapping')) {
            $query->whereNull('user_id');
        }

        $customers = $query->latest()->get();
        
        $companyCount = Company::count();
        $company = null;

        if ($companyCount == 1) {
            $company = Company::first();

            if ($company && !empty($company->state)) {
                $companyStates = array_map('intval', explode(',', $company->state));

                if ($roleName === 'sub_admin') {
                    $states = State::where('status', 1)->whereIn('id', $companyStates)->get();
                } else {
                    $states = empty($stateIds) ? collect()
                        : State::where('status', 1)->whereIn('id', $stateIds)->get();
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
                ? State::where('status', 1)->get() : (empty($stateIds)
                ? collect() : State::where('status', 1)->whereIn('id', $stateIds)->get());
        }
        $financialYears = range(2025, now()->year + 1); // Example range

        $partyVisitTarget = PartyVisitTarget::first();

        return view('admin.customers.index', compact('customers', 'states', 'financialYears', 'partyVisitTarget'));
    }

    public function savePartyVisitTarget(Request $request)
    {
        $request->validate([
            'target' => 'required|integer|min:0'
        ]);

        $target = PartyVisitTarget::first() ?? new PartyVisitTarget();
        $target->target = $request->target;
        $target->save();

        return redirect()->back()->with('success', 'Party Visit Target updated successfully.');
    }

    public function toggleStatus(Customer $customer)
    {
        $customer->is_active = !$customer->is_active;
        $customer->save();

        return response()->json([
            'success' => true,
            'status' => $customer->is_active
        ]);
    }

    public function create()
    {
        $admin = Auth::user();
        $executives = collect();
        $executives = User::where('id', '!=', 1)->get();
        $depos = Depo::where('status',1)->orderBy('depo_name')->get();
        $states = State::where('status', 1)->orderBy('name')->get();
        return view('admin.customers.create', compact( 'executives','depos','states'));
    }

    public function store(Request $request)
    {
        $admin = Auth::user();

        $validated = $request->validate([
            'agro_name' => 'required|string|max:255',
            'contact_person_name'  => 'required|string|max:255',
            'party_code' => 'nullable|string|max:255',
            'address'    => 'nullable|string|max:255',
            'phone' => 'required|string|max:255',
            // 'gst_no' => 'required|string|max:255',
            'user_id'    => 'required|exists:users,id',
            // 'credit_limit' => 'required|string|max:255',
            // 'depo_id' => 'required|exists:depos,id',
            'party_active_since' => 'required|date',
            'state_id' => 'required|exists:states,id',
            'district_id' => 'required|exists:districts,id',
            'tehsil_id' => 'required|exists:tehsils,id',
            'email' => 'nullable|email|unique:customers,email',
            'is_active'  => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? (int) $request->input('is_active') : 1;
        $validated['type'] = "web";

        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', 'Customer added successfully.');
    }

    public function show(string $id)
    {
        $customer = Customer::with(['user', 'company'])->findOrFail($id);
        $this->authorizeCustomerAccess($customer);
        return view('admin.customers.show', compact('customer'));
    }

    public function edit(string $id)
    {
        $customer = Customer::findOrFail($id);
        $admin = Auth::user();

        $executives = collect();
        $executives = User::where('id', '!=', 1)->get();
        $depos = Depo::where('status',1)->orderBy('depo_name')->get();
        $states = State::where('status', 1)->orderBy('name')->get();
        $districts = District::where('state_id', $customer->state_id)->where('status',1)->orderBy('name')->get();
        $tehsils  = Tehsil::where('district_id', $customer->district_id)->where('status',1)->orderBy('name')->get();
        return view('admin.customers.edit', compact('customer', 'executives', 'depos','states','districts','tehsils'));
    }

    public function update(Request $request, string $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'agro_name'          => 'required|string|max:255',
            'contact_person_name'  => 'required|string|max:255',
            'party_code'         => 'nullable|string|max:255',
            'address'            => 'nullable|string|max:255',
            'phone'              => 'required|string|max:255',
            // 'gst_no'             => 'required|string|max:255',
            'user_id'            => 'required|exists:users,id',
            // 'credit_limit'       => 'required|string|max:255',
            // 'depo_id'            => 'required|exists:depos,id',
            'party_active_since' => 'required|date',
            'state_id'           => 'required|exists:states,id',
            'district_id'        => 'required|exists:districts,id',
            'tehsil_id'          => 'required|exists:tehsils,id',
            'email'              => 'nullable|email|unique:customers,email,' . $customer->id,
            'is_active'          => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? (int) $request->input('is_active') : 1;

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(string $id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }

    public function getExecutives($companyId)
    {
        $executives = User::where('company_id', $companyId)
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'master_admin');
            })->select('id', 'name')->get();

        return response()->json(['executives' => $executives]);
    }

    private function authorizeCustomerAccess(Customer $customer)
    {
        $admin = Auth::user();

        if ($admin->hasRole('master_admin')) return;

        if (($admin->company_id ?? 1) !== $customer->company_id) {
            abort(403, 'Unauthorized access to this customer.');
        }
    }


    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids');

        if (!$ids || !is_array($ids)) {
            return redirect()->back()->with('error', 'No customers selected for deletion.');
        }

        Customer::whereIn('id', $ids)->delete();

        return redirect()->route('customers.index')->with('success', 'Selected customers deleted successfully.');
    }


    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:2048',
        ]);

        $import = new CustomersImport();

        try {
            $import->import($request->file('file'));

            $rowErrors = [];

            foreach ($import->failures() as $failure) {
                $rowErrors[$failure->row()] = true; // ek row = ek error
            }

            $message = 'Customers imported successfully!';

            if (!empty($rowErrors)) {
                $message .= '<br><b>Some rows were skipped:</b><br>';

                foreach (array_keys($rowErrors) as $row) {
                    $message .= "Row {$row}: Agro Name, Phone, Contact Person Name required<br>";
                }
            }

            return redirect()->route('customers.index')->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Import failed: '.$e->getMessage());
        }
    }

    public function assignParty(Request $request)
    {
        $request->validate([
            'party_ids' => 'required|array',
            'party_ids.*' => 'integer|exists:tally_party_syncs,id',
            'user_id' => 'required|exists:users,id'
        ]);

        $parties = TallyPartySync::whereIn('id', $request->party_ids)->get();

        // Pre-fetch state and district maps to avoid N+1 query issues in the loop
        $stateMap = State::pluck('id', 'name')->mapWithKeys(function ($id, $name) {
            return [strtolower(trim($name)) => $id];
        })->toArray();

        $districts = District::select('id', 'name', 'state_id')->get();
        $districtMap = [];
        foreach ($districts as $d) {
            $name = strtolower(trim($d->name));
            $districtMap[$d->state_id . '-' . $name] = $d->id;
            // Fallback map without state_id
            if (!isset($districtMap['any-' . $name])) {
                $districtMap['any-' . $name] = $d->id;
            }
        }

        $customersToInsert = [];
        $now = now();

        foreach ($parties as $party) {
            $stateId = null;
            if ($party->state) {
                $stateId = $stateMap[strtolower(trim($party->state))] ?? null;
            }

            $districtId = null;
            if ($party->district) {
                $districtName = strtolower(trim($party->district));
                if ($stateId && isset($districtMap[$stateId . '-' . $districtName])) {
                    $districtId = $districtMap[$stateId . '-' . $districtName];
                } else {
                    $districtId = $districtMap['any-' . $districtName] ?? null;
                }
            }

            Customer::create([
                'type' => 'web',
                'agro_name' => $party->party_name,
                'contact_person_name' => $party->contact_person_name ?? $party->party_name,
                'party_code' => $party->master_id,
                'address' => $party->address,
                'phone' => $party->phone_1 ?? $party->phone_2 ?? 'N/A',
                'email' => $party->email,
                'gst_no' => $party->gst_no,
                'party_active_since' => $party->party_create_date ?? $now,
                'user_id' => $request->user_id,
                'is_active' => 1,
                'status' => 'approved',
                'state_id' => $stateId,
                'district_id' => $districtId,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Parties assigned successfully!']);
    }

}
