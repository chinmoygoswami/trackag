<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Models\PartyVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PartyController extends BaseController
{
    public function index()
    {
        $user = Auth::user();

        $visits = PartyVisit::with(['customer'])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        $visitsFormatted = $visits->map(function($visit) {
            return [
                'id' => $visit->id,
                'user_id' => $visit->user_id,
                'customer' => $visit->customer ? [
                    'id' => $visit->customer->id,
                    'name' => $visit->customer->agro_name,
                ] : null,
                'visit_purpose' => $visit->visit_purpose,
                // 'visited_date' => $visit->visited_date ? $visit->visited_date : null,
                'visited_date' => Carbon::parse($visit->visited_date)
                    ->timezone('Asia/Kolkata')
                     ->format('Y-m-d'),
                'check_in_time' => $visit->check_in_time ? $visit->check_in_time : null,
                'check_out_time' => $visit->check_out_time ? $visit->check_out_time : null,
                'followup_date' => $visit->followup_date ? $visit->followup_date : null,
                'remarks' => $visit->remarks,
                'latitude' => $visit->latitude,
                'longitude' => $visit->longitude,
                'agro_visit_image' => $visit->agro_visit_image ? asset('storage/' . $visit->agro_visit_image) : null,
                'created_at' => $visit->created_at,
                'updated_at' => $visit->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Party visits fetched successfully',
            'data' => $visitsFormatted
        ]);
    }

    public function partyVisitsStore(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'customer_id' => 'nullable|integer',
            'check_in_time' => 'nullable|date_format:Y-m-d H:i:s',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $validated['visited_date'] = Carbon::now();

        $partyVisit = PartyVisit::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Party visit saved successfully!',
            'data' => $partyVisit
        ]);
    }

    public function partyVisitCheckout(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'check_out_time' => 'nullable|date_format:Y-m-d H:i:s',
            'visit_purpose' => 'nullable|string',
            'followup_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'agro_visit_image' => 'nullable|image|mimes:jpg,jpeg,png|max:10240',
        ]);

        $partyVisit = PartyVisit::find($validated['id']);

        if ($request->hasFile('agro_visit_image')) {
            $path = $request->file('agro_visit_image')->store('party_visits', 'public');
            $validated['agro_visit_image'] = $path;
        }

        if (!$partyVisit) {
            return response()->json([
                'success' => false,
                'message' => 'Party visit not found'
            ], 404);
        }

        $partyVisit->check_out_time = $validated['check_out_time'] ?? now();
        $partyVisit->visit_purpose = $validated['visit_purpose'] ?? null;
        $partyVisit->followup_date = $validated['followup_date'] ?? null;
        $partyVisit->remarks = $validated['remarks'] ?? null;
        $partyVisit->agro_visit_image = $validated['agro_visit_image'] ?? null;
        $partyVisit->save();

        return response()->json([
            'success' => true,
            'message' => 'Checkout time updated successfully',
            'data' => $partyVisit
        ]);
    }

    public function newPartyStore(Request $request)
    {
        $validated = $request->validate([
            'agro_name'             => 'required|string',
            'contact_person_name'   => 'nullable|string',
            'phone'                 => 'nullable|string|max:20',
            'mobil_no_2'            => 'nullable|string|max:20',
            'state_id'              => 'required|integer',
            'district_id'           => 'required|integer',
            'tehsil_id'             => 'required|integer',
            'city'                  => 'required|string',
            'address'               => 'required|string',
            'gst_no'                => 'nullable|string',
            'working_with'          => 'nullable|string',
            'visit_card_image'      => 'nullable|image|mimes:jpg,jpeg,png|max:10240',
            'party_documents.*'     => 'nullable|image|mimes:jpg,jpeg,png|max:10240',
            'user_id'               => 'nullable|integer',
            'latitude' =>  'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if ($request->hasFile('visit_card_image')) {
            $visitCardPath = $request->file('visit_card_image')->store('visit_cards', 'public');
            $validated['visit_card_image'] = $visitCardPath;
        }

        $documentPaths = [];
        if ($request->hasFile('party_documents')) {
            foreach ($request->file('party_documents') as $file) {
                $documentPaths[] = $file->store('party_documents', 'public');
            }
        }

        $validated['party_documents'] = $documentPaths;

        $validated['visit_date'] = Carbon::now();
        $validated['type'] = "mobile";

        $customer = Customer::create($validated);

        return $this->sendResponse($customer, "Customer saved successfully!");
    }

    public function getPartyList(Request $request)
    {
        $user = Auth::user();
        $customers = Customer::with('state','district','tehsil')->orderBy('id', 'desc')->where('type','mobile')->where('user_id',$user->id)->get()
            ->map(function ($customer) {

                return [
                    'id' => $customer->id,
                    'type' => $customer->type,
                    // 'visit_date' => $customer->visit_date,
                    'visit_date' => Carbon::parse($customer->visited_date)
                    ->timezone('Asia/Kolkata')
                     ->format('Y-m-d'),
                    'agro_name' => $customer->agro_name,
                    'contact_person_name' => $customer->contact_person_name,
                    'phone' => $customer->phone,
                    'mobil_no_2' => $customer->mobil_no_2,
                    'state_id' => $customer->state_id,
                    'state_name' => $customer->state->name ?? "",
                    'district_id' => $customer->district_id,
                    'district_name' => $customer->district->name ?? "",
                    'tehsil_id' => $customer->tehsil_id,
                    'tehsil_name' => $customer->tehsil->name ?? "",
                    'city' => $customer->city,
                    'address' => $customer->address,
                    'gst_no' => $customer->gst_no,
                    'working_with' => $customer->working_with,
                    'user_id' => $customer->user_id,
                    'status' => $customer->status,

                    // 👇 Single Image URL
                    'visit_card_image_url' => $customer->visit_card_image
                        ? asset('storage/' . $customer->visit_card_image)
                        : null,

                    'party_documents_urls' => $customer->party_documents
                        ? collect($customer->party_documents)->map(function ($path) {
                            return asset('storage/' . $path);
                        })
                        : [],

                    'created_at' => $customer->created_at,
                ];
            });

        return $this->sendResponse($customers, "Party list fetched successfully!");
    }
    
}

