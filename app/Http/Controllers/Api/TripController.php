<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\TaDaSlab;

class TripController extends BaseController
{
    public function index()
    {
        $user = Auth::user()->load("roles");
        $query = Trip::with(['user', 'company', 'approvedByUser', 'tripLogs']);

        if ($user->hasRole('master_admin')) {
            $trips = $query->latest()->paginate(10);
        } elseif ($user->hasRole('sub_admin')) {
            $trips = $query->where('company_id', $user->company_id)->latest()->paginate(10);
        } else {
            $trips = $query->where('user_id', $user->id)->latest()->paginate(10);
        }

        return $this->sendResponse($trips, "Trips fetched successfully");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'trip_date'    => 'required|date',
            'start_time'   => 'required|time',
            'start_lat'    => 'required|numeric',
            'start_lng'    => 'required|numeric',
            'travel_mode'  => 'required|string',
            'purpose'      => 'required|string',
            'tour_type'      => 'required|string',
            'place_to_visit' => 'required|string',
            'starting_km'    => 'required|string',
            'start_km_photo' => 'required|mimes:jpeg,jpg,png,bmp,gif,svg,webp,tiff,ico|max:5120',
        ]);
        $startKmPhoto = $request->hasFile('start_km_photo')
            ? $request->file('start_km_photo')->store('trip_photos', 'public')
            : null;
        $distance = $this->calculateDistance(
            $request->start_lat,
            $request->start_lng,
            $request->end_lat,
            $request->end_lng
        );

        $trip = Trip::create([
            'user_id'           => Auth::id(),
            'company_id'        => Auth::user()->company_id,
            'trip_date'         => $request->trip_date,
            'start_time'        => $request->start_time,
            'start_lat'         => $request->start_lat,
            'start_lng'         => $request->start_lng,
            'travel_mode'       => $request->travel_mode,
            'purpose'           => $request->purpose,
            'tour_type'         => $request->tour_type,
            'place_to_visit'    => $request->place_to_visit,
            'starting_km'       => $request->starting_km,
            'start_km_photo'    => $startKmPhoto,
            'status'            => 'pending',
            'approval_status'   => 'pending',
        ]);
        return $this->sendResponse($trip, "Trip has been created successfully");
    }

    public function show($id)
    {
        $trip = Trip::with('tripLogs')->findOrFail($id);
        return response()->json(['status' => 'success', 'trip' => $trip]);
    }

    public function update(Request $request, $id)
    {
        $trip = Trip::findOrFail($id);
        $user = Auth::user();

        $validated = $request->validate([
            'trip_date'       => 'required|date',
            'start_time'      => 'required',
            'end_time'        => 'required',
            'start_lat'       => 'required|numeric',
            'start_lng'       => 'required|numeric',
            'end_lat'         => 'required|numeric',
            'end_lng'         => 'required|numeric',
            'travel_mode'     => 'required|string',
            'purpose'         => 'nullable|string',
            'approval_status' => 'required|in:pending,approved,denied',
            'approval_reason' => 'nullable|string|max:255',
        ]);

        $trip->update([
            'trip_date'         => $request->trip_date,
            'start_time'        => $request->start_time,
            'end_time'          => $request->end_time,
            'start_lat'         => $request->start_lat,
            'start_lng'         => $request->start_lng,
            'end_lat'           => $request->end_lat,
            'end_lng'           => $request->end_lng,
            'travel_mode'       => $request->travel_mode,
            'purpose'           => $request->purpose,
            'total_distance_km' => $this->calculateDistance(
                $request->start_lat,
                $request->start_lng,
                $request->end_lat,
                $request->end_lng
            ),
            'approval_status'   => $request->approval_status,
            'approval_reason'   => $request->approval_status === 'denied' ? $request->approval_reason : null,
            'approved_by'       => in_array($request->approval_status, ['approved', 'denied']) ? $user->id() : null,
            'approved_at'       => in_array($request->approval_status, ['approved', 'denied']) ? now() : null,
        ]);

        return $this->sendResponse($trip, "Trip has been updated");
    }

    public function destroy($id)
    {
        $trip = Trip::findOrFail($id);
        $user = Auth::user();
        if ($trip->user_id !== $user->id) {
            return $this->sendError('Trip is not assigned you', [], 403);
        }
        $trip->delete();

        return response()->json(['status' => 'success', 'message' => 'Trip deleted']);
    }

    public function approve(Request $request, $id)
    {
        $trip = Trip::findOrFail($id);
        $status = $request->input('status', 'approved');
        $reason = $request->input('reason');

        if ($status === 'denied') {
            $request->validate(['reason' => 'required|string|max:255']);
        }

        $calculatedDistance = app(\App\Http\Controllers\Api\TripLogController::class)->calculateDistanceFromLogsInternal($trip->id) ?? 0;
        
        // Ensure calculatedDistance is valid
        if (!is_finite($calculatedDistance) || $calculatedDistance < 0) {
            $calculatedDistance = 0;
        }

        if ($status === 'approved') {
            $trip->load('user');
            $monthlyApprovedKm = Trip::where('user_id', $trip->user_id)
                ->where('approval_status', 'approved')
                ->whereYear('trip_date', Carbon::parse($trip->trip_date)->year)
                ->whereMonth('trip_date', Carbon::parse($trip->trip_date)->month)
                ->sum('total_distance_km');

            $newTotal = $monthlyApprovedKm + $calculatedDistance;

            $taDaSlab = null;
            if ($trip->user->slab === 'Individual') {
                $taDaSlab = TaDaSlab::where('user_id', $trip->user_id)->first();
                if (!$taDaSlab) {
                    $taDaSlab = TaDaSlab::whereNull('user_id')->first();
                }
            } else {
                $taDaSlab = TaDaSlab::whereNull('user_id')->first();
            }

            if ($taDaSlab && $taDaSlab->max_monthly_travel === 'yes' && $taDaSlab->km > 0) {
                if ($newTotal > $taDaSlab->km) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot approve trip. Max monthly travel limit (' . $taDaSlab->km . ' km) exceeded. Current month approved: ' . $monthlyApprovedKm . ' km.'
                    ], 422);
                }
            }
        }

        $trip->update([
            'approval_status'   => $status,
            'approval_reason'   => $status === 'denied' ? $reason : null,
            'approved_by'       => Auth::id(),
            'approved_at'       => now(),
            'total_distance_km' => $calculatedDistance,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Trip approval updated']);
    }

    public function updateTripCoordinates($tripId)
    {
        $startLog = DB::table('trip_logs')
            ->where('trip_id', $tripId)
            ->orderBy('recorded_at', 'asc')
            ->first();

        $endLog = DB::table('trip_logs')
            ->where('trip_id', $tripId)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if ($startLog && $endLog) {
            DB::table('trips')->where('id', $tripId)->update([
                'start_lat' => $startLog->latitude,
                'start_lng' => $startLog->longitude,
                'end_lat'   => $endLog->latitude,
                'end_lng'   => $endLog->longitude,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => 'Trip coordinates updated']);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $km = $dist * 60 * 1.1515 * 1.609344;

        return round($km, 2);
    }
    public function lastActive()
    {
        $user = Auth::user();
        $trip = Trip::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->latest('trip_date')
            ->latest('start_time')
            ->first();

        if (!$trip) {
            return response()->json(['message' => 'No active trips found'], 404);
        }

        return response()->json($trip);
    }
    public function close(Request $request, $id)
    {
        // 1️⃣  Authorise: only the owner (or an admin) may close the trip.

        $trip = Trip::find($id);
        if (!$trip) {
            return $this->sendError('Trip not found.', [], 200);
        }
        // 2️⃣  Validate incoming data.
        $validated = $request->validate([
            'end_time' => 'required|date_format:H:i:s',   // send as 24h time, e.g. 17:45:00
            'end_lat'  => 'required|numeric',
            'end_lng'  => 'required|numeric',
            'end_km'         => 'required|string',
            'end_km_photo'   => 'required|mimes:jpeg,jpg,png,bmp,gif,svg,webp,tiff,ico|max:5120',
            'status'   => 'in:completed',                 // optional override; default below
        ]);

        $user = Auth::user();
        if ($trip->user_id !== $user->id) {
            return $this->sendError('Trip is not assigned you', [], 403);
        }

        // 3️⃣ Already closed
        if ($trip->status === 'completed') {
            return $this->sendError('Trip is already closed.', [], 400);
        }
        $endKmPhoto = $request->hasFile('end_km_photo')
            ? $request->file('end_km_photo')->store('trip_photos', 'public')
            : null;

        // 4️⃣  Update the trip.
        $trip->update([
            'end_time'          => $validated['end_time'],
            'end_lat'           => $validated['end_lat'],
            'end_lng'           => $validated['end_lng'],
            'end_km'            => $request->end_km,
            'end_km_photo'      => $endKmPhoto,
            'status'            => $validated['status']   ?? 'completed',
            'updated_at'        => Carbon::now(),         // or leave for Eloquent timestamps
        ]);

        // 5️⃣  Return a consistent API response.
        return $this->sendResponse($trip, "Trip has been closed");
    }
}
