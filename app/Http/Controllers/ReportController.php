<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->input('filter', 'daily');
        
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
        ])->where('user_level', '!=', 'master_admin');

        $users = $query->get();

        $reportData = $users->map(function($user) use ($filter) {
            $trip = $user->trips->first(); // Assuming one trip per day or aggregating
            $workingHrs = 0;
            if ($trip && $trip->start_time && $trip->end_time) {
                $start = Carbon::parse($trip->start_time);
                $end = Carbon::parse($trip->end_time);
                $workingHrs = round($end->diffInMinutes($start) / 60, 2);
            }

            return [
                'user_id' => $user->id,
                'date' => Carbon::now()->format('d/m/Y'), // Simplify for now, depends on filter
                'state' => $user->state ? $user->state->name : 'N/A',
                'employee_name' => $user->name,
                'reporting_to' => $user->reportingManager->name ?? 'N/A',
                'punch_in' => $trip ? $trip->start_time : 'N/A',
                'punch_out' => $trip ? $trip->end_time : 'N/A',
                'working_hrs' => $workingHrs,
                'tour_plan' => $trip ? $trip->place_to_visit : 'N/A',
                'travel_km' => $user->trips->sum('total_distance_km'),
                'visit_party_count' => $user->partyVisits->count(),
                'new_party_count' => $user->customers->count(),
                'new_parties' => $user->customers, // For the modal
                'order_count' => $user->orders->count(),
                'order_amount' => $user->orders->flatMap->items->sum('grand_total'),
                'payment_collection' => $user->partyPayments->sum('amount'),
                'farmer_download' => 0, // Placeholder
                'field_demo' => $user->farmVisits->count(),
                'visited_parties' => $user->partyVisits, // For the modal
                'orders_list' => $user->orders, // For the modal
                'payments_list' => $user->partyPayments, // For the modal
                'farm_visits_list' => $user->farmVisits // For the modal
            ];
        });

        return view('admin.reports.index', compact('reportData', 'filter'));
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
        }
    }
}
