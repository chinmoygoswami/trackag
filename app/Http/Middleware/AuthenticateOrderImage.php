<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;

class AuthenticateOrderImage
{
    public function handle(Request $request, Closure $next)
    {
        // Header-based clients retain the existing API authentication flow.
        if ($request->bearerToken() || $request->hasHeader('X-Company-Code')) {
            if ($request->bearerToken() && ! $request->header('X-Company-Code')) {
                $tenant = $this->domainTenant($request);
                if ($tenant) {
                    $code = DB::connection('mysql')->table('companies')
                        ->where('tenant_id', $tenant->id)->where('is_active', 1)
                        ->orderBy('id')->value('code');
                    if ($code) {
                        $request->headers->set('X-Company-Code', $code);
                    }
                }
            }

            return app(TenantAuthenticate::class)->handle($request, $next);
        }

        // Only browser requests load a session; bearer clients stay stateless.
        return (new Pipeline(app()))->send($request)
            ->through(app('router')->resolveMiddleware(['web']))
            ->then(fn (Request $request) => $this->browserSession($request, $next));
    }

    private function browserSession(Request $request, Closure $next)
    {
        // A normal image link cannot send API headers. Use the existing web
        // session only when it belongs to this domain's tenant.
        $tenant = $this->domainTenant($request);
        $sessionTenant = $request->session()->get('logged_in_tenant_id');
        if ($tenant) {
            if ((string) $sessionTenant !== (string) $tenant->id) {
                return $this->unauthenticated();
            }

            return app(EnsureTenantDatabase::class)->handle($request, function ($request) use ($next) {
                return $this->sessionUser($request, $next);
            });
        }

        if (! in_array($request->getHost(), config('tenancy.central_domains', []), true)
            || $sessionTenant !== null) {
            return $this->unauthenticated();
        }

        return $this->sessionUser($request, $next);
    }

    private function domainTenant(Request $request): ?Tenant
    {
        if (in_array($request->getHost(), config('tenancy.central_domains', []), true)) {
            return null;
        }

        return Tenant::whereHas('domains', fn ($query) => $query->where('domain', $request->getHost()))->first();
    }

    private function sessionUser(Request $request, Closure $next)
    {
        if (! $request->user('web')) {
            return $this->unauthenticated();
        }

        $request->attributes->set('order_image_browser_session', true);

        return $next($request);
    }

    private function unauthenticated()
    {
        return response()->json(['status' => false, 'message' => 'Unauthenticated. Please log in to view this image.'], 401);
    }
}
