<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminFarmerListController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'state_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user.',
            ], 401);
        }

        if (! $user->hasAnyRole(['master_admin', 'sub_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Master Admin and Sub Admin can access this API.',
            ], 403);
        }

        $farmers = Farmer::query()
            ->with([
                'user:id,name,state_id',
                'state:id,name',
                'district:id,name',
                'taluka:id,name',
                'latestFarmVisit' => fn ($query) => $query->select([
                    'farm_visits.id',
                    'farm_visits.farmer_id',
                    'farm_visits.next_visit_date',
                    'farm_visits.created_at',
                ]),
            ])
            ->when($validated['state_id'] ?? null, function (Builder $query, int $stateId) {
                $query->where('state_id', $stateId);
            })
            ->when($validated['employee_id'] ?? null, function (Builder $query, int $employeeId) {
                $query->where('user_id', $employeeId);
            })
            ->when($validated['start_date'] ?? null, function (Builder $query, string $startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            })
            ->when($validated['end_date'] ?? null, function (Builder $query, string $endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $items = $farmers->map(function (Farmer $farmer) {
            $addressParts = collect([
                $farmer->village,
                $farmer->taluka?->name,
                $farmer->district?->name,
                $farmer->state?->name,
            ])->filter()->unique()->values();

            return [
                'farmer_id' => $farmer->id,
                'farmer_name' => $farmer->farmer_name,
                'mobile_1' => $farmer->mobile_no,
                'mobile_2' => $farmer->mobile_no_2,
                'address' => $addressParts->implode(', '),
                'village' => $farmer->village,
                'state_id' => $farmer->state_id,
                'state_name' => $farmer->state?->name,
                'district_id' => $farmer->district_id,
                'district_name' => $farmer->district?->name,
                'taluka_id' => $farmer->taluka_id,
                'taluka_name' => $farmer->taluka?->name,
                'employee_id' => $farmer->user_id,
                'employee_name' => $farmer->user?->name,
                'land' => trim(collect([$farmer->land_acr_size, $farmer->land_acr])->filter()->implode(' ')),
                'land_value' => $farmer->land_acr_size,
                'land_unit' => $farmer->land_acr,
                'irrigation' => $farmer->irrigation_type,
                'next_visit_date' => $farmer->latestFarmVisit?->next_visit_date?->format('Y-m-d'),
                'next_visit_date_formatted' => $farmer->latestFarmVisit?->next_visit_date?->format('d M Y'),
                'created_at' => $farmer->created_at?->toISOString(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'selected_filters' => [
                    'state_id' => $validated['state_id'] ?? null,
                    'employee_id' => $validated['employee_id'] ?? null,
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                ],
                'filters' => [
                    'states' => State::query()
                        ->where('status', 1)
                        ->orderBy('name')
                        ->get(['id', 'name']),
                    'employees' => $this->employees($validated['state_id'] ?? null),
                ],
                'summary' => [
                    'total_farmers' => $items->count(),
                ],
                'items' => $items,
            ],
        ]);
    }

    private function employees(?int $stateId)
    {
        return User::query()
            ->where('status', 'Active')
            ->where('is_active', 1)
            ->when($stateId, fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->where(function (Builder $query) {
                $query->whereNull('user_level')
                    ->orWhereNotIn('user_level', ['master_admin', 'sub_admin']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'state_id']);
    }
}
