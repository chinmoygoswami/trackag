<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartyVisit;
use App\Models\State;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminPartyVisitReportController extends Controller
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
                'message' => 'Only Master Admin and Sub Admin can access this report.',
            ], 403);
        }

        $visits = PartyVisit::query()
            ->with([
                'customer:id,agro_name,contact_person_name,phone,state_id,city,address',
                'customer.state:id,name',
                'user:id,name,mobile,state_id',
                'user.state:id,name',
            ])
            ->whereNotNull('check_in_time')
            ->whereNotNull('check_out_time')
            ->when($validated['state_id'] ?? null, function (Builder $query, int $stateId) {
                $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('state_id', $stateId));
            })
            ->when($validated['employee_id'] ?? null, function (Builder $query, int $employeeId) {
                $query->where('user_id', $employeeId);
            })
            ->when($validated['start_date'] ?? null, function (Builder $query, string $startDate) {
                $query->whereDate('visited_date', '>=', $startDate);
            })
            ->when($validated['end_date'] ?? null, function (Builder $query, string $endDate) {
                $query->whereDate('visited_date', '<=', $endDate);
            })
            ->orderByDesc('visited_date')
            ->orderByDesc('id')
            ->get();

        $items = $visits->map(function (PartyVisit $visit) {
            $durationMinutes = null;
            if ($visit->check_in_time && $visit->check_out_time) {
                $durationMinutes = (int) $visit->check_in_time->diffInMinutes($visit->check_out_time);
            }

            return [
                'id' => $visit->id,
                'employee_id' => $visit->user_id,
                'employee_name' => $visit->user?->name,
                'employee_mobile' => $visit->user?->mobile,
                'employee_state_id' => $visit->user?->state_id,
                'employee_state_name' => $visit->user?->state?->name,
                'customer_id' => $visit->customer_id,
                'party_name' => $visit->customer?->agro_name,
                'contact_person_name' => $visit->customer?->contact_person_name,
                'party_phone' => $visit->customer?->phone,
                'state_id' => $visit->customer?->state_id,
                'state_name' => $visit->customer?->state?->name,
                'city' => $visit->customer?->city,
                'address' => $visit->customer?->address,
                'visited_date' => $visit->visited_date?->format('Y-m-d'),
                'visited_date_formatted' => $visit->visited_date?->format('jS F Y'),
                'visit_day' => $visit->visited_date?->format('d'),
                'visit_month_short' => $visit->visited_date?->format('M'),
                'check_in_time' => $visit->check_in_time?->format('H:i:s'),
                'check_out_time' => $visit->check_out_time?->format('H:i:s'),
                'duration_minutes' => $durationMinutes,
                'duration_formatted' => $durationMinutes === null
                    ? null
                    : intdiv($durationMinutes, 60).'h '.($durationMinutes % 60).'m',
                'visit_purpose' => $visit->visit_purpose,
                'followup_date' => $visit->followup_date?->format('Y-m-d'),
                'followup_date_formatted' => $visit->followup_date?->format('jS F Y'),
                'remarks' => $visit->remarks,
                'latitude' => $visit->latitude !== null ? (float) $visit->latitude : null,
                'longitude' => $visit->longitude !== null ? (float) $visit->longitude : null,
                'agro_visit_image_url' => $visit->agro_visit_image
                    ? asset('storage/'.$visit->agro_visit_image)
                    : null,
                'created_at' => $visit->created_at?->toISOString(),
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
                    'total_visits' => $items->count(),
                ],
                'items' => $items,
            ],
        ]);
    }

    private function employees(?int $stateId)
    {
        return User::query()
            ->with('state:id,name')
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
