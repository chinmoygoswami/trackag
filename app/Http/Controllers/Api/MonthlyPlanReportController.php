<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPlan;
use App\Models\Product;
use App\Models\State;
use App\Models\UserStateAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonthlyPlanReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'view_type' => 'nullable|in:month_wise,state_wise',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'state_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            
        ]);

        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user.',
            ], 401);
        }

        $viewType = $validated['view_type'] ?? 'month_wise';
        $month = (int) ($validated['month'] ?? now()->month);
        $year = (int) ($validated['year'] ?? now()->year);

        $query = MonthlyPlan::query()
            ->with([
                'product:id,product_name',
                'packing:id,packing_value,packing_size',
                'state:id,name',
            ])
            ->where('month', $month)
            ->where('year', $year);

        $roleName = $user->getRoleNames()->first();

        if (! in_array($roleName, ['master_admin', 'sub_admin'], true)) {
            $stateIds = UserStateAccess::where('user_id', $user->id)->value('state_ids') ?? [];

            if (empty($stateIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('user', function ($query) use ($stateIds, $user) {
                    $query->whereIn('state_id', $stateIds)
                        ->where('reporting_to', $user->id);
                });
            }
        }

        if ($viewType === 'month_wise') {
            $query->when($validated['state_id'] ?? null, function ($query, $stateId) {
                $query->where('state_id', $stateId);
            });
        }

        $query->when($validated['product_id'] ?? null, function ($query, $productId) {
            $query->where('product_id', $productId);
        });

        $plans = $query->get();

        $items = $plans
            ->groupBy(fn (MonthlyPlan $plan) => $plan->product_id.'_'.$plan->packing_id)
            ->map(function ($group) use ($viewType) {
                $plan = $group->first();

                $item = [
                    'product_id' => $plan->product_id,
                    'product_name' => $plan->product?->product_name ?? 'Unknown Product',
                    'packing_id' => $plan->packing_id,
                    'packing_value' => $plan->packing?->packing_value,
                    'packing_size' => $plan->packing?->packing_size,
                    'packaging_size' => trim(($plan->packing?->packing_value ?? '').' '.($plan->packing?->packing_size ?? '')),
                    'total_quantity' => (float) $group->sum('quantity'),
                ];

                if ($viewType === 'state_wise') {
                    $item['states'] = $group
                        ->groupBy('state_id')
                        ->map(function ($statePlans) {
                            $statePlan = $statePlans->first();

                            return [
                                'state_id' => $statePlan->state_id,
                                'state_name' => $statePlan->state?->name ?? 'Unknown State',
                                'quantity' => (float) $statePlans->sum('quantity'),
                            ];
                        })
                        ->sortBy('state_name', SORT_NATURAL | SORT_FLAG_CASE)
                        ->values();
                }

                return $item;
            })
            ->sortBy('product_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'view_type' => $viewType,
                'month' => $month,
                'month_name' => Carbon::createFromDate($year, $month, 1)->format('F'),
                'year' => $year,
                'selected_filters' => [
                    'product_id' => $validated['product_id'] ?? null,
                    ...($viewType === 'month_wise'
                        ? ['state_id' => $validated['state_id'] ?? null]
                        : []),
                ],
                'filters' => [
                    'products' => Product::query()
                        ->where('status', 1)
                        ->orderBy('product_name')
                        ->get(['id', 'product_name']),
                    ...($viewType === 'month_wise'
                        ? [
                            'states' => State::query()
                                ->where('status', 1)
                                ->orderBy('name')
                                ->get(['id', 'name']),
                        ]
                        : []),
                ],
                'items' => $items,
            ],
        ]);
    }
}
