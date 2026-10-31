<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\TaDaSlab;
use App\Models\TaDaTourSlab;
use App\Models\TaDaVehicleSlab;
use Illuminate\Support\Collection;

class ExpenseReportCalculator
{
    public function populate(Collection $trips): void
    {
        if ($trips->isEmpty()) {
            return;
        }

        $userIds = $trips->pluck('user_id')->unique();
        $dates = $trips->pluck('trip_date')->unique();
        $scope = fn ($query) => $query->whereIn('user_id', $userIds)->orWhereNull('user_id');
        $tours = TaDaTourSlab::where($scope)->whereIn('tour_type_id', $trips->pluck('tour_type')->filter()->unique())->get();
        $vehicles = TaDaVehicleSlab::where($scope)->whereIn('travel_mode_id', $trips->pluck('travel_mode')->filter()->unique())->get();
        $slabs = TaDaSlab::where($scope)->get();
        $expenses = Expense::whereIn('user_id', $userIds)->whereIn('bill_date', $dates)
            ->where('approval_status', 'Approved')
            ->selectRaw('user_id, bill_date, SUM(amount) as total_amount')
            ->groupBy('user_id', 'bill_date')->get()
            ->keyBy(fn ($expense) => $expense->user_id . ':' . $expense->bill_date->format('Y-m-d'));

        foreach ($trips as $trip) {
            $user = $trip->user;
            $type = $user->slab ?? '';
            $tour = null;
            $vehicle = null;
            if ($type === 'Individual') {
                $tour = $tours->where('user_id', $trip->user_id)->where('tour_type_id', $trip->tour_type)->first();
                $vehicle = $vehicles->where('user_id', $trip->user_id)->where('travel_mode_id', $trip->travel_mode)->first();
            } elseif ($type === 'Slab Wise') {
                $tour = $tours->whereNull('user_id')->where('designation_id', $user->slab_designation_id)->where('tour_type_id', $trip->tour_type)->first();
                $vehicle = $vehicles->whereNull('user_id')->where('designation_id', $user->slab_designation_id)->where('travel_mode_id', $trip->travel_mode)->first();
            }
            $slab = $type === 'Individual'
                ? $slabs->where('user_id', $trip->user_id)->first()
                : $slabs->whereNull('user_id')->first();
            $amounts = self::amounts(
                (float) $trip->end_km - (float) $trip->starting_km,
                (float) ($vehicle->travelling_allow_per_km ?? 0),
                (float) ($tour->da_amount ?? 0),
                (bool) ($slab->travel_mode_enabled ?? false),
                (float) ($slab->travel_mode_limit ?? 0),
                (int) $trip->trip_limit_override,
                (float) ($expenses->get($trip->user_id . ':' . $trip->trip_date)->total_amount ?? 0)
            );
            foreach ($amounts as $field => $amount) {
                $trip->{$field} = $amount;
            }
        }
    }

    public static function amounts(float $km, float $rate, float $da, bool $limitEnabled, float $limit, int $override, float $other): array
    {
        $ta = $km * $rate;
        if ($limitEnabled && $km < $limit) {
            if ($override === 0 || $override === 2) {
                $ta = 0;
            }
            if ($override === 0 || $override === 3) {
                $da = 0;
            }
        }

        return ['ta_exp' => $ta, 'da_exp' => $da, 'other_exp' => $other, 'total_exp' => $ta + $da + $other];
    }
}
