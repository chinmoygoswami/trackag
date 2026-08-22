<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GpsLog;
use App\Models\Purpose;
use App\Models\TourType;
use App\Models\TravelMode;
use App\Models\Trip;
use App\Models\User;
use App\Models\TripLog;
use App\Models\Farmer;
use App\Models\FarmVisit;
use App\Models\PartyVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiTripController extends BaseController
{

    public function getTourDetails()
    {
        $user = Auth::user(); // or just Auth::user() if 'api' is default guard

        $tourPurposes = Purpose::get();
        $vehicleTypes = TravelMode::get();
        $tourTypes = TourType::get();
        $success = [];
        $success['tourPurposes'] = $tourPurposes;
        $success['vehicleTypes'] = $vehicleTypes;
        $success['tourTypes'] = $tourTypes;
        // Return the response
        return $this->sendResponse($success, 'Tour details fetch successfully');
    }

    public function fetchCustomer()
    {
        $user = Auth::user();

        $getReporting = User::where('reporting_to', $user->id)->pluck('id');

        if ($getReporting->isNotEmpty()) {
            $userIds = $getReporting->push($user->id);

            $customers = Customer::where('is_active', 1)
                ->whereIn('user_id', $userIds)
                ->latest()
                ->get();

        } else {
            $customers = Customer::where('is_active', 1)
                ->where('user_id', $user->id)
                ->latest()
                ->get();
        }

        return $this->sendResponse($customers, "Customers fetched successfully");
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Trip::with([
            'user',
            'company',
            'approvedByUser',
            'tripLogs',
            'customers',
            'travelMode',
            'tourType',
            'purpose'
        ]);

        // Role-based filtering
        if ($user->hasRole('master_admin')) {
            // Master admin sees all trips
        } elseif ($user->hasRole('sub_admin')) {
            // $query->where('company_id', $user->company_id);
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);

                // Include pending trips from subordinates
                $subordinateIds = User::where('reporting_to', $user->id)->pluck('id');
                if ($subordinateIds->isNotEmpty()) {
                    $q->orWhere(function ($inner) use ($subordinateIds) {
                        $inner->whereIn('user_id', $subordinateIds)
                            ->where('approval_status', 'pending');
                    });
                }
            });
        }

        // Add optional filters
        if ($request->has('status')) {
            $query->where('approval_status', $request->status);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('created_at', [
                $request->date_from,
                $request->date_to
            ]);
        }

        // Paginated results
        $trips = $query->latest()->paginate($request->per_page ?? 10);

        return $this->sendResponse($trips, "Trips fetched successfully");
    }
    
    public function logPoint(Request $request)
    {
        $validated = $request->validate([
            'location' => 'required|array|min:1',
            'location.*.tripId' => 'required',
            'location.*.latitude' => 'required|numeric',
            'location.*.longitude' => 'required|numeric',
            'location.*.gps_status' => 'nullable',
            'location.*.battery_percentage' => 'nullable',
            'location.*.recorded_at' => 'nullable|date',
            'location.*.mobile_status' => 'nullable|numeric',
        ]);

        $locations = $validated['location'];

        $tripIds = collect($locations)->pluck('tripId'); // fixed typo (was trip_id)
        $completedTrips = Trip::whereIn('id', $tripIds)
            ->where('status', 'completed')
            ->pluck('id')
            ->toArray();

        if (!empty($completedTrips)) {
            return response()->json([
                'success' => false,
                'message' => "Cannot log points for completed trips: " . implode(', ', $completedTrips)
            ], 403);
        }

        // Insert logs safely within a transaction
        $logs = DB::transaction(function () use ($locations) {
            return collect($locations)->map(function ($loc) {
                // if($loc['latitude'] != 0 && $loc['longitude'] != 0){
                    return TripLog::create([
                        'trip_id' => $loc['tripId'],
                        'latitude' => $loc['latitude'],
                        'longitude' => $loc['longitude'],
                        'gps_status' => $loc['gps_status'] ?? null,
                        'mobile_status' => $loc['mobile_status'] ?? 0,
                        'battery_percentage' => ($loc['battery_percentage'] === "null" || $loc['battery_percentage'] === null)
                            ? null
                            : $loc['battery_percentage'],
                        'recorded_at' => $loc['recorded_at'],
                    ]);
                // }
                
            });
        });
        return $this->sendResponse($logs, "Trip logs recorded successfully");
    }

    // Get all logs for a trip
    public function logs($tripId)
    {
        $trip = Trip::with('tripLogs')->findOrFail($tripId);
        $success["trip"] = $trip->only(['id', 'trip_date', 'start_time', 'end_time', 'status']);
        $success["logs"] = $trip->tripLogs->sortBy('recorded_at')->values();
        return $this->sendResponse($success, "Trip log fetch successfully");
    }

    // Mark a trip as completed
    public function completeTrip($tripId)
    {
        $trip = Trip::findOrFail($tripId);
        $endLog = TripLog::where('trip_id', $tripId)->orderByDesc('recorded_at')->first();

        if ($endLog) {
            $trip->end_lat = $endLog->latitude;
            $trip->end_lng = $endLog->longitude;
            $trip->end_time = now();
        }

        $trip->total_distance_km = $this->calculateDistanceFromLogs($tripId);
        $trip->status = 'completed';
        $trip->save();
        return $this->sendResponse($trip, "Trip marked as completed.");
    }

    // Create a new trip via API
    public function storeTrip(Request $request)
    {
        $validated = $request->validate([
            'trip_date'      => 'nullable|date',
            'start_time'     => 'nullable',
            'start_lat'      => 'required|numeric',
            'start_lng'      => 'required|numeric',
            'travel_mode'    => 'nullable',
            'purpose'        => 'required',
            'tour_type'      => 'required',
            'place_to_visit' => 'nullable|string',
            'starting_km'    => 'nullable|string',
            'start_km_photo' => 'nullable|mimes:jpeg,jpg,png,bmp,gif,svg,webp,tiff,ico|max:5120',
            'customer_ids'   => 'nullable|array',
            'battery_percentage' => 'nullable',
        ]);

        $user = Auth::user();
        $startKmPhoto = null;
        if ($request->hasFile('start_km_photo')) {
            try {
                $startKmPhoto = $request->file('start_km_photo')->store('trip_photos', 'public');
            } catch (\Exception $e) {
                // Handle the error appropriately
            }
        }
        $endKmPhoto = null;
        if ($request->hasFile('end_km_photo')) {
            try {
                $endKmPhoto = $request->file('end_km_photo')->store('trip_photos', 'public');
            } catch (\Exception $e) {
                // Handle the error appropriately
            }
        }


        // If end_lat/lng provided, calculate distance
        $distance = null;
        if (!empty($validated['end_lat']) && !empty($validated['end_lng'])) {
            $distance = $this->calculateDistance(
                $validated['start_lat'],
                $validated['start_lng'],
                $validated['end_lat'],
                $validated['end_lng']
            );
        }

        $trip = Trip::create([
            'user_id'           => $user->id,
            'company_id' =>     isset($user->company_id) ? $user->company_id : 1,
            'trip_date'         => $validated['trip_date'] ?? now()->toDateString(),
            'start_time'        => $validated['start_time'] ?? now()->toTimeString(),
            'end_time'          => $validated['end_time'] ?? null,
            'start_lat'         => $validated['start_lat'],
            'start_lng'         => $validated['start_lng'],
            'end_lat'           => $validated['end_lat'] ?? null,
            'end_lng'           => $validated['end_lng'] ?? null,
            'total_distance_km' => $distance,
            'travel_mode' => is_numeric($request->travel_mode) ? $request->travel_mode : null,
            'purpose'           => $validated['purpose'],
            'tour_type'         => $validated['tour_type'],
            'place_to_visit'    => $validated['place_to_visit'] ?? null,
            'starting_km'       => $validated['starting_km'] ?? null,
            'end_km'            => $validated['end_km'] ?? null,
            'start_km_photo'    => $startKmPhoto,
            'end_km_photo'      => $endKmPhoto,
            'status'            => 'pending',
            'approval_status'   => 'pending',
        ]);

        if($trip){
            TripLog::create([
                'trip_id' => $trip->id,
                'latitude' => $validated['start_lat'],
                'longitude' => $validated['start_lng'],
                'gps_status' => 1,
                'battery_percentage' => $validated['battery_percentage'] ?? 0,
                'recorded_at' => now(),
            ]);
        }

        // Attach customers if provided
        if (!empty($validated['customer_ids'])) {
            $trip->customers()->attach($validated['customer_ids']);
        }
        return $this->sendResponse($trip->load(["purpose", "tourType", "travelMode", "company", "approvedByUser", "user"]), "Day logs created successfully");
    }

    private function calculateDistanceFromLogs($tripId)
    {
        $logs = TripLog::where('trip_id', $tripId)->orderBy('recorded_at')->get();

        if ($logs->count() < 2) {
            return 0;
        }

        $distance = 0;
        for ($i = 1; $i < $logs->count(); $i++) {
            $km = $this->calculateDistance(
                $logs[$i - 1]->latitude,
                $logs[$i - 1]->longitude,
                $logs[$i]->latitude,
                $logs[$i]->longitude
            );

            if (is_numeric($km) && !is_nan($km) && !is_infinite($km)) {
                $distance += $km;
            }
        }

        return round($distance, 2);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist  = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));

        // 🧩 Clamp between -1 and 1 to prevent NaN from acos()
        if ($dist > 1) $dist = 1;
        if ($dist < -1) $dist = -1;

        $dist  = acos($dist);
        $dist  = rad2deg($dist);
        $km    = $dist * 111.13384;

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
            return $this->sendResponse($trip, "No active trips found");
        }
        return $this->sendResponse($trip, "Trips fetched successfully");
    }

    public function punchStatus()
    {
        $user = Auth::user();

        $activeTrip = Trip::query()
            ->where('user_id', $user->id)
            ->whereNull('end_time')
            ->where('status', '!=', 'completed')
            ->latest('trip_date')
            ->latest('start_time')
            ->first(['id', 'trip_date', 'start_time', 'status']);

        return $this->sendResponse([
            'isPunchin' => $activeTrip ? 1 : 0,
            'trip_id' => $activeTrip?->id,
            'trip_date' => $activeTrip?->trip_date,
            'start_time' => $activeTrip?->start_time,
            'trip_status' => $activeTrip?->status,
        ], $activeTrip ? 'User is punched in.' : 'User is punched out.');
    }

    public function close(Request $request)
    {
        try {
            $validated = $request->validate([
                'end_time'       => 'required|date_format:H:i:s',
                'end_lat'        => 'required|numeric',
                'end_lng'        => 'required|numeric',
                'closenote'      => 'nullable|string',
                'end_km'         => 'nullable|string',
                'battery_percentage'         => 'nullable|string',
                'end_km_photo'   => 'nullable|mimes:jpeg,jpg,png,bmp,gif,svg,webp,tiff,ico|max:5120',
                'status'         => 'in:completed',
            ]);
            
            $trip = Trip::findOrFail($request->id);

            $user = Auth::user();
            if ($trip->user_id !== $user->id) {
                return $this->sendError('Trip is not assigned you', [], 403);
            }

            if ($trip->status === 'completed') {
                return $this->sendError('Trip is already closed.', [], 400);
            }

            $endKmPhoto = null;
            if ($request->hasFile('end_km_photo')) {
                $endKmPhoto = $request->file('end_km_photo')->store('trip_photos', 'public');
            }

            $total_distance_km = $this->calculateDistanceFromLogs($request->id);

            $trip->update([
                'end_time'          => $validated['end_time'],
                'end_lat'           => $validated['end_lat'],
                'end_lng'           => $validated['end_lng'],
                'end_km'            => $validated['end_km'],
                'end_km_photo'      => $endKmPhoto,
                'total_distance_km' => $total_distance_km,
                'status'            => $validated['status'] ?? 'completed',
                'updated_at'        => Carbon::now(),
            ]);


            TripLog::create([
                'trip_id' => $trip->id,
                'latitude' => $validated['end_lat'],
                'longitude' => $validated['end_lng'],
                'gps_status' => 1,
                'battery_percentage' => $validated['battery_percentage'] ?? 0,
                'recorded_at' => now(),
            ]);

            // 🧩 Step 8: Return success
            return $this->sendResponse($trip, "Trip has been closed");

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);

        } catch (\Exception $e) {
            return $this->sendError('Something went wrong while closing trip.', [], 500);
        }
    }

    public function showTrip($id)
    {
        $user = Auth::user();
        $trip = Trip::findOrFail($id);
        return $this->sendResponse($trip->load(["purpose", "tourType", "travelMode", "company", "approvedByUser", "user"]), "Trip fetched successfully");
    }

    public function gpsStore(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'gps_flag' => 'required|in:0,1',
            'trip_id' => 'required|integer'
        ]);

        // Save record
        $log = GpsLog::create([
            'user_id' => $request->user_id,
            'gps_flag' => $request->gps_flag,
            'trip_id' => $request->trip_id
        ]);

        return $this->sendResponse($log, "GPS Log Saved Successfully");
    }

    public function getMyTrips(Request $request)
    {
        $user = Auth::user();

        $query = Trip::with([
            'tourType',
            'travelMode',
            'purpose',
            'purposeData',
            'user'
        ]);

        if ($request->filled('view_map_log')) {

            if (!$user->hasRole('master_admin') && !$user->hasRole('sub_admin')) {
                $userIds = User::where('reporting_to', $user->id)->pluck('id');
                $userIds->push($user->id);

                $query->whereIn('user_id', $userIds);
            }

            // Default today's data
            if (!$request->filled('from_date') && !$request->filled('to_date')) {
                $query->whereDate('trip_date', today());
            } else {
                // Filter data
                if ($request->filled('from_date')) {
                    $query->whereDate('trip_date', '>=', $request->from_date);
                }

                if ($request->filled('to_date')) {
                    $query->whereDate('trip_date', '<=', $request->to_date);
                }
            }

        }else{
            $query->where('user_id', $user->id);
        }

        $trips = $query->latest()->get()->map(function ($data) {
            return [
                'id' => $data->id,
                'employee_name' => optional($data->user)->name,
                'trip_date' => $data->trip_date,
                'tour_type' => optional($data->tourType)->name,
                'travel_mode' => optional($data->travelMode)->name,
                'tour_purpose' => optional($data->purposeData)->name,
                'start_time' => $data->start_time,
                'end_time' => $data->end_time,
                'visit_place' => $data->place_to_visit,
                'starting_km' => $data->starting_km,
                'end_km' => $data->end_km,
                'travel_km' => ($data->end_km && $data->starting_km)
                    ? ((float)$data->end_km - (float)$data->starting_km)
                    : 0,
                'gps_km' => $data->total_distance_km ?? 0,
                'km_diff' => ($data->end_km && $data->starting_km)
                    ? ((float)$data->end_km - (float)$data->starting_km)
                    : 0,
                'approval_status' => $data->approval_status,
                'ta_exp' => "",
                'da_exp' => "",
                'other_exp' => "",
                'total' => "",
            ];
        });

        return $this->sendResponse($trips, "Trips fetched successfully");
    }

    public function viewLog($tripId)
    {
        $trip = Trip::with('user')->find($tripId);
        if (!$trip) {
            return $this->sendError('Trip not found', [], 404);
        }

        $logs = TripLog::where('trip_id', $trip->id)
            ->orderBy('recorded_at')
            ->get()
            ->map(function ($log) {
                return [
                    'latitude'     => $log->latitude,
                    'longitude'    => $log->longitude,
                    'battery'      => $log->battery_percentage !== null
                                        ? $log->battery_percentage . '%'
                                        : 'N/A',
                    'gps_status'   => $log->gps_status,
                    'mobile_status' => $log->mobile_status,
                    'recorded_at'  => Carbon::parse($log->recorded_at)->format('d-m-Y H:i:s a'),
                    'created_at'   => optional($log->created_at)->format('d-m-Y H:i:s a'),
                    'updated_at'   => optional($log->updated_at)->format('d-m-Y H:i:s a'),
                ];
            });

        $tripData = [
            'id'            => $trip->id,
            'trip_date'     => Carbon::parse($trip->trip_date)->format('d-m-Y'),
            'employee_name' => optional($trip->user)->name ?? 'N/A',
            'start_time'    => $trip->start_time,
            'end_time'      => $trip->end_time,
            'status'        => $trip->status,
        ];

        return $this->sendResponse([
            'trip' => $tripData,
            'logs' => $logs
        ], "Trip log fetched successfully");
    }

    public function viewMap($tripId)
    {
        $trip = Trip::with('user')->find($tripId);
        if (!$trip) {
            return $this->sendError('Trip not found', [], 404);
        }

        $tripLogs = TripLog::where('trip_id', $trip->id)
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0)
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'recorded_at'])
            ->map(function ($log) {
                return [
                    'latitude'    => $log->latitude,
                    'longitude'   => $log->longitude,
                    'recorded_at' => Carbon::parse($log->recorded_at)->format('d-m-Y H:i:s a'),
                ];
            });

        $partyVisits = PartyVisit::with('customer')
            ->whereDate('visited_date', $trip->trip_date)
            ->where('user_id', $trip->user_id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($visit) {
                return [
                    'latitude'      => $visit->latitude,
                    'longitude'     => $visit->longitude,
                    'check_in_time' => $visit->check_in_time
                        ? Carbon::parse($visit->check_in_time)
                            ->timezone('Asia/Kolkata')
                            ->format('d-m-Y h:i A')
                        : null,
                    'customer'      => [
                        'agro_name' => optional($visit->customer)->agro_name ?? 'Customer'
                    ],
                ];
            });

        $farmers = Farmer::where('user_id', $trip->user_id)
            ->whereDate('created_at', $trip->trip_date)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($farmer) {
                return [
                    'latitude'    => $farmer->latitude,
                    'longitude'   => $farmer->longitude,
                    'created_at'  => Carbon::parse($farmer->created_at)->format('d-m-Y H:i:s a'),
                    'farmer_name' => $farmer->farmer_name ?? 'Farmer',
                ];
            });

        $farmVisits = FarmVisit::with('farmer')
            ->where('user_id', $trip->user_id)
            ->whereDate('created_at', $trip->trip_date)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($visit) {
                return [
                    'latitude'    => $visit->latitude,
                    'longitude'   => $visit->longitude,
                    'created_at'  => Carbon::parse($visit->created_at)->format('d-m-Y H:i:s a'),
                    'farmer_name' => optional($visit->farmer)->farmer_name ?? 'Farmer',
                ];
            });

        $customers = Customer::where('user_id', $trip->user_id)
            ->whereDate('created_at', $trip->trip_date)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($customer) {
                return [
                    'latitude'   => $customer->latitude,
                    'longitude'  => $customer->longitude,
                    'created_at' => Carbon::parse($customer->created_at)->format('d-m-Y H:i:s a'),
                    'agro_name'  => $customer->agro_name ?? 'Customer',
                ];
            });

        $tripData = [
            'id'            => $trip->id,
            'trip_date'     => Carbon::parse($trip->trip_date)->format('d-m-Y'),
            'employee_name' => optional($trip->user)->name ?? 'N/A',
            'start_time'    => $trip->start_time,
            'end_time'      => $trip->end_time,
            'status'        => $trip->status,
        ];

        return $this->sendResponse([
            'trip'        => $tripData,
            'tripLogs'    => $tripLogs,
            'partyVisits' => $partyVisits,
            'farmers'     => $farmers,
            'farmVisits'  => $farmVisits,
            'customers'   => $customers,
        ], "Trip map details fetched successfully");
    }

    public function viewMapWebview($tripId)
    {
        $trip = Trip::with('user')->find($tripId);
        if (!$trip) {
            abort(404, 'Trip not found');
        }

        $tripLogs = TripLog::where('trip_id', $trip->id)
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0)
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'recorded_at'])
            ->map(function ($log) {
                return [
                    'latitude'    => $log->latitude,
                    'longitude'   => $log->longitude,
                    'recorded_at' => Carbon::parse($log->recorded_at)->format('d-m-Y H:i:s a'),
                ];
            });

        $partyVisits = PartyVisit::with('customer')
            ->whereDate('visited_date', $trip->trip_date)
            ->where('user_id', $trip->user_id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($visit) {
                return [
                    'latitude'      => $visit->latitude,
                    'longitude'     => $visit->longitude,
                    'check_in_time' => $visit->check_in_time
                        ? Carbon::parse($visit->check_in_time)
                            ->timezone('Asia/Kolkata')
                            ->format('d-m-Y h:i A')
                        : null,
                    'customer'      => [
                        'agro_name' => optional($visit->customer)->agro_name ?? 'Customer'
                    ],
                ];
            });

        $farmers = Farmer::where('user_id', $trip->user_id)
            ->whereDate('created_at', $trip->trip_date)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($farmer) {
                return [
                    'latitude'    => $farmer->latitude,
                    'longitude'   => $farmer->longitude,
                    'created_at'  => Carbon::parse($farmer->created_at)->format('d-m-Y H:i:s a'),
                    'farmer_name' => $farmer->farmer_name ?? 'Farmer',
                ];
            });

        $farmVisits = FarmVisit::with('farmer')
            ->where('user_id', $trip->user_id)
            ->whereDate('created_at', $trip->trip_date)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($visit) {
                return [
                    'latitude'    => $visit->latitude,
                    'longitude'   => $visit->longitude,
                    'created_at'  => Carbon::parse($visit->created_at)->format('d-m-Y H:i:s a'),
                    'farmer_name' => optional($visit->farmer)->farmer_name ?? 'Farmer',
                ];
            });

        $customers = Customer::where('user_id', $trip->user_id)
            ->whereDate('created_at', $trip->trip_date)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($customer) {
                return [
                    'latitude'   => $customer->latitude,
                    'longitude'  => $customer->longitude,
                    'created_at' => Carbon::parse($customer->created_at)->format('d-m-Y H:i:s a'),
                    'agro_name'  => $customer->agro_name ?? 'Customer',
                ];
            });

        return view('admin.trips.map_webview', compact(
            'trip',
            'tripLogs',
            'partyVisits',
            'farmers',
            'farmVisits',
            'customers'
        ));
    }
}
