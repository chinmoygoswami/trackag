<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->input('filter', 'daily');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        $query = User::with([
            'reportingManager',
            'trips' => function($q) use ($filter) {
                $this->applyDateFilter($q, $filter, 'trip_date');
            },
            'partyVisits' => function($q) use ($filter) {
                $q->with('customer');
                $this->applyDateFilter($q, $filter, 'visited_date');
            },
            'customers' => function($q) use ($filter) {
                $this->applyDateFilter($q, $filter, 'created_at');
            },
            'orders' => function($q) use ($filter) {
                $q->with(['items', 'customer']);
                $this->applyDateFilter($q, $filter, 'created_at');
            },
            'partyPayments' => function($q) use ($filter) {
                $q->with('customer');
                $this->applyDateFilter($q, $filter, 'payment_date');
            },
            'farmVisits' => function($q) use ($filter) {
                $q->with(['farmer', 'crop']);
                $this->applyDateFilter($q, $filter, 'created_at');
            }
        ])
        ->whereNotIn('user_level', ['master_admin', 'sub_admin', 'company_admin'])
        ->whereHas('trips', function($q) use ($filter) {
            $this->applyDateFilter($q, $filter, 'trip_date');
        });

        $users = $query->get();

        $reportData = $users->map(function($user) use ($filter) {
            $trip = $user->trips->first(); // Assuming one trip per day or aggregating
            $workingHrs = 0;
            if ($trip && $trip->start_time && $trip->end_time) {
                $start = Carbon::parse($trip->start_time);
                $end = Carbon::parse($trip->end_time);
                
                // Use absolute value to prevent negative hours if start/end are parsed incorrectly
                $workingHrs = round(abs($start->diffInMinutes($end)) / 60, 2);
            }

            return [
                'user_id' => $user->id,
                'trip_id' => $trip ? $trip->id : null,
                'date' => $trip && $trip->trip_date ? Carbon::parse($trip->trip_date)->format('d/m/Y') : Carbon::now()->format('d/m/Y'),
                'state' => $user->state ? $user->state->name : 'N/A',
                'employee_name' => $user->name,
                'reporting_to' => $user->reportingManager->name ?? 'N/A',
                'punch_in' => $trip ? $trip->start_time : 'N/A',
                'punch_out' => $trip ? $trip->end_time : 'N/A',
                'working_hrs' => $workingHrs,
                'tour_plan' => $trip ? $trip->place_to_visit : 'N/A',
                'travel_km' => $user->trips->sum(function($t) { return max(0, (float) $t->end_km - (float) $t->starting_km); }),
                'visit_party_count' => $user->partyVisits->count(),
                'new_party_count' => $user->customers->count(),
                'new_parties' => $user->customers, // Detailed raw collection instead of formatted string
                'order_count' => $user->orders->count(),
                'order_amount' => $user->orders->flatMap->items->sum('grand_total'),
                'payment_collection' => $user->partyPayments->sum('amount'),
                'farmer_download' => 0, // Placeholder
                'field_demo' => $user->farmVisits->count(),
                'visited_parties' => $user->partyVisits, // Detailed raw collection
                'orders_list' => $user->orders, // Detailed raw collection
                'payments_list' => $user->partyPayments, // Detailed raw collection
                'farm_visits_list' => $user->farmVisits // Detailed raw collection
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Admin report data retrieved successfully.',
            'filter' => $filter,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'data' => $reportData
        ], 200);
    }

    private function applyDateFilter($query, $filter, $column)
    {
        $now = Carbon::now();
        switch ($filter) {
            case 'daily':
                $query->whereDate($column, $now->toDateString());
                break;
            case 'weekly':
                $query->whereBetween($column, [$now->startOfWeek()->toDateString(), $now->endOfWeek()->toDateString()]);
                break;
            case 'monthly':
                $query->whereMonth($column, $now->month)
                      ->whereYear($column, $now->year);
                break;
            case 'yearly':
                $query->whereYear($column, $now->year);
                break;
            case 'custom':
                if (request()->has('start_date') && request()->has('end_date') && request()->input('start_date') != '' && request()->input('end_date') != '') {
                    $start = Carbon::parse(request()->input('start_date'))->startOfDay();
                    $end = Carbon::parse(request()->input('end_date'))->endOfDay();
                    $query->whereBetween($column, [$start, $end]);
                }
                break;
        }
    }
}
