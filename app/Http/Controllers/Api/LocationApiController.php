<?php

namespace App\Http\Controllers\Api;

use App\Models\Country;
use App\Models\District;
use App\Models\State;
use App\Models\Tehsil;
use Illuminate\Http\Request;

class LocationApiController extends BaseController
{
    //
    public function index()
    {
        $states = State::with(['districts.cities.tehsils'])
            ->get();

        return $this->sendResponse($states, 'Location data fetched successfully.');
    }

    public function getStates(Request $request)
    {
        $company = $request->attributes->get('company');
        $companyStateIds = collect(explode(',', (string) ($company?->state ?? '')))
            ->map(fn ($stateId) => (int) trim($stateId))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $states = State::query()
            ->select('id', 'name')
            ->where('status', 1)
            ->when($companyStateIds, fn ($query) => $query->whereIn('id', $companyStateIds))
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $states
        ]);
    }

    public function getDistricts($state_id)
    {
        $districts = District::where('state_id', $state_id)
            ->select('id', 'name')
            ->where('status',1)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $districts
        ]);
    }

    public function getTehsils($district_id)
    {
        $tehsils = Tehsil::where('district_id', $district_id)
            ->select('id', 'name')
            ->where('status',1)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $tehsils
        ]);
    }
}
