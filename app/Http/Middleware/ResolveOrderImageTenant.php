<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class ResolveOrderImageTenant
{
    public function handle(Request $request, Closure $next)
    {
        // Public image links select their database by domain only. Headers and
        // cookies must never switch the image lookup to another tenant.
        if (in_array($request->getHost(), config('tenancy.central_domains', []), true)) {
            abort_if(tenancy()->tenant !== null, 404);

            return $next($request);
        }

        $tenant = Tenant::whereHas('domains', fn ($query) => $query->where('domain', $request->getHost()))->first();
        abort_unless($tenant, 404);
        $database = $tenant->getDatabaseName();
        abort_if(! $database || $database === 'default_tenant_db', 503, 'Tenant database is not configured for order images.');
        tenancy()->initialize($tenant);

        return $next($request);
    }
}
