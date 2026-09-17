<?php

namespace App\Http\Controllers;

use App\Models\Depo;
use App\Models\TravelMode;
use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\UserDepoAccess;
use App\Models\UserStateAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\State;
use App\Models\District;
use App\Models\City;
use App\Models\Company;
use App\Models\Tehsil;
use App\Models\Pincode;
use App\Models\Designation;
use App\Models\TaDaSlab;
use App\Models\TaDaTourSlab;
use App\Models\TaDaVehicleSlab;
use App\Models\TourType;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Hash;
use Barryvdh\DomPDF\Facade\Pdf;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_users')->only(['index']);
        $this->middleware('permission:create_users')->only(['create','store']);
        $this->middleware('permission:edit_users')->only(['edit','update']);
        $this->middleware('permission:delete_users')->only(['destroy']);
    }
    public function index(Request $request)
    {
        Session::put('page', 'dashboard');
        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();
        $query = User::with(['roles', 'permissions', 'state', 'district', 'tehsil', 'city', 'reportingManager', 'activeSessions', 'depos', 'designation'])->latest();
        // Filters
        if ($request->filled('state_id')) {
            $query->where('state_id', $request->state_id);
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('designation_id')) {
            $query->where('designation_id', $request->designation_id);
        }
        if ($request->filled('mobile')) {
            $query->where('mobile', 'like', '%' . $request->mobile . '%');
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        $maxUsers = 0;
        if (auth()->user()->user_level !== 'master_admin') {
            $getcompany = Company::find(1);
            if(isset($getcompany->id)) {
                $maxUsers = $getcompany->user_assigned;
            }
        }
        $stateIds = [];
        $userStateAccess = UserStateAccess::where('user_id', $user->id)->first();
        if ($userStateAccess && !empty($userStateAccess->state_ids)) {
            $stateIds = $userStateAccess->state_ids ?? [];
        
        }
        if (!in_array($roleName, ['master_admin', 'sub_admin'])) {
            
            if (!empty($stateIds)) {
                $query->whereIn('state_id', $stateIds)->where('reporting_to', $user->id);
            }else{
                $query->whereRaw('1 = 0');
            }
            // $query->where('reporting_to', $user->id);
        }


        $users = $query->get();
        
        // $currentUsers = $query->count();
        $currentUsers = User::count();
        if (in_array($roleName, ['master_admin', 'sub_admin'])) {
            $states = State::where('status', 1)->get();
        }else{
            if($userStateAccess && !empty($userStateAccess->state_ids)) {
                $states = State::where('status', 1)->whereIn('id', $stateIds)->get();
            }else{
                $states = collect();
            }
        }
        
        $designations = Designation::where('status', 1)->get();
        
        
        $stateAccesCompany = "";
        if($roleName != 'master_admin'){
            $stateAccesCompany = Company::value('state');
            $stateAccesCompany = $stateAccesCompany ? explode(',', $stateAccesCompany) : [];
        }
        return view('admin.users.index', compact('users', 'maxUsers', 'currentUsers', 'states', 'designations','stateAccesCompany'));
    }

    public function create()
    {
        $authUser = auth()->user();
        $roleName = $authUser->getRoleNames()->first();
        $currentUsers = User::count();
        $maxUsers = 0;
        if($roleName != 'master_admin'){
            $getcompany = Company::find(1);
            if(isset($getcompany->id)) {
                $maxUsers = $getcompany->user_assigned;
            }
            if ($currentUsers >= $maxUsers) {
                return redirect()
                    ->route('users.index')
                    ->with('error', 'User limit reached. You cannot create more users.');
            }
        }
        
        $roles = Role::where('status',1)->get();
        // if($roleName == 'sub_admin'){
        //     $roles = Role::where('name', '!=', 'sub_admin')->get();
        // }
        $companies = Company::all();
        $users = User::when($authUser->user_level !== 'master_admin', function ($query) use ($authUser) {
                $query->where('company_id', $authUser->company_id);
            })->get();

        $designations = Designation::all();
        $depos = Depo::where('status',1)->get();
        
        return view('admin.users.create', [
            'roles' => $roles,
            'permissions' => Permission::all(),
            'states' => State::all(),
            'companies' => $companies,
            'authUser' => $authUser,
            'users' => $users, // ✅ added here
            'designations' => $designations, // ✅ passed to view
            'depos' => $depos
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('users', 'public');
        }

        if ($request->hasFile('cancel_cheque_photos')) {
            $photos = [];
            foreach ($request->file('cancel_cheque_photos') as $index => $photo) {
                if ($index >= 3) break; 
                $photos[] = $photo->store('cancel_cheques', 'public');
            }
            $data['cancel_cheque_photos'] = json_encode($photos);
        }

        $user = User::create($data);

        if ($request->filled('roles')) {
            $user->syncRoles($request->input('roles'));
        }

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }
    public function show(User $user)
    {
        if (auth()->user()->user_level !== 'master_admin' && $user->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized access to user.');
        }

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $authUser = auth()->user();
        $roleName = $authUser->getRoleNames()->first();
        $roles = Role::where('status',1)->get();
        // if($roleName == 'sub_admin'){
        //     $roles = Role::where('name', '!=', 'sub_admin')->get();
        // }

        $companies = Company::all();
        $users = User::where('id', '!=', $user->id)->get();
        $designations = Designation::all();
        $depos = Depo::where('status',1)->get();
            
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $roles,
            'permissions' => Permission::all(),
            'states' => State::all(),
            'districts' => District::where('state_id', $user->state_id)->get(),
            'cities' => City::where('district_id', $user->district_id)->get(),
            'tehsils' => Tehsil::where('city_id', $user->city_id)->get(),
            'pincodes' => Pincode::where('city_id', $user->city_id)->get(),
            'companies' => $companies,
            'authUser' => $authUser,
            'users' => $users, 
            'designations' => $designations, 
            'depos' => $depos

        ]);
    }
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        // Handle password
        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        // Handle Profile Image
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('users', 'public');
        }

        // Handle Cancel Cheque Photos
        if ($request->hasFile('cancel_cheque_photos')) {
            $cheque_photos = [];
            foreach ($request->file('cancel_cheque_photos') as $photo) {
                $cheque_photos[] = $photo->store('cheque_photos', 'public');
            }
            $data['cancel_cheque_photos'] = json_encode($cheque_photos);
        }

        $user->update($data);

        // Sync Roles & Permissions
        $user->syncRoles($request->input('roles', []));
        $user->syncPermissions($request->input('permissions', []));

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }
    public function destroy(User $user)
    {
        if (auth()->user()->user_level !== 'master_admin' && $user->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized delete attempt.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function exportPdf(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->getRoleNames()->first();

        $query = User::with([
            'state',
            'district',
            'tehsil',
            'designation',
            'reportingManager'
        ])->latest();

        // Same filters (optional – index page jeva)
        if ($request->filled('state_id')) {
            $query->where('state_id', $request->state_id);
        }

        if (!in_array($roleName, ['master_admin', 'sub_admin'])) {
            $query->where('reporting_to', $user->id);
        }

        $users = $query->get();

        $pdf = Pdf::loadView('admin.users.pdf', compact('users'))
            ->setPaper('A4', 'landscape');

        return $pdf->download('users-list.pdf');
    }


    public function getDepos(Request $request){
        // $depos = Depo::where('state_id',$request->state_id)->get(['id','depo_name']);
        $depos = Depo::get(['id','depo_name']);
        return response()->json($depos);
    }

    public function getUserDepoAccess(Request $request)
    {
        $userAccess = UserDepoAccess::where('user_id', $request->user_id)->first();
        return response()->json(['userAccess' => $userAccess]);
    }

    public function saveDepoAccess(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            // 'state_id' => 'required|exists:states,id',
            'depo_id' => 'required|array|min:1',
            'depo_id.*' => 'required|integer',
        ]);

        // Save or update
        $access = UserDepoAccess::updateOrCreate(
            ['user_id' => $data['user_id']],
            ['depo_ids' => $data['depo_id']]
        );

        return response()->json(['message' => 'Depo access saved successfully']);
    }

    public function getUserStateAccess(Request $request){
        $access = UserStateAccess::where('user_id', $request->user_id)->first();
        return response()->json([
            'state_ids' => $access ? $access->state_ids : []
        ]);
    }

    public function saveUserStateAccess(Request $request){
        $request->validate([
            'user_id' => 'required',
            'state_ids' => 'required|array'
        ]);

        UserStateAccess::updateOrCreate(
            ['user_id' => $request->user_id],
            ['state_ids' => $request->state_ids]
        );

        return response()->json(['success'=>true]);
    }

    public function getUserSlab(Request $request)
    {
        $userId = $request->user_id;
        $slabType = $request->slab;
        
        $travelModes = TravelMode::get();
        $tourTypes = TourType::all();
        $designations = Designation::all();
        $user = User::findOrFail($request->user_id);

        $vehicleSlabs = collect();
        $tourSlabs = collect();
        $taDaSlab = null;

        if ($slabType === "Slab Wise") {
            $designationId = $request->designation_id;
            if (empty($designationId)) {
                $designationId = $user->slab_designation_id;
            }
            $taDaSlab = TaDaSlab::whereNull('user_id')->first();
            
            $vehicleSlabs = TaDaVehicleSlab::where('type', 'slab_wise')->where('designation_id', $designationId)->whereNull('user_id')->get();
            
            $tourSlabs = TaDaTourSlab::where('type', 'slab_wise')->where('designation_id', $designationId)->whereNull('user_id')->get();

        } elseif ($slabType === "Individual") {
            $taDaSlab = TaDaSlab::where('user_id', $userId)->first();

            $vehicleSlabs = TaDaVehicleSlab::where('user_id', $userId)->get();
            $tourSlabs = TaDaTourSlab::where('user_id', $userId)->get();

            if (!$taDaSlab && $vehicleSlabs->isEmpty() && $tourSlabs->isEmpty()) {
                $taDaSlab = TaDaSlab::whereNull('user_id')->first();
                $vehicleSlabs = TaDaVehicleSlab::where('type', 'individual')->whereNull('user_id')->get();
                $tourSlabs = TaDaTourSlab::where('type', 'individual')->whereNull('user_id')->get();
            }
        }
        return response()->json([
            'travel_modes' => $travelModes,
            'tour_types' => $tourTypes,
            'designations' => $designations,
            'vehicle_slabs' => $vehicleSlabs,
            'tour_slabs' => $tourSlabs,
            'ta_da_slab' => $taDaSlab,
            'user_slab' => $user->slab,
            'approved_bills_in_da_slab_wise' => $taDaSlab->approved_bills_in_da_slab_wise ?? [],
            'slab_designation_id' => $user->slab_designation_id ?? [],
        ]);
    }

    public function saveSlab(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'slab' => 'required|string',
        ]);
        $user = User::findOrFail($request->user_id);
        $user->slab = $request->slab;
        if($request->slab == "Slab Wise"){
            $user->slab_designation_id = $request->designation_id;
        }
        $user->save();

        if ($request->slab === "Individual") {

            $request->validate([
                'max_monthly_travel' => 'nullable|in:yes,no',
                'km' => 'nullable|numeric',
                'approved_bills_in_da' => 'nullable|array',
                'travel_mode_id' => 'nullable|array',
                // 'travel_mode_id.*' => 'exists:travel_modes,id',
                'travelling_allow_per_km' => 'nullable|array',
                'travelling_allow_per_km.*' => 'numeric',
                'tour_type_id' => 'nullable|array',
                // 'tour_type_id.*' => 'exists:tour_types,id',
                'da_amount' => 'nullable|array',
                'da_amount.*' => 'numeric',
            ]);

            $taDaSlab = TaDaSlab::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'max_monthly_travel' => $request->max_monthly_travel,
                    'km' => $request->km,
                    'approved_bills_in_da' => $request->approved_bills_in_da,
                    'travel_mode_enabled' => $request->has('travel_mode_enabled') ? 1 : 0,
                    'travel_mode_limit' => $request->has('travel_mode_enabled') ? $request->travel_mode_limit : null,
                    'tour_type_enabled' => $request->has('tour_type_enabled') ? 1 : 0,
                    'tour_type_limit' => $request->has('tour_type_enabled') ? $request->tour_type_limit : null,
                ]
            );

            // Reset old slabs
            TaDaVehicleSlab::where('ta_da_slab_id', $taDaSlab->id)->delete();
            TaDaTourSlab::where('ta_da_slab_id', $taDaSlab->id)->delete();

            // Vehicle slab
            if ($request->has('travel_mode_id')) {
                foreach ($request->travel_mode_id as $index => $modeId) {
                    TaDaVehicleSlab::create([
                        'ta_da_slab_id' => $taDaSlab->id,
                        'travel_mode_id' => $modeId,
                        'travelling_allow_per_km' => $request->travelling_allow_per_km[$index] ?? 0,
                        'user_id' => $user->id,
                        'type' => "individual"
                    ]);
                }
            }

            // Tour slab
            if ($request->has('tour_type_id')) {
                foreach ($request->tour_type_id as $index => $tourId) {
                    TaDaTourSlab::create([
                        'ta_da_slab_id' => $taDaSlab->id,
                        'tour_type_id' => $tourId,
                        'da_amount' => $request->da_amount[$index] ?? 0,
                        'user_id' => $user->id,
                        'type' => "individual"
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Slab saved successfully']);
    }
    
    public function toggle(User $user)
    {
        if (auth()->user()->user_level !== 'master_admin' && $user->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized toggle attempt.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return redirect()->route('users.index')->with('success', 'User status updated.');
    }
    public function resetPassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'password' => 'required|min:6',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Password reset successfully']);
    }

    public function getDistricts($state_id)
    {
        $districts = District::where('state_id', $state_id)->get();
        return response()->json($districts);
    }

    public function getCities($district_id)
    {
        $cities = City::where('district_id', $district_id)->get();
        return response()->json($cities);
    }

    public function getTehsils($city_id)
    {
        $tehsils = Tehsil::where('city_id', $city_id)->get();
        return response()->json($tehsils);
    }

    public function getPincodes($city_id)
    {
        $pincodes = Pincode::where('city_id', $city_id)->get();
        return response()->json($pincodes);
    }
}
