<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;

class TenantAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        // 1️⃣ Get company code
        $companyCode = $request->header('X-Company-Code');
        if (!$companyCode) {
            return response()->json(['message' => 'Company code missing'], 400);
        }

        // 2️⃣ Get company
        $company = Company::where('code', $companyCode)->first();
        if (!$company) {
            return response()->json(['message'=>'Invalid company code'], 404);
        }

        if (!$company->is_active) {
            return response()->json([
                'message' => 'Your company account has been deactivated.'
            ], 403);
        }

        // 3️⃣ Get tenant
        $tenant = Tenant::find($company->tenant_id);
        if (!$tenant) {
            return response()->json(['message'=>'Tenant not found'], 404);
        }

        // 4️⃣ Switch tenant DB
        config(['database.connections.tenant.database' => $tenant->tenancy_db_name]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        tenancy()->initialize($tenant);
        $request->attributes->set('company', $company);

        // 5️⃣ Get Bearer token
        $plainToken = $request->bearerToken();
        if (!$plainToken) {
            return response()->json(['message' => 'Bearer token missing'], 401);
        }

        // 6️⃣ Token format: id|plain_token
        $parts = explode('|', $plainToken);
        $tokenId = $parts[0] ?? null;
        $tokenPlain = $parts[1] ?? null;

        if (!$tokenId || !$tokenPlain) {
            return response()->json(['message'=>'Invalid token format'], 401);
        }

        // 7️⃣ Check token in tenant DB
        $tokenRecord = DB::connection('tenant')
            ->table('personal_access_tokens')
            ->where('id', $tokenId)
            ->first();

        if (!$tokenRecord || !hash_equals($tokenRecord->token, hash('sha256', $tokenPlain))) {
            return response()->json(['message'=>'Invalid or expired token'], 401);
        }

        // 8️⃣ Get user
        $user = User::on('tenant')->find($tokenRecord->tokenable_id);
        if (!$user) {
            return response()->json(['message'=>'User not found'], 401);
        }

        // 🔴 9️⃣ EXPIRY CHECK + AUTO LOGOUT
        if ($company->validity_upto && now()->greaterThan($company->validity_upto)) {

            // last_seen null
            $user->last_seen = null;
            $user->save();

            // update session
            $session = DB::connection('tenant')->table('user_sessions')
                ->where('user_id', $user->id)
                ->whereNull('logout_at')
                ->where('platform', 'mobile')
                ->orderByDesc('id')
                ->first();

            if ($session) {
                DB::connection('tenant')->table('user_sessions')
                    ->where('id', $session->id)
                    ->update([
                        'logout_at' => now(),
                        'session_duration' => now()->diffInSeconds($session->login_at),
                    ]);
            }

            // delete all tokens (logout)
            $user->tokens()->delete();

            return response()->json([
                'message' => 'Subscription expired. You have been logged out.'
            ], 403);
        }

        // 🔟 Authenticate user
        Auth::setUser($user);

        return $next($request);
    }
}
