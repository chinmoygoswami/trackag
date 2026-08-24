<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Designation;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeCreatedException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


use App\Models\Tenant;

class CompanyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_companies')->only(['index','show']);
        $this->middleware('permission:create_companies')->only(['create','store']);
        $this->middleware('permission:edit_companies')->only(['edit','update']);
        $this->middleware('permission:delete_companies')->only(['destroy']);
    }
    
    public function index()
    {
        $user = Auth::user();
        $companies = Company::get();
        return view('admin.companies.index', compact('companies'));
    }

    public function create()
    {
        $authUser = auth()->user();

        $roles = $authUser->user_level === 'master_admin' ? Role::all() : Role::where('company_id', $authUser->company_id)->get();

        $companies = $authUser->user_level === 'master_admin' ? Company::all() : collect();

        $users = User::when($authUser->user_level !== 'master_admin', function ($query) use ($authUser) {
            $query->where('company_id', $authUser->company_id);
        })->get();

        $designations = $authUser->user_level === 'master_admin' ? Designation::all() : Designation::where('company_id', $authUser->company_id)->get();

        $state = State::where('status', 1)->get();

        return view('admin.companies.create', [
            'states' => State::all(),
            'designations' => $designations,
            'users' => $users, // ✅ added here
            'roles' => $roles,
            'permissions' => Permission::all(),
            'authUser' => $authUser,
            'companies' => $companies,
            'state' => $state
        ]);
    }

    public function store(StoreCompanyRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $subdomain = Str::slug($validated['code'], '-');
        $centralDomain = env('CENTRAL_DOMAIN', 'test');
        $fullDomain = $subdomain . '.' . $centralDomain;
        $tenancyDbName = 'tenant_' . Str::slug($subdomain, '_');

        $company = null;
        $tenant = null;

        try {
            // DB::statement("CREATE DATABASE IF NOT EXISTS `$tenancyDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                $company = Company::create([
                    'name' => $validated['name'],
                    'code' => $validated['code'] ?? null,
                    'owner_name' => $validated['owner_name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'password' => $validated['user_password'] ?? null,
                    'gst_number' => $validated['gst_number'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'contact_no' => $validated['contact_no'] ?? null,
                    'contact_no2' => $validated['contact_no2'] ?? null,
                    'telephone_no' => $validated['telephone_no'] ?? null,
                    'website' => $validated['website'] ?? null,
                    'state' => !empty($validated['state']) ? implode(',', $validated['state']) : null,
                    'product_name' => $validated['product_name'] ?? null,
                    'subscription_type' => $validated['subscription_type'] ?? null,
                    'tally_configuration' => $validated['tally_configuration'] ?? 0,
                    'logo' => $validated['logo'] ?? null,
                    'subdomain' => $fullDomain,
                    'start_date' => $validated['start_date'] ?? null,
                    'validity_upto' => $validated['validity_upto'] ?? null,
                    'user_assigned' => $validated['user_assigned'] ?? null,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s')
                ]);

                // 3️⃣ Create tenant record in central DB
                $tenant = Tenant::create([
                    'id' => (string) Str::uuid(),
                    'data' => [
                        'company_id' => $company->id,
                        'database' => $tenancyDbName,
                    ],
                    'tenancy_db_name' => $tenancyDbName,
                ]);

                $tenant->domains()->create(['domain' => $fullDomain]);
                $company->update(['tenant_id' => $tenant->id]);

            tenancy()->initialize($tenant);

            // 5️⃣ Setup tenant DB connection dynamically
            $tenantConnection = config('database.connections.tenant');
            $tenantConnection['database'] = $tenancyDbName;
            config(['database.connections.tenant' => $tenantConnection]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            // 6️⃣ Run tenant migrations
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            // 7️⃣ Insert tenant record inside tenant DB to satisfy FK
            DB::connection('tenant')->table('tenants')->insert([
                'id' => $tenant->id,
                'data' => json_encode($tenant->data),
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

            // 8️⃣ Insert company inside tenant DB (like old version)
            $tenantCompanyData = [
                'name' => $company->name,
                'code' => $company->code,
                'owner_name' => $company->owner_name,
                'email' => $company->email,
                'password' => $validated['user_password'] ?? null,
                'gst_number' => $company->gst_number,
                'address' => $company->address,
                'contact_no' => $company->contact_no,
                'contact_no2' => $company->contact_no2,
                'telephone_no' => $company->telephone_no,
                'website' => $company->website,
                'state' => $company->state,
                'product_name' => $company->product_name,
                'subscription_type' => $company->subscription_type,
                'tally_configuration' => $company->tally_configuration,
                'logo' => $company->logo,
                'subdomain' => $fullDomain,
                'start_date' => $validated['start_date'] ?? null,
                'validity_upto' => $validated['validity_upto'] ?? null,
                'user_assigned' => $validated['user_assigned'] ?? null,
                'tenant_id' => $tenant->id,
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ];
            DB::connection('tenant')->table('companies')->insert($tenantCompanyData);

            // 9️⃣ Run only important seeders
            $seederOrder = [
                'CountrySeeder',
                'StateSeeder',
                'DistrictSeeder',
                'CitiesSeeder',
                'TehsilSeeder',
                // 'RoleSeeder',
                'PermissionSeeder',
                'LookupTablesSeeder',
                'CropCategoryWithSubSeeder'
            ];

            DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=0;');
            try {
                foreach ($seederOrder as $seederClass) {
                    $class = 'Database\\Seeders\\' . $seederClass;
                    if (class_exists($class)) {
                        Artisan::call('db:seed', [
                            '--class' => $class,
                            '--database' => 'tenant',
                            '--force' => true,
                        ]);
                    }
                }
            } finally {
                DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            // 🔟 Create default admin user inside tenant DB
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['user_password']),
                'mobile' => $validated['contact_no'] ?? null,
                'address' => $validated['address'] ?? null,
                'user_level' => 'company_admin',
                'is_active' => true,
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ];

            $userId = DB::connection('tenant')->table('users')->insertGetId($userData);

            // Assign role & all permissions
            $tenantUserModel = new \App\Models\User();
            $tenantUserModel->setConnection('tenant');
            $user = $tenantUserModel->find($userId);

            if ($user) {
                $roleModel = new \Spatie\Permission\Models\Role();
                $roleModel->setConnection('tenant');

                $permissionModel = new \Spatie\Permission\Models\Permission();
                $permissionModel->setConnection('tenant');

                $role = $roleModel->firstOrCreate(['name' => 'sub_admin','guard_name' => 'web']);
                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
                // app(PermissionRegistrar::class)->forgetCachedPermissions();
                // $permissions = $permissionModel->pluck('name')->toArray();
                $permissions = $permissionModel->get();
                if (!empty($permissions)) {
                    $role->syncPermissions($permissions);
                }
                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
                $user->assignRole($role->name);
            }

            return redirect()->route('companies.index')
                ->with('success', "Company & Admin created successfully. Domain: {$fullDomain}");
        } catch (\Exception $e) {
            Log::error('Company/Tenant creation failed: ' . $e->getMessage());

            // Cleanup
            if ($tenant) {
                try {
                    $tenant->delete();
                } catch (\Throwable $ex) {
                }
            }
            if ($company) {
                try {
                    $company->delete();
                } catch (\Throwable $ex) {
                }
            }
            try {
                DB::statement("DROP DATABASE IF EXISTS `$tenancyDbName`");
            } catch (\Throwable $ex) {
            }

            return back()->withInput()
                ->withErrors(['error' => 'Onboarding failed: ' . $e->getMessage()]);
        }
    }

    public function show(Company $company)
    {
        return view('admin.companies.show', compact('company'));
    }

    public function edit(Company $company)
    {
        $state = State::where('status', 1)->get();
        return view('admin.companies.edit', compact('company', 'state'));
    }

    public function update(StoreCompanyRequest $request, Company $company)
    {
        $this->authorizeCompanyAccess($company);

        $validated = $request->validated();

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('logos', 'public');
        }
        if (!empty($validated['state']) && is_array($validated['state'])) {
            $validated['state'] = implode(',', $validated['state']);
        }
        if (!empty($validated['user_password'])) {
            $validated['password'] = $validated['user_password'];
        }

        // ✅ Central DB update
        $company->update($validated);
        $tenant = $company->tenant_id;
        $tenantData = Tenant::where('id', $tenant)->first();

        if (!empty($tenantData->tenancy_db_name)) {
            $tenancyDbName = $tenantData->tenancy_db_name;

            $tenantConnection = config('database.connections.tenant');
            $tenantConnection['database'] = $tenancyDbName;
            config(['database.connections.tenant' => $tenantConnection]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            $tenantCompanyData = [
                'name' => $company->name,
                'code' => $company->code,
                'owner_name' => $company->owner_name,
                'email' => $company->email,
                'gst_number' => $company->gst_number,
                'address' => $company->address,
                'contact_no' => $company->contact_no,
                'contact_no2' => $company->contact_no2,
                'telephone_no' => $company->telephone_no,
                'website' => $company->website,
                'state' => $company->state,
                'product_name' => $company->product_name,
                'subscription_type' => $company->subscription_type,
                'tally_configuration' => $company->tally_configuration,
                'logo' => $company->logo,
                'subdomain' => $company->subdomain,
                'start_date' => $validated['start_date'] ?? $company->start_date,
                'validity_upto' => $validated['validity_upto'] ?? $company->validity_upto,
                'user_assigned' => $validated['user_assigned'] ?? $company->user_assigned,
                'updated_at' => now(),
            ];

            if (!empty($validated['user_password'])) {
                $tenantCompanyData['password'] = $validated['user_password'];
            }

            DB::connection('tenant')->table('companies')->where('tenant_id', $tenant)->update($tenantCompanyData);

            if (!empty($validated['user_password'])) {
                $adminUser = DB::connection('tenant')->table('users')->where('email', $company->email)->first();

                if ($adminUser) {
                    DB::connection('tenant')->table('users')->where('id', $adminUser->id)
                        ->update(['mobile' => $validated['contact_no'],'password' => Hash::make($validated['user_password']),'updated_at' => now()]);
                }
            }
        }

        return redirect()->route('companies.index')->with('success', 'Company & Tenant updated successfully.');
    }

    public function destroy(Company $company)
    {
        $this->authorizeMaster(); // Only master_admin can delete companies

        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Company deleted successfully.');
    }

    public function toggle($id)
    {
        $company = Company::findOrFail($id);
        $this->authorizeCompanyAccess($company);

        $company->is_active = !$company->is_active;
        $company->status = $company->is_active ? 'Active' : 'Inactive';
        $company->save();

        $tenantId = $company->tenant_id;
        $tenant = Tenant::where('id', $tenantId)->first();

        if ($tenant && !empty($tenant->tenancy_db_name)) {
            $tenancyDbName = $tenant->tenancy_db_name;
            $tenantConnection = config('database.connections.tenant');
            $tenantConnection['database'] = $tenancyDbName;

            config(['database.connections.tenant' => $tenantConnection]);

            DB::purge('tenant');
            DB::reconnect('tenant');

            DB::connection('tenant')->table('companies')->where('tenant_id', $tenantId)->update([
                    'is_active' => $company->is_active,
                    'status' => $company->status,
                    'updated_at' => now()
                ]);

        }

        return redirect()->route('companies.index')->with('success', 'Company status updated in central & tenant database.');
    }

    private function authorizeMaster()
    {
        $user = Auth::user();
        if (!$user->hasRole('master_admin')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function authorizeCompanyAccess(Company $company)
    {
        $user = Auth::user();

        if ($user->hasRole('master_admin')) {
            return;
        }

        if ($company->id !== $user->company_id) {
            abort(403, 'Unauthorized access to this company.');
        }
    }
}
