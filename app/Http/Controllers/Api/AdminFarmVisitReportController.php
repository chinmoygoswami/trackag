<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmVisit;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminFarmVisitReportController extends Controller
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

        $isAdmin = $user->hasAnyRole(['master_admin', 'sub_admin']);
        $employeeId = $isAdmin ? ($validated['employee_id'] ?? null) : $user->id;

        $visits = FarmVisit::query()
            ->with([
                'user:id,name,state_id',
                'farmer:id,user_id,farmer_name,mobile_no,mobile_no_2,village,state_id,district_id,taluka_id',
                'farmer.state:id,name',
                'farmer.district:id,name',
                'farmer.taluka:id,name',
                'crop:id,name',
            ])
            ->when($validated['state_id'] ?? null, function (Builder $query, int $stateId) {
                $query->whereHas('farmer', fn (Builder $farmerQuery) => $farmerQuery->where('state_id', $stateId));
            })
            ->when($employeeId, fn (Builder $query, int $employeeId) => $query->where('user_id', $employeeId))
            ->when($validated['start_date'] ?? null, function (Builder $query, string $startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            })
            ->when($validated['end_date'] ?? null, function (Builder $query, string $endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $farmers = $visits
            ->filter(fn (FarmVisit $visit) => $visit->farmer !== null)
            ->groupBy('farmer_id')
            ->map(function ($farmerVisits) {
                $latestVisit = $farmerVisits->first();
                $previousVisit = $farmerVisits->skip(1)->first();
                $farmer = $latestVisit->farmer;
                $address = collect([
                    $farmer->village,
                    $farmer->taluka?->name,
                    $farmer->district?->name,
                    $farmer->state?->name,
                ])->filter()->unique()->implode(', ');

                return [
                    'farmer_id' => $farmer->id,
                    'farmer_name' => $farmer->farmer_name,
                    'mobile_1' => $farmer->mobile_no,
                    'mobile_2' => $farmer->mobile_no_2,
                    'address' => $address,
                    'village' => $farmer->village,
                    'state_id' => $farmer->state_id,
                    'state_name' => $farmer->state?->name,
                    'district_id' => $farmer->district_id,
                    'district_name' => $farmer->district?->name,
                    'taluka_id' => $farmer->taluka_id,
                    'taluka_name' => $farmer->taluka?->name,
                    'employee_id' => $latestVisit->user_id,
                    'employee_name' => $latestVisit->user?->name,
                    'crop_id' => $latestVisit->crop_id,
                    'crop_name' => $latestVisit->crop?->name,
                    'crop_days' => $latestVisit->crop_days,
                    'last_visit_date' => $previousVisit?->created_at?->format('Y-m-d'),
                    'last_visit_date_formatted' => $previousVisit?->created_at?->format('d M Y'),
                    'next_visit_date' => $latestVisit->next_visit_date?->format('Y-m-d'),
                    'next_visit_date_formatted' => $latestVisit->next_visit_date?->format('d M Y'),
                    'latest_visit' => $this->visitData($latestVisit),
                    'visits' => $farmerVisits->map(fn (FarmVisit $visit) => $this->visitData($visit))->values(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'selected_filters' => [
                    'state_id' => $validated['state_id'] ?? null,
                    'employee_id' => $employeeId,
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                ],
                'filters' => [
                    'states' => State::query()
                        ->where('status', 1)
                        ->orderBy('name')
                        ->get(['id', 'name']),
                    'employees' => $this->employees($isAdmin, $user->id, $validated['state_id'] ?? null),
                ],
                'summary' => [
                    'total_farmers' => $farmers->count(),
                    'total_visits' => $visits->count(),
                ],
                'farmers' => $farmers,
            ],
        ]);
    }

    private function visitData(FarmVisit $visit): array
    {
        return [
            'visit_id' => $visit->id,
            'visit_date' => $visit->created_at?->format('Y-m-d'),
            'visit_date_formatted' => $visit->created_at?->format('d M Y'),
            'crop_id' => $visit->crop_id,
            'crop_name' => $visit->crop?->name,
            'crop_days' => $visit->crop_days,
            'land_area' => trim(collect([$visit->land_area_size, $visit->crop_sowing_land_area])->filter()->implode(' ')),
            'crop_condition' => $visit->crop_condition,
            'pest_disease' => $visit->pest_disease,
            'product_suggested' => $visit->product_suggested,
            'dosage' => null,
            'remarks' => $visit->remark,
            'agronomist_remark' => $visit->agronomist_remark,
            'next_visit_date' => $visit->next_visit_date?->format('Y-m-d'),
            'next_visit_date_formatted' => $visit->next_visit_date?->format('d M Y'),
            'field_photos' => collect($visit->images ?? [])
                ->map(fn (string $image) => asset('storage/'.$image))
                ->values(),
            'videos' => collect($visit->videos ?? [])
                ->map(fn (string $video) => asset('storage/'.$video))
                ->values(),
        ];
    }

    private function employees(bool $isAdmin, int $userId, ?int $stateId)
    {
        return User::query()
            ->where('status', 'Active')
            ->where('is_active', 1)
            ->when(! $isAdmin, fn (Builder $query) => $query->whereKey($userId))
            ->when($stateId, fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->where(function (Builder $query) {
                $query->whereNull('user_level')
                    ->orWhereNotIn('user_level', ['master_admin', 'sub_admin']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'state_id']);
    }
}
