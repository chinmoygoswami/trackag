<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\State;
use App\Models\TaDaSlab;
use App\Models\TaDaTourSlab;
use App\Models\TaDaVehicleSlab;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminTaDaReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'view_type' => 'nullable|in:employee_wise,state_wise',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'state_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user.',
            ], 401);
        }

        if (!$user->hasAnyRole(['master_admin', 'sub_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Master Admin and Sub Admin can access this report.',
            ], 403);
        }

        $viewType = $validated['view_type'] ?? 'employee_wise';
        $month = (int) ($validated['month'] ?? now()->month);
        $year = (int) ($validated['year'] ?? now()->year);
        $date = Carbon::createFromDate($year, $month, 1);

        $query = Trip::query()
            ->with('user:id,name,state_id,slab,slab_designation_id')
            ->where('approval_status', 'approved')
            ->whereBetween('trip_date', [
                $date->copy()->startOfMonth()->toDateString(),
                $date->copy()->endOfMonth()->toDateString(),
            ]);

        if ($viewType === 'employee_wise') {
            $query->when($validated['state_id'] ?? null, function (Builder $query, int $stateId) {
                $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('state_id', $stateId));
            });

            $query->when($validated['employee_id'] ?? null, function (Builder $query, int $employeeId) {
                $query->where('user_id', $employeeId);
            });
        }

        $trips = $query->get();

        $data = [
            'view_type' => $viewType,
            'month' => $month,
            'month_name' => $date->format('F'),
            'year' => $year,
            'selected_filters' => $viewType === 'employee_wise'
                ? [
                    'state_id' => $validated['state_id'] ?? null,
                    'employee_id' => $validated['employee_id'] ?? null,
                ]
                : [],
            'filters' => $this->filters($viewType, $validated['state_id'] ?? null),
        ];

        if ($viewType === 'employee_wise') {
            $data['total_information'] = $this->summarize($trips);
        } else {
            $tripsByState = $trips
                ->filter(fn (Trip $trip) => $trip->user && $trip->user->state_id)
                ->groupBy(fn (Trip $trip) => $trip->user->state_id);

            $data['items'] = State::query()
                ->whereIn('id', $tripsByState->keys())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (State $state) => [
                    'state_id' => $state->id,
                    'state_name' => $state->name,
                    ...$this->summarize($tripsByState->get($state->id, collect())),
                ])
                ->values();
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function filters(string $viewType, ?int $stateId): array
    {
        if ($viewType !== 'employee_wise') {
            return [];
        }

        $employees = User::query()
            ->with('state:id,name')
            ->where('status', 'Active')
            ->when($stateId, fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->where(function (Builder $query) {
                $query->whereNull('user_level')
                    ->orWhereNotIn('user_level', ['master_admin', 'sub_admin']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'state_id']);

        return [
            'states' => State::query()
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name']),
            'employees' => $employees,
        ];
    }

    private function summarize(Collection $trips): array
    {
        $summary = [
            'total_travel_km' => 0.0,
            'gps_travel_km' => 0.0,
            'ta_allowance' => 0.0,
            'da_allowance' => 0.0,
            'other_expense' => 0.0,
            'total' => 0.0,
        ];
        $countedExpenseDays = [];

        foreach ($trips as $trip) {
            if (! $trip->user) {
                continue;
            }

            $travelKm = max(0, (float) $trip->end_km - (float) $trip->starting_km);
            [$ta, $da] = $this->allowances($trip, $travelKm);
            $expenseKey = $trip->user_id.'|'.Carbon::parse($trip->trip_date)->toDateString();
            $other = 0.0;

            if (! isset($countedExpenseDays[$expenseKey])) {
                $other = (float) Expense::query()
                    ->where('user_id', $trip->user_id)
                    ->whereDate('bill_date', $trip->trip_date)
                    ->where('approval_status', 'Approved')
                    ->sum('amount');
                $countedExpenseDays[$expenseKey] = true;
            }

            $summary['total_travel_km'] += $travelKm;
            $summary['gps_travel_km'] += (float) ($trip->total_distance_km ?? 0);
            $summary['ta_allowance'] += $ta;
            $summary['da_allowance'] += $da;
            $summary['other_expense'] += $other;
            $summary['total'] += $ta + $da + $other;
        }

        return array_map(fn (float $value) => round($value, 2), $summary);
    }

    private function allowances(Trip $trip, float $travelKm): array
    {
        $user = $trip->user;
        $isIndividual = $user->slab === 'Individual';

        $tourSlab = TaDaTourSlab::query()
            ->where('tour_type_id', $trip->tour_type)
            ->when($isIndividual,
                fn (Builder $query) => $query->where('user_id', $user->id),
                fn (Builder $query) => $query->whereNull('user_id')->where('designation_id', $user->slab_designation_id)
            )->first();

        $vehicleSlab = TaDaVehicleSlab::query()
            ->where('travel_mode_id', $trip->travel_mode)
            ->when($isIndividual,
                fn (Builder $query) => $query->where('user_id', $user->id),
                fn (Builder $query) => $query->whereNull('user_id')->where('designation_id', $user->slab_designation_id)
            )->first();

        $slab = TaDaSlab::query()
            ->when($isIndividual,
                fn (Builder $query) => $query->where('user_id', $user->id),
                fn (Builder $query) => $query->whereNull('user_id')
            )->first();

        $ta = (float) ($vehicleSlab->travelling_allow_per_km ?? 0) * $travelKm;
        $da = (float) ($tourSlab->da_amount ?? 0);

        if ($slab?->travel_mode_enabled == 1 && $travelKm < (float) $slab->travel_mode_limit) {
            return match ((int) $trip->trip_limit_override) {
                1 => [$ta, $da],
                2 => [0.0, $da],
                3 => [$ta, 0.0],
                default => [0.0, 0.0],
            };
        }

        return [$ta, $da];
    }
}
