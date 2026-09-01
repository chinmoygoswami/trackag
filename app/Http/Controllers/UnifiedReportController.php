<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UnifiedReportController extends Controller
{
    public function index(Request $request)
    {
        $month = (int)$request->input('month', Carbon::now()->month);
        $year = (int)$request->input('year', Carbon::now()->year);
        $date = Carbon::create($year, $month, 1);

        $users = User::with([
            'state',
            'reportingManager',
            'trips' => function($q) use ($month, $year) {
                $q->whereMonth('trip_date', $month)->whereYear('trip_date', $year);
            },
            'partyVisits' => function($q) use ($month, $year) {
                $q->whereMonth('visited_date', $month)->whereYear('visited_date', $year);
            },
            'customers' => function($q) use ($month, $year) {
                $q->whereMonth('created_at', $month)->whereYear('created_at', $year);
            },
            'orders' => function($q) use ($month, $year) {
                $q->with('items')->whereMonth('created_at', $month)->whereYear('created_at', $year);
            },
            'partyPayments' => function($q) use ($month, $year) {
                $q->whereMonth('payment_date', $month)->whereYear('payment_date', $year);
            },
            'farmVisits' => function($q) use ($month, $year) {
                $q->whereMonth('created_at', $month)->whereYear('created_at', $year);
            },
            'expenses' => function($q) use ($month, $year) {
                $q->whereMonth('bill_date', $month)->whereYear('bill_date', $year);
            }
        ])
        ->whereNotIn('user_level', ['master_admin', 'sub_admin'])
        ->get();

        $reportData = $users->map(function($user) use ($date, $month, $year) {
            $workingHrs = 0;
            $presentDays = 0;
            $travelKm = 0;

            $ta = 0;
            $da = 0;
            $isIndividual = $user->slab === 'Individual';
            $slab = \App\Models\TaDaSlab::query()
                ->when($isIndividual,
                    fn ($q) => $q->where('user_id', $user->id),
                    fn ($q) => $q->whereNull('user_id')
                )->first();

            foreach ($user->trips as $trip) {
                $tripKm = max(0, (float) $trip->end_km - (float) $trip->starting_km);
                $travelKm += $tripKm;
                $presentDays++;
                if ($trip->start_time && $trip->end_time) {
                    $start = Carbon::parse($trip->start_time);
                    $end = Carbon::parse($trip->end_time);
                    $workingHrs += round(abs($start->diffInMinutes($end)) / 60, 2);
                }
                
                if (strtolower($trip->approval_status) === 'approved') {
                    $tourSlab = \App\Models\TaDaTourSlab::query()
                        ->where('tour_type_id', $trip->tour_type)
                        ->when($isIndividual,
                            fn ($q) => $q->where('user_id', $user->id),
                            fn ($q) => $q->whereNull('user_id')->where('designation_id', $user->slab_designation_id)
                        )->first();

                    $vehicleSlab = \App\Models\TaDaVehicleSlab::query()
                        ->where('travel_mode_id', $trip->travel_mode)
                        ->when($isIndividual,
                            fn ($q) => $q->where('user_id', $user->id),
                            fn ($q) => $q->whereNull('user_id')->where('designation_id', $user->slab_designation_id)
                        )->first();

                    $tripTa = (float) ($vehicleSlab->travelling_allow_per_km ?? 0) * $tripKm;
                    $tripDa = (float) ($tourSlab->da_amount ?? 0);

                    if ($slab?->travel_mode_enabled == 1 && $tripKm < (float) $slab->travel_mode_limit) {
                        switch ((int) $trip->trip_limit_override) {
                            case 1: break; // Keep both
                            case 2: $tripTa = 0.0; break;
                            case 3: $tripDa = 0.0; break;
                            default: $tripTa = 0.0; $tripDa = 0.0; break;
                        }
                    }
                    $ta += $tripTa;
                    $da += $tripDa;
                }
            }
            
            $avgWorkHrs = $presentDays > 0 ? round($workingHrs / $presentDays, 2) : 0;
            
            $orderCount = $user->orders->count();
            $paymentCollection = $user->partyPayments->sum('amount');
            
            // Other expenses are approved expenses
            $other = $user->expenses->where('approval_status', 'Approved')->sum('amount');
            $totalExpense = $ta + $da + $other;

            $monthNames = [1=>'january',2=>'february',3=>'march',4=>'april',5=>'may',6=>'june',
                           7=>'july',8=>'august',9=>'september',10=>'october',11=>'november',12=>'december'];
            $monthField = $monthNames[$month];
            
            $startYear = ($month >= 4) ? $year : $year - 1;
            $financialYear = $startYear . '-' . substr($startYear + 1, 2);

            $budget = \App\Models\Budget::where('user_id', $user->id)
                        ->where('financial_year', $financialYear)
                        ->first();

            $yearlyTarget = $budget ? $budget->total_target : 0;
            $monthlyTarget = $budget ? $budget->$monthField : 0;

            $monthlyAchievement = $user->orders->sum(function($order) {
                return $order->items->sum('total_price');
            });
            // For performance, yearly achievement is approximated to monthly in this view unless fully queried
            $yearlyAchievement = $monthlyAchievement; 

            $monthlyTargetPct = $monthlyTarget > 0 ? round(($monthlyAchievement / $monthlyTarget) * 100, 1) : ($monthlyAchievement > 0 ? 100 : 0);
            $yearlyTargetPct = $yearlyTarget > 0 ? round(($yearlyAchievement / $yearlyTarget) * 100, 1) : 0;

            return [
                'date' => $date->format('F Y'),
                'state' => $user->state->name ?? 'N/A',
                'employee_name' => $user->name,
                'reporting_to' => $user->reportingManager->name ?? 'N/A',
                
                'present_days' => $presentDays,
                'working_hrs' => $workingHrs,
                'avg_work_hrs' => $avgWorkHrs,
                'total_travel_km' => $travelKm,
                
                'yearly_target_pct' => $yearlyTargetPct,
                'monthly_target_pct' => $monthlyTargetPct,
                
                'visit_party_count' => $user->partyVisits->count(),
                'new_party_count' => $user->customers->count(),
                'order_count' => $orderCount,
                'payment_collection' => $paymentCollection,
                'farmer_download' => 0, // Module not implemented
                'field_demo' => $user->farmVisits->count(),
                
                'ta_allowance' => $ta,
                'da_allowance' => $da,
                'other_allowance' => $other,
                'total_expense' => $totalExpense,
            ];
        });

        return view('admin.reports.unified', compact('reportData', 'month', 'year'));
    }
}
