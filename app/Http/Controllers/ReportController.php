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
        ->where('user_level', '!=', 'master_admin')
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
                'date' => $trip && $trip->trip_date ? Carbon::parse($trip->trip_date)->format('d/m/Y') : Carbon::now()->format('d/m/Y'),
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

        if (request()->has('export') && request('export') == 'csv') {
            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=employee_activity_report.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $columns = [
                'Date', 'State', 'Employee Name', 'Reporting To', 'Punch In', 'Punch Out', 
                'Working Hrs', 'Tour Plan', 'Travel KM', 'Visit Party Count', 'New Party Count', 
                'Order Count', 'Order Amount (Rs)', 'Payment Collection (Rs)', 'Farmer Download', 'Field Demo',
                'Visited Parties Details', 'New Parties Details', 'Orders Details', 'Payments Details', 'Field Demo Details'
            ];

            $callback = function() use($reportData, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);

                foreach ($reportData as $data) {
                    $visitedPartiesText = $data['visited_parties']->map(function($p) {
                        $name = $p->customer ? ($p->customer->agro_name ?: ($p->customer->name ?: ($p->customer->contact_person_name ?: 'Unnamed'))) : 'Party ID: ' . $p->customer_id;
                        $date = $p->check_in_time ? \Carbon\Carbon::parse($p->check_in_time)->format('d M Y, h:i A') : 'N/A';
                        return "$name (Check In: $date)";
                    })->implode("\n");

                    $newPartiesText = $data['new_parties']->map(function($p) {
                        $name = $p->agro_name ?: ($p->name ?: ($p->contact_person_name ?: 'Unnamed'));
                        $date = $p->created_at ? \Carbon\Carbon::parse($p->created_at)->format('d M Y, h:i A') : 'N/A';
                        return "$name (Added: $date)";
                    })->implode("\n");

                    $ordersText = $data['orders_list']->map(function($o) {
                        $name = $o->customer ? ($o->customer->agro_name ?: ($o->customer->name ?: ($o->customer->contact_person_name ?: 'Unnamed'))) : 'Party ID: ' . $o->party_id;
                        $no = $o->order_no ?: 'N/A';
                        $amt = $o->items ? $o->items->sum('grand_total') : 0;
                        return "$name (Order No: $no, Rs $amt)";
                    })->implode("\n");

                    $paymentsText = $data['payments_list']->map(function($p) {
                        $name = $p->customer ? ($p->customer->agro_name ?: ($p->customer->name ?: ($p->customer->contact_person_name ?: 'Unnamed'))) : 'Party ID: ' . $p->customer_id;
                        $mode = $p->payment_mode ?: 'N/A';
                        return "$name (Mode: $mode, Rs {$p->amount})";
                    })->implode("\n");

                    $farmVisitsText = $data['farm_visits_list']->map(function($v) {
                        $name = $v->farmer ? ($v->farmer->farmer_name ?: ($v->farmer->name ?: 'Unnamed')) : 'Farmer ID: ' . $v->farmer_id;
                        $crop = $v->crop ? $v->crop->name : 'N/A';
                        return "$name (Crop: $crop)";
                    })->implode("\n");

                    fputcsv($file, [
                        $data['date'],
                        $data['state'],
                        $data['employee_name'],
                        $data['reporting_to'],
                        $data['punch_in'],
                        $data['punch_out'],
                        $data['working_hrs'],
                        $data['tour_plan'],
                        $data['travel_km'],
                        $data['visit_party_count'],
                        $data['new_party_count'],
                        $data['order_count'],
                        $data['order_amount'],
                        $data['payment_collection'],
                        $data['farmer_download'],
                        $data['field_demo'],
                        $visitedPartiesText,
                        $newPartiesText,
                        $ordersText,
                        $paymentsText,
                        $farmVisitsText
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        return view('admin.reports.index', compact('reportData', 'filter', 'startDate', 'endDate'));
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
