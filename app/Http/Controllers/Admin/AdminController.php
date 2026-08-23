<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Admin;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Services\Admin\AdminService;
use App\Models\UserSession;
use App\Models\Customer;
use App\Models\District;
// use App\Models\Permission;
// use App\Models\Role;
use App\Models\State;
use App\Models\Tehsil;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Trip;
use App\Models\Expense;
use App\Models\PartyVisit;
use App\Models\Attendance;
use App\Models\Depo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

use Session;

class AdminController extends Controller
{
    protected $adminService;

    // ✅ Inject AdminService using Constructor
    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function index()
    {
        $user = Auth::user();
        $isMasterAdmin = false;

        $todaysActiveUserCount = 0;
        $todaysOrderCount = 0;
        $todaysPaymentCollection = 0;
        $todaysPartyVisits = 0;
        $statesData = collect([]);
        $employeeData = collect([]);

        $tallyPendingPartySyncCount = 0;
        $tallyTotalSalesAmount = 0;
        $tallyTotalClosingBalance = 0;
        $tallyTotalCreditAmount = 0;

        try {
            if ($user) {
                $isMasterAdmin = $user->hasRole('master_admin');
            }

            // 1. Top Cards: Daily Pulse
        $userIds = \App\Models\User::where('reporting_to', $user->id)->pluck('id')->toArray();
        $userIds[] = $user->id;

        $todaysActiveTripCount = \App\Models\Trip::when(!$isMasterAdmin, function ($q) use ($userIds) {
            $q->whereIn('user_id', $userIds);
        })->whereNotIn('status', ['completed', 'denied'])->whereNull('end_time')->whereDate('trip_date', today())->count();
        
        $todaysOrderCount = \App\Models\Order::when(!$isMasterAdmin, function ($q) use ($userIds) {
            $q->whereIn('user_id', $userIds);
        })->whereDate('created_at', today())->count();
        
        $todaysOrderValue = \App\Models\OrderItem::whereHas('order', function($q) use ($isMasterAdmin, $userIds) {
            $q->whereDate('created_at', today());
            if (!$isMasterAdmin) {
                $q->whereIn('user_id', $userIds);
            }
        })->sum('total_price');
        
        $todaysPaymentCollection = \App\Models\PartyPayment::when(!$isMasterAdmin, function ($q) use ($userIds) {
            $q->whereIn('user_id', $userIds);
        })->whereDate('payment_date', today())->sum('amount');
        
        $todaysPartyVisits = \App\Models\PartyVisit::when(!$isMasterAdmin, function ($q) use ($userIds) {
            $q->whereIn('user_id', $userIds);
        })->whereDate('visited_date', today())->count();

        // 2. Middle Cards: State-wise groupings
        $companyCount = \App\Models\Company::count();
        if ($companyCount == 1) {
            $company = \App\Models\Company::first();
            $companyStates = $company && $company->state ? array_map('intval', explode(',', $company->state)) : [];
            $states = \App\Models\State::where('status', 1)
                        ->whereIn('id', $companyStates)
                        ->get();
        } else {
            $states = \App\Models\State::where('status', 1)->get();
        }
        
        // Partywise Outstanding
        $outstandingByState = \App\Models\TallyOpeningClosing::join('customers', 'tally_opening_closings.master_id', '=', 'customers.party_code')
            ->groupBy('customers.state_id')
            ->selectRaw('customers.state_id, SUM(closing_balance_amt) as total')
            ->get()
            ->pluck('total', 'state_id');

        // TA-DA Info (Using Slab logic from state-wise report)
        $tadaTrips = \App\Models\Trip::with(['user'])
            ->where('approval_status', 'approved')
            ->whereDate('trip_date', today())
            ->get();
        
        $tadaUserIds = $tadaTrips->pluck('user_id')->unique();
        $expensesByUserId = \App\Models\Expense::whereIn('user_id', $tadaUserIds)
            ->whereDate('bill_date', today())
            ->where('approval_status', 'Approved')
            ->select('user_id', \DB::raw('SUM(amount) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $tadaByState = [];
        foreach ($tadaTrips as $item) {
            if (!$item->user || !$item->user->state_id) continue;
            $stateId = $item->user->state_id;

            $slabType = $item->user->slab ?? "";
            $da_amount = null;
            $ta_amount = null;

            if ($slabType == "Slab Wise") {
                $da_amount = \App\Models\TaDaTourSlab::where('tour_type_id', $item->tour_type)->whereNull('user_id')->where('designation_id', $item->user->slab_designation_id)->first();
                $ta_amount = \App\Models\TaDaVehicleSlab::where('travel_mode_id', $item->travel_mode)->whereNull('user_id')->where('designation_id', $item->user->slab_designation_id)->first();
            }

            if ($slabType == "Individual") {
                $da_amount = \App\Models\TaDaTourSlab::where('tour_type_id', $item->tour_type)->where('user_id', $item->user->id)->first();
                $ta_amount = \App\Models\TaDaVehicleSlab::where('travel_mode_id', $item->travel_mode)->where('user_id', $item->user->id)->first();
            }

            $expense = $expensesByUserId->get($item->user_id, 0);

            $slabInfo = null;
            if ($slabType == 'Individual') {
                $slabInfo = \App\Models\TaDaSlab::where('user_id', $item->user->id)->first();
            } else {
                $slabInfo = \App\Models\TaDaSlab::whereNull('user_id')->first();
            }
            $limitEnabled = $slabInfo ? $slabInfo->travel_mode_enabled : 0;
            $limitValue = $slabInfo ? $slabInfo->travel_mode_limit : 0;

            $travelKm = ((float)$item->end_km - (float)$item->starting_km);

            if ($limitEnabled == 1 && $travelKm < $limitValue && $item->trip_limit_override == 0) {
                $ta = 0; $da = 0;
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

            $total = $ta + $da + $expense;
            
            if (!isset($tadaByState[$stateId])) {
                $tadaByState[$stateId] = 0;
            }
            $tadaByState[$stateId] += $total;
        }

        // Payment Credit from Field App
        $paymentCreditByStateArray = \App\Models\PartyPayment::join('customers', 'party_payments.customer_id', '=', 'customers.id')
            ->select('customers.state_id', \DB::raw('SUM(party_payments.amount) as total'))
            ->whereDate('party_payments.payment_date', today())
            ->groupBy('customers.state_id')
            ->pluck('total', 'state_id')->toArray();

        // Tally Payment Credit
        $tallyCredits = \App\Models\TallyPartywisePaymentCredit::join('customers', 'tally_partywise_payment_credits.party_name', '=', 'customers.party_code')
            ->select('customers.state_id', \DB::raw('SUM(tally_partywise_payment_credits.credit_amount) as total'))
            ->whereDate('tally_partywise_payment_credits.payment_date', today())
            ->groupBy('customers.state_id')
            ->pluck('total', 'state_id')->toArray();

        foreach($tallyCredits as $stateId => $amount) {
            if(!isset($paymentCreditByStateArray[$stateId])) {
                $paymentCreditByStateArray[$stateId] = 0;
            }
            $paymentCreditByStateArray[$stateId] += $amount;
        }
        $paymentCreditByState = collect($paymentCreditByStateArray);

        // Target vs Achievement (Financial Year)
        $currentMonthRaw = now()->format('n');
        if ($currentMonthRaw >= 4) {
            $startYear = now()->format('Y');
            $endYear = now()->format('Y') + 1;
            $fy = now()->format('Y') . '-' . (now()->format('y') + 1);
        } else {
            $startYear = now()->format('Y') - 1;
            $endYear = now()->format('Y');
            $fy = (now()->format('Y') - 1) . '-' . now()->format('y');
        }

        $startDate = "$startYear-04-01 00:00:00";
        $endDate = "$endYear-03-31 23:59:59";

        $budgets = \App\Models\Budget::whereIn('state_id', $states->pluck('id'))
            ->where('financial_year', $fy)
            ->get();
            
        $targetVsAchByState = [];

        foreach ($budgets as $budget) {
            $stateId = $budget->state_id;
            
            if (!isset($targetVsAchByState[$stateId])) {
                $targetVsAchByState[$stateId] = ['target' => 0, 'achievement' => 0];
            }

            // Total target for the year
            $targetVsAchByState[$stateId]['target'] += $budget->total_target ?? 0;

            // Total achievement for the year
            $achive = \App\Models\OrderItem::whereHas('order', function($q) use ($budget, $startDate, $endDate) {
                $q->where('user_id', $budget->user_id)
                  ->where('status', 'approved')
                  ->whereBetween('created_at', [$startDate, $endDate]);
            })->sum('total_price');

            $targetVsAchByState[$stateId]['achievement'] += $achive;
        }

        $statesData = $states->map(function($state) use ($tadaByState, $outstandingByState, $paymentCreditByState, $targetVsAchByState) {
            $target = $targetVsAchByState[$state->id]['target'] ?? 0;
            $achievement = $targetVsAchByState[$state->id]['achievement'] ?? 0;
            $percent = $target > 0 ? round(($achievement / $target) * 100, 1) : 0;

            return (object)[
                'id' => $state->id,
                'name' => $state->name,
                'target' => $target,
                'achievement' => $achievement,
                'target_ach' => $percent,
                'outstanding' => $outstandingByState->get($state->id, 0),
                'tada' => $tadaByState[$state->id] ?? 0,
                'payment_credit' => $paymentCreditByState->get($state->id, 0),
            ];
        })->filter(function($s) {
            return $s->outstanding > 0 || $s->tada > 0 || $s->payment_credit > 0;
        });

        // 3. Bottom Table: Employee Daily Logs
        $activeTripUserIds = \App\Models\Trip::whereDate('trip_date', today())->pluck('user_id');
        
        $employees = \App\Models\User::where('is_active', 1)
            ->where('id', '!=', 1) // Skip master admin
            ->whereIn('id', $activeTripUserIds)
            ->when(!$isMasterAdmin, function ($q) use ($userIds) {
                $q->whereIn('id', $userIds);
            })
            ->get();

        // Fetch today's sessions for employees
        $sessions = \App\Models\UserSession::whereDate('login_at', today())
            ->whereIn('user_id', $employees->pluck('id'))
            ->get()
            ->groupBy('user_id');

        // Fetch today's trips for displaying in the table
        $todayTrips = \App\Models\Trip::with('customers')->whereDate('trip_date', today())
            ->whereIn('user_id', $employees->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $employeeData = $employees->map(function($emp) use ($sessions, $todayTrips) {
            $userSessions = $sessions->get($emp->id);
            
            $userTrip = $todayTrips->get($emp->id)?->first();
            
            // Calc login hours from trip
            $loginHrs = '-';
            if ($userTrip && $userTrip->start_time) {
                $startCarbon = \Carbon\Carbon::parse($userTrip->start_time);
                
                if ($userTrip->end_time && $userTrip->status === 'completed') {
                    $endCarbon = \Carbon\Carbon::parse($userTrip->end_time);
                    $h = (int) $startCarbon->diffInHours($endCarbon);
                    $m = (int) ($startCarbon->diffInMinutes($endCarbon) % 60);
                    $hours = str_pad($h, 2, '0', STR_PAD_LEFT);
                    $minutes = str_pad($m, 2, '0', STR_PAD_LEFT);
                    $loginHrs = "{$hours}:{$minutes} Hrs";
                } else {
                    $h = (int) $startCarbon->diffInHours(now());
                    $m = (int) ($startCarbon->diffInMinutes(now()) % 60);
                    $hours = str_pad($h, 2, '0', STR_PAD_LEFT);
                    $minutes = str_pad($m, 2, '0', STR_PAD_LEFT);
                    $loginHrs = "{$hours}:{$minutes} Hrs (Active)";
                }
            }

            return (object)[
                'id' => $emp->id,
                'name' => $emp->name,
                'day_start' => $userTrip && $userTrip->start_time ? \Carbon\Carbon::parse($userTrip->start_time)->format('h:i A') : '-',
                'day_end' => $userTrip && $userTrip->end_time ? \Carbon\Carbon::parse($userTrip->end_time)->format('h:i A') : '-',
                'login_hrs' => $loginHrs,
                'tour_plan' => $userTrip ? ($userTrip->place_to_visit ?? 'Active Tour') : '-',
                'places_visited' => $userTrip ? $userTrip->customers : collect([]),
                'trip_id' => $userTrip ? $userTrip->id : null,
            ];
        });

        // 4. Tally Overview Metrics
        $tallyPendingPartySyncCount = \App\Models\TallyPartySync::whereNotExists(function ($query) {
            $query->select(\Illuminate\Support\Facades\DB::raw(1))
                  ->from('customers')
                  ->whereColumn('customers.party_code', 'tally_party_syncs.master_id')
                  ->whereNotNull('customers.party_code');
        })->count();
        $tallyTotalSalesAmount = \App\Models\TallySalesBill::sum('grand_total');
        $tallyTotalClosingBalance = \App\Models\TallyOpeningClosing::sum('closing_balance_amt');
        $tallyTotalCreditAmount = \App\Models\TallyPartywisePaymentCredit::sum('credit_amount');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Dashboard exception: " . $e->getMessage());
        }

        return view('admin.dashboard', compact(
            'todaysActiveTripCount',
            'todaysOrderCount',
            'todaysOrderValue',
            'todaysPaymentCollection',
            'todaysPartyVisits',
            'statesData',
            'employeeData',
            'isMasterAdmin',
            'tallyPendingPartySyncCount',
            'tallyTotalSalesAmount',
            'tallyTotalClosingBalance',
            'tallyTotalCreditAmount'
        ));
    }


    public function create()
    {
        $defaultDb = Config::get('database.default');
        $defaultDbName = DB::connection()->getDatabaseName();
        $companyCount = Company::count();
        $company = null;

        if($companyCount == 1){
            $company = Company::first();
        }
        $apk = DB::connection('mysql')->table('apk_uploads')->orderByDesc('id')->first();
        
        return view('admin.login',compact('company','apk'));
    }
    
    public function store(LoginRequest $request)
    {
        $credentials = $request->only('mobile', 'password');

        if (Auth::attempt($credentials)) {

            $user = Auth::user();
            if ($user->is_active == 0) {
                Auth::logout();
                return redirect()->back()->with('error_message', 'Your account is inactive. Please contact support.');
            }

            $companyCount = Company::count();
            $company = Company::first();

            if($companyCount == 1){
                if ($company && $company->is_active == 0) {
                    Auth::logout();
                    return redirect()->back()->with('error_message', 'Your company account has been deactivated.');
                }

                // 3️⃣ Validity expiry check
                if ($company && !empty($company->validity_upto)) {
                    if (now()->greaterThan($company->validity_upto)) {
                        Auth::logout();
                        return redirect()->back()->with('error_message','Your company subscription has expired. Please contact administrator.');
                    }
                }
            }
            // Remember me
            if (!empty($request->remember)) {
                setcookie("mobile", $credentials["mobile"], time() + 3600);
                setcookie("password", $credentials["password"], time() + 3600);
            } else {
                setcookie("mobile", "", time() - 3600);
                setcookie("password", "", time() - 3600);
            }

            $request->session()->regenerate();

            // Session logging
            $existingSession = UserSession::where('user_id', $user->id)->whereNull('logout_at')->where('platform', 'web')->latest()->first();

            if ($existingSession) {
                $existingSession->update([
                    'logout_at'        => now(),
                    'session_duration' => $existingSession->login_at->diffInSeconds(now()),
                ]);
            }

            UserSession::create([
                'user_id'    => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'platform'   => 'web',
                'login_at'   => now(),
            ]);

            return redirect()->route('admin.dashboard');

        } else {
            return redirect()->back()->with('error_message', 'Invalid Mobile or Password.');
        }
    }

    public function edit(Admin $admin)
    {
        //
    }

    public function update(Request $request, Admin $admin)
    {
        //
    }

    public function destroy()
    {
        $user = Auth::user();

        if ($user) {
            $user->last_seen = null;
            $user->save();

            $session = UserSession::where('user_id', $user->id)->whereNull('logout_at')->where('platform', 'web')->latest()->first();

            if ($session) {
                $session->update([
                    'logout_at'        => now(),
                    'session_duration' => $session->login_at->diffInSeconds(now()),
                ]);
            }
        }

        Auth::logout();
        return redirect()->route('admin.login');
    }

    public function getUserSessionHistory(Request $request, $userId)
    {
        $loggedInUser = Auth::user();
        $targetUser   = User::find($userId);

        if (!$targetUser) {
            return '<p class="text-danger">User not found.</p>';
        }

        $isMasterAdmin = $loggedInUser->hasRole('master_admin');

        if (!$isMasterAdmin && $loggedInUser->company_id !== $targetUser->company_id) {
            return '<p class="text-danger">Unauthorized access. You can only view session logs of your own company\'s users.</p>';
        }

        $sessions = UserSession::where('user_id', $userId)->whereDate('login_at', now()->toDateString())->orderByDesc('login_at')->get();
        if ($sessions->isEmpty()) {
            return '<p class="text-muted">No session records found.</p>';
        }

        $todayTotalSeconds = UserSession::where('user_id', $userId)->whereNotNull('session_duration')->whereDate('login_at', now()->toDateString())->sum('session_duration');

        $html = '<p><strong>Total Active Time Today:</strong> ' . gmdate('H:i:s', $todayTotalSeconds) . '</p>';

        $html .= '<table class="table table-bordered table-striped">';
        $html .= '<thead><tr>
                    <th>Platform</th>
                    <th>Login Time</th>
                    <th>Logout Time</th>
                    <th>Duration</th>
                </tr></thead><tbody>';

        foreach ($sessions as $session) {
            $platform = ucfirst($session->platform ?? 'N/A');
            $login    = $session->formatted_login_at;
            $logout   = $session->formatted_logout_at;
            $duration = $session->formatted_duration;

            // ✅ Add platform in each row
            $html .= "<tr>
                        <td>{$platform}</td>
                        <td>{$login}</td>
                        <td>{$logout}</td>
                        <td>{$duration}</td>
                    </tr>";
        }

        $html .= '</tbody></table>';

        return $html;
    }

    public function changePassword(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'string', 'confirmed'],
        ], [
            'new_password.confirmed' => 'New password and confirm password do not match.',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        if ($user->hasRole('sub_admin')) {
            $company = Company::first(); 
            if ($company && !empty($company->tenant_id)) {
                $company->update(['password'=> $request->new_password]);
                try {
                    DB::connection('central')->table('companies')->where('tenant_id', $company->tenant_id)->update(['password' => $request->new_password,'updated_at' => now()]);
                } catch (\Exception $e) {
                    \Log::error('Central password update failed: ' . $e->getMessage());
                }
            }
        }

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function updateState()
    {
        // 1️⃣ Update State
        $state = State::findOrFail(20);
        $state->update([
            'name' => 'Rajasthan'
        ]);

        // 2️⃣ Rajasthan District → Tehsil Data (UNCHANGED)
        $rajasthanData = [
            'Ajmer' => [
                'Ajmer','Beawar','Bhinay','Kekri','Kishangarh',
                'Masuda','Nasirabad','Peesangan','Sarwar',
            ],
            'Alwar' => [
                'Alwar','Bansur','Behror','Kathumar','Kishangarh Bas',
                'Kotkasim','Lachhmangarh','Mandawar','Rajgarh',
                'Ramgarh','Thanagazi','Tijara',
            ],
            'Banswara' => [
                'Bagidora','Garhi','Ghatol','Kushalgarh','Banswara',
            ],
            'Baran' => [
                'Antah','Atru','Baran','Chhabra',
                'Chhipabarod','Kishanganj','Mangrol','Shahbad',
            ],
            'Barmer' => [
                'Barmer','Baytoo','Chohtan','Gudha Malani',
                'Pachpadra','Ramsar','Sheo','Siwana',
                'Dhorimana','Sindhari',
            ],
            'Bharatpur' => [
                'Bayana','Deeg','Kaman','Kumher','Nadbai',
                'Nagar','Pahari','Rupbas','Weir','Bharatpur',
            ],
            'Bhilwara' => [
                'Asind','Banera','Beejoliya','Bhilwara','Hurda',
                'Jahazpur','Kotri','Mandal','Mandalgarh',
                'Raipur','Sahara','Shahpura',
            ],
            'Bikaner' => [
                'Bikaner','Chhatargarh','Khajuwala','Kolayat',
                'Lunkaransar','Nokha','Poogal','Sridungargarh',
            ],
            'Bundi' => [
                'Bundi','Hindoli','Indragarh',
                'Keshoraipatan','Nainwa','Taleda',
            ],
            'Chittaurgarh' => [
                'Bari Sadri','Begun','Bhadesar','Chittaurgarh',
                'Dungla','Gangrar','Kapasan',
                'Nimbahera','Rashmi','Rawatbhata',
            ],
            'Churu' => [
                'Churu','Rajgarh','Ratangarh',
                'Sardarshahar','Sujangarh','Taranagar',
            ],
            'Dausa' => [
                'Baswa','Dausa','Lalsot','Mahwa','Sikrai',
            ],
            'Dhaulpur' => [
                'Bari','Baseri','Dhaulpur','Rajakhera','Sepau',
            ],
            'Dungarpur' => [
                'Aspur','Bichhiwara','Dungarpur','Sagwara','Simalwara',
            ],
            'Ganganagar' => [
                'Anupgarh','Ganganagar','Gharsana','Karanpur',
                'Padampur','Raisinghnagar','Sadulsahar',
                'Suratgarh','Vijainagar',
            ],
            'Hanumangarh' => [
                'Bhadra','Hanumangarh','Nohar',
                'Pilibanga','Rawatsar','Sangaria','Tibbi',
            ],
            'Jaipur' => [
                'Amber','Bassi','Chaksu','Chomu','Jamwa Ramgarh',
                'Jaipur','Kotputli','Mauzamabad','Phagi',
                'Phulera (Hq.Sambhar)','Sanganer','Shahpura','Viratnagar',
            ],
            'Jaisalmer' => [
                'Fatehgarh','Jaisalmer','Pokaran',
            ],
            'Jalor' => [
                'Ahore','Bagora','Bhinmal','Jalor',
                'Raniwara','Sanchore','Sayla',
            ],
            'Jhalawar' => [
                'Aklera','Gangdhar','Jhalrapatan',
                'Khanpur','Manohar Thana','Pachpahar','Pirawa',
            ],
            'Jhunjhunun' => [
                'Buhana','Chirawa','Jhunjhunun',
                'Khetri','Nawalgarh','Udaipurwati',
            ],
            'Jodhpur' => [
                'Balesar','Bap','Bhopalgarh','Bilara',
                'Jodhpur','Luni','Osian','Phalodi','Shergarh',
            ],
            'Karauli' => [
                'Hindaun','Karauli','Mandrail',
                'Nadbai','Sapotra','Todabhim',
            ],
            'Kota' => [
                'Digod','Ladpura','Pipalda',
                'Ramganj Mandi','Sangod',
            ],
            'Nagaur' => [
                'Degana','Didwana','Jayal','Kheenvsar',
                'Ladnu','Makrana','Merta','Nagaur','Nawa','Parbatsar',
            ],
            'Pali' => [
                'Bali','Desuri','Jaitaran','Marwar Junction',
                'Pali','Raipur','Rohat','Sojat','Sumerpur',
            ],
            'Pratapgarh' => [
                'Arnod','Chhoti Sadri','Dhariawad',
                'Peepalkhoont','Pratapgarh',
            ],
            'Rajsamand' => [
                'Amet','Bhim','Deogarh','Kumbhalgarh',
                'Nathdwara','Railmagra','Rajsamand',
            ],
            'Sawai Madhopur' => [
                'Bamanwas','Bonli','Chauth Ka Barwara',
                'Gangapur','Khandar','Malarna Doongar','Sawai Madhopur',
            ],
            'Sikar' => [
                'Danta Ramgarh','Fatehpur','Lachhmangarh',
                'Neem-Ka-Thana','Sikar','Sri Madhopur',
            ],
            'Sirohi' => [
                'Abu Road','Pindwara','Reodar','Sheoganj','Sirohi',
            ],
            'Tonk' => [
                'Deoli','Malpura','Niwai','Peeplu',
                'Tonk','Todaraisingh','Uniara',
            ],
            'Udaipur' => [
                'Badgaon','Bhindar','Dhariawad','Girwa','Gogunda',
                'Jhadol','Kanor','Kherwara','Kotda','Lasadiya',
                'Mavli','Rishabhdeo','Salumbar','Sarada',
                'Semari','Vallabhnagar',
            ],
        ];

        // 3️⃣ Insert District & Tehsil (OPTIMIZED)
        foreach ($rajasthanData as $districtName => $tehsils) {

            $district = District::firstOrCreate(
                [
                    'name' => $districtName,
                    'state_id' => $state->id,
                ],
                [
                    'country_id' => 1,
                ]
            );

            $tehsilRows = [];
            foreach ($tehsils as $tehsilName) {
                $tehsilRows[] = [
                    'name' => $tehsilName,
                    'district_id' => $district->id,
                    'state_id' => $state->id,
                    'country_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // 🔥 ONE QUERY PER DISTRICT
            Tehsil::insertOrIgnore($tehsilRows);
        }

        return response()->json([
            'status' => true,
            'message' => 'Rajasthan State, Districts & Tehsils inserted successfully'
        ]);
    }

    public function updatePermission(){
        $permissions = [
                // 'view_users',
                // 'create_users',
                // 'edit_users',
                // 'view_roles',
                // 'create_roles',
                // 'edit_roles',
                // 'view_permissions',
                // 'create_permissions',
                // 'edit_permissions',
                // 'delete_permissions',
                // 'view_customers',
                // 'create_customers',
                // 'edit_customers',
                // 'delete_customers',
                // 'toggle_customers',
                // 'view_products',
                // 'create_products',
                // 'edit_products',
                // 'delete_products',
                // 'toggle_users',
                // 'view_companies',
                // 'create_companies',
                // 'edit_companies',
                // 'delete_companies',
                // 'view_budget_plan',
                // 'create_budget_plan',
                // 'edit_budget_plan',
                // 'approvals_budget_plan',
                // 'reject_budget_plan',
                // 'verify_budget_plan',
                // 'remove_review_budget_plan',
                // 'view_monthly_plan',
                // 'create_monthly_plan',
                // 'edit_monthly_plan',
                // 'approvals_monthly_plan',
                // 'reject_monthly_plan',
                // 'verify_monthly_plan',
                // 'remove_review_monthly_plan',
                // 'view_plan_vs_achievement',
                // 'create_plan_vs_achievement',
                // 'edit_plan_vs_achievement',
                // 'approvals_plan_vs_achievement',
                // 'reject_plan_vs_achievement',
                // 'verify_plan_vs_achievement',
                // 'remove_review_plan_vs_achievement',
                // 'view_party_visit',
                // 'view_order',
                // 'edit_order',
                // 'delete_order',
                // 'approvals_order',
                // 'reject_order',
                // 'dispatch_order',
                // 'view_order_report',
                // 'view_stock',
                // 'view_stock_ageing',
                // 'view_emp_on_map',
                // 'view_daily_trip',
                // 'edit_daily_trip',
                // 'delete_daily_trip',
                // 'approvals_daily_trip',
                // 'reject_daily_trip',
                // 'view_attendance',
                // 'create_monthly_attendance_report',
                // 'view_monthly_attendance_report',
                // 'approvals_monthly_attendance_report',
                // 'create_leave_report',
                // 'view_leave_report',
                // 'edit_leave_report',
                // 'view_expense',
                // 'edit_expense',
                // 'delete_expense',
                // 'approvals_expense',
                // 'reject_expense',
                // 'create_genrate_monthly_expense',
                // 'view_genrate_monthly_expense',
                // 'edit_genrate_monthly_expense',
                // 'delete_genrate_monthly_expense',
                // 'approvals_genrate_monthly_expense',
                // 'reject_genrate_monthly_expense',
                // 'view_ta_da_report',
                // 'view_daily_farm_demo',
                // 'edit_daily_farm_demo',
                // 'delete_daily_farm_demo',
                // 'view_monthly_farm_demo_report',
                // 'view_all_trip',
                // // 'create_all_trip',
                // 'edit_all_trip',
                // 'delete_all_trip',
                // 'approvals_all_trip',
                // 'reject_all_trip',
                // 'logs_all_trip',
                // 'view_trip_types',
                // 'create_trip_types',
                // 'edit_trip_types',
                // 'view_travel_modes',
                // 'create_travel_modes',
                // 'edit_travel_modes',
                // 'view_trip_purposes',
                // 'create_trip_purposes',
                // 'edit_trip_purposes',
                // 'view_designations',
                // 'create_designations',
                // 'edit_designations',
                // 'delete_designations',
                // 'view_states',
                // 'create_states',
                // 'edit_states',
                // 'view_districts',
                // 'create_districts',
                // 'edit_districts',
                // 'view_talukas',
                // 'create_talukas',
                // 'edit_talukas',
                // 'view_vehicle_types',
                // 'create_vehicle_types',
                // 'edit_vehicle_types',
                // 'delete_vehicle_types',
                // 'view_depo_master',
                // 'create_depo_master',
                // 'edit_depo_master',
                // 'delete_depo_master',
                // 'view_party_master',
                // 'create_party_master',
                // 'edit_party_master',
                // 'delete_party_master',
                // 'view_holiday_master',
                // 'create_holiday_master',
                // 'edit_holiday_master',
                // 'delete_holiday_master',
                // 'view_leave_master',
                // 'create_leave_master',
                // 'edit_leave_master',
                // 'delete_leave_master',
                // 'view_ta_da',
                // 'create_ta_da',
                // 'edit_ta_da',
                // 'delete_ta_da',
                // 'view_ta_da_bill_master',
                // 'create_ta_da_bill_master',
                // 'edit_ta_da_bill_master',
                // 'delete_ta_da_bill_master',
                // 'view_sales_product_master',
                // 'create_sales_product_master',
                // 'edit_sales_product_master',
                // 'delete_sales_product_master',
                // 'view_technical_master',
                // 'create_technical_master',
                // 'edit_technical_master',
                // 'delete_technical_master',
                // 'view_product_category',
                // 'create_product_category',
                // 'edit_product_category',
                // 'delete_product_category',
                // 'view_product_price',
                // 'create_product_price',
                // 'edit_product_price',
                // 'delete_product_price',
                // 'view_product_collection',
                // 'create_product_collection',
                // 'edit_product_collection',
                // 'delete_product_collection',
                // 'view_price_list_master',
                // 'create_price_list_master',
                // 'edit_price_list_master',
                // 'delete_price_list_master',
                // 'view_list_of_all_price_list',
                // 'view_upload_brochure',
                // 'create_upload_brochure',
                // 'edit_upload_brochure',
                // 'delete_upload_brochure',
                // 'view_vehicle_master',
                // 'create_vehicle_master',
                // 'edit_vehicle_master',
                // 'delete_vehicle_master',
                // 'view_new_party',
                // 'approvals_new_party',
                // 'reject_new_party',
                // 'view_party_payment',
                // 'approvals_party_payment',
                // 'view_party_performance',
                // 'view_party_ledger',
                // 'view_sales_return',
                // 'edit_sales_return',
                // 'delete_sales_return',
                'approvals_sales_return'
            ];

            foreach ($permissions as $permissionName) {
                Permission::firstOrCreate(
                    ['name' => $permissionName],
                    ['guard_name' => 'web']
                );
            }
        
            // app()[PermissionRegistrar::class]->forgetCachedPermissions();
            // $subAdminRole = Role::firstOrCreate(['name' => 'sub_admin', 'guard_name' => 'web']);
            // $allPermissions = Permission::all();
            // $subAdminRole->syncPermissions($allPermissions);
            // $user = User::find(1);
            
            // // dd($subAdminRole->permissions->pluck('name'));
            // $user->assignRole('sub_admin');
            // $user->refresh();
            

            // dd($user->roles->pluck('name'));
    }

}
