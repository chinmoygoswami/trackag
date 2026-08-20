<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\State;
use App\Models\TallyPartySync;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PartyPerformanceController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'state_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
            'party_id' => 'nullable|integer',
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user.',
            ], 401);
        }

        $isAdmin = $user->hasAnyRole(['master_admin', 'sub_admin']);
        $stateId = $isAdmin ? ($validated['state_id'] ?? null) : $user->state_id;
        $employeeId = $isAdmin ? ($validated['employee_id'] ?? null) : $user->id;

        $partiesQuery = Customer::query()
            ->with([
                'state:id,name',
                'user:id,name,state_id',
                'user.state:id,name',
            ])
            ->whereNotNull('party_code')
            ->when($stateId, function (Builder $query, int $stateId) {
                $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('state_id', $stateId));
            })
            ->when($employeeId, fn (Builder $query, int $employeeId) => $query->where('user_id', $employeeId))
            ->when($validated['party_id'] ?? null, fn (Builder $query, int $partyId) => $query->whereKey($partyId))
            ->orderBy('agro_name');

        $parties = $partiesQuery->get([
            'id',
            'user_id',
            'party_code',
            'agro_name',
            'contact_person_name',
            'address',
            'city',
            'phone',
            'mobil_no_2',
            'state_id',
            'party_active_since',
            'credit_limit',
        ]);

        $tallyParties = TallyPartySync::query()
            ->whereIn('master_id', $parties->pluck('party_code')->filter())
            ->get()
            ->keyBy('master_id');

        $items = $parties->map(function (Customer $party) use ($tallyParties) {
            $tallyParty = $tallyParties->get($party->party_code);
            $joiningDate = $party->party_active_since ?? $tallyParty?->party_create_date;

            return [
                'party_id' => $party->id,
                'party_code' => $party->party_code,
                'party_name' => $party->agro_name ?: $tallyParty?->party_name,
                'employee_id' => $party->user_id,
                'employee_name' => $party->user?->name,
                'state_id' => $party->user?->state_id,
                'state_name' => $party->user?->state?->name ?? $party->state?->name ?? $tallyParty?->state,
                'contact_person_name' => $party->contact_person_name ?: $tallyParty?->contact_person_name,
                'address' => $party->address ?: $tallyParty?->address,
                'city' => $party->city,
                'mobile_1' => $party->phone ?: $tallyParty?->phone_1,
                'mobile_2' => $party->mobil_no_2 ?: $tallyParty?->phone_2,
                'joining_date' => $joiningDate?->format('Y-m-d'),
                'joining_year' => $joiningDate?->format('Y'),
                'credit_limit' => (float) ($party->credit_limit ?: $tallyParty?->credit_limit ?: 0),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'selected_filters' => [
                    'state_id' => $stateId,
                    'employee_id' => $employeeId,
                    'party_id' => $validated['party_id'] ?? null,
                ],
                'filters' => [
                    'states' => $this->states($isAdmin, $user->state_id),
                    'employees' => $this->employees($isAdmin, $user->id, $stateId),
                    'parties' => $this->partyOptions($isAdmin, $user->id, $stateId, $employeeId),
                ],
                'summary' => [
                    'total_parties' => $items->count(),
                ],
                'items' => $items,
            ],
        ]);
    }

    private function states(bool $isAdmin, ?int $userStateId)
    {
        return State::query()
            ->where('status', 1)
            ->when(! $isAdmin, fn (Builder $query) => $query->whereKey($userStateId))
            ->orderBy('name')
            ->get(['id', 'name']);
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

    private function partyOptions(bool $isAdmin, int $userId, ?int $stateId, ?int $employeeId)
    {
        return Customer::query()
            ->whereNotNull('party_code')
            ->when(! $isAdmin, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($stateId, function (Builder $query, int $stateId) {
                $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('state_id', $stateId));
            })
            ->when($employeeId, fn (Builder $query, int $employeeId) => $query->where('user_id', $employeeId))
            ->orderBy('agro_name')
            ->get(['id', 'agro_name', 'party_code', 'user_id']);
    }
}
