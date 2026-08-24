<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class FarmVisitController extends Controller
{
    public function index(Request $request)
    {
        $visits = FarmVisit::with([
        'farmer:id,farmer_name,state_id,district_id,taluka_id',
        'farmer.state:id,name',
        'farmer.district:id,name',
        'farmer.taluka:id,name',
        'crop:id,name'
        ])->where('user_id', Auth::id())
        ->latest()->get();

        $data = $visits->map(function ($visit) {
            $images = [];
            $videos = [];

            // 📸 Images
            if (is_array($visit->images)) {
                foreach ($visit->images as $img) {
                    $images[] = asset('storage/' . $img);
                }
            }

            // 🎥 Videos (MULTIPLE)
            if (is_array($visit->videos)) {
                foreach ($visit->videos as $vid) {
                    $videos[] = asset('storage/' . $vid);
                }
            }

            return [
                'id'                    => $visit->id,
                'farmer_id'             => $visit->farmer_id,
                'farmer_name'           => $visit->farmer->farmer_name ?? null,

                'state_id'              => $visit->farmer->state_id ?? null,
                'state_name'            => $visit->farmer->state->name ?? null,

                'district_id'           => $visit->farmer->district_id ?? null,
                'district_name'         => $visit->farmer->district->name ?? null,

                'taluka_id'             => $visit->farmer->taluka_id ?? null,
                'taluka_name'           => $visit->farmer->taluka->name ?? null,

                'crop_id'               => $visit->crop_id,
                'crop_name'             => $visit->crop->name ?? null,

                'crop_days'             => $visit->crop_days,
                'crop_sowing_land_area' => $visit->crop_sowing_land_area,
                'land_area_size'        => $visit->land_area_size,
                'crop_condition'        => $visit->crop_condition,
                'pest_disease'          => $visit->pest_disease,
                'product_suggested'     => $visit->product_suggested,
                'images'                => $images,
                'videos'                => $videos, // ✅ multiple videos

                'remark'                => $visit->remark,
                'next_visit_date'       => optional($visit->next_visit_date)->format('d-m-Y'),
                'agronomist_remark'     => $visit->agronomist_remark,
                'created_at'            => $visit->created_at->format('d-m-Y H:i:s'),
            ];
        });

        return response()->json([
            'status'  => true,
            'message' => 'Farm visit list',
            'data'    => $data
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'farmer_id'               => 'required',
            'crop_id'                 => 'required',
            'crop_days'               => 'nullable|string',
            'crop_sowing_land_area'   => 'nullable|string',
            'land_area_size'   => 'nullable|string',
            'crop_condition'          => 'nullable|string',
            'pest_disease'            => 'nullable|string',
            'product_suggested'            => 'nullable|string',
            'images.*'                => 'nullable|image|mimes:jpg,jpeg,png|max:5120', // 5MB each
            // 'video'                   => 'nullable|mimes:mp4,mov,avi|max:102400', // 100MB
            'videos'   => 'nullable|array',
            'videos.*' => 'mimes:mp4,mov,avi|max:102400',
            'remark'                  => 'nullable|string',
            'next_visit_date'         => 'nullable|date',
            'agronomist_remark'       => 'nullable|string',
            'latitude' =>  'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        /* 📸 Multiple Images Upload */
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('farm_visits/images', 'public');
                $imagePaths[] = $path;
            }
        }

        $videoPaths = [];

        if ($request->hasFile('videos')) {
            foreach ($request->file('videos') as $video) {

                // original name
                $fileName = time() . '_' . uniqid() . '.' . strtolower($video->getClientOriginalExtension() ?: 'mp4');

                $compressedPath = $video->storeAs('farm_visits/videos', $fileName, 'public');

                $videoPaths[] = $compressedPath;
            }
        }

        $visit = FarmVisit::create([
            'user_id'               => Auth::id(), // 🔐 logged-in user visits
            'farmer_id'             => $request->farmer_id,
            'crop_id'               => $request->crop_id,
            'crop_days'             => $request->crop_days,
            'crop_sowing_land_area' => $request->crop_sowing_land_area,
            'land_area_size'        => $request->land_area_size,
            'crop_condition'        => $request->crop_condition,
            'pest_disease'          => $request->pest_disease,
            'product_suggested'     => $request->product_suggested,
            'images'                => $imagePaths,
            'videos'                 => $videoPaths,
            'remark'                => $request->remark,
            'next_visit_date'       => $request->next_visit_date,
            'agronomist_remark'     => $request->agronomist_remark,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Farm visit added successfully',
            'data' => $visit
        ]);
    }

    public function update(Request $request, $id)
    {
        $visit = FarmVisit::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'farmer_id'               => 'required',
            'crop_id'                 => 'required',
            'crop_days'               => 'nullable|string',
            'crop_sowing_land_area'   => 'nullable|string',
            'land_area_size'   => 'nullable|string',
            'crop_condition'          => 'nullable|string',
            'pest_disease'            => 'nullable|string',
            'product_suggested'     => 'nullable|string',
            'images.*'                => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'videos'   => 'nullable|array',
            'videos.*' => 'mimes:mp4,mov,avi|max:102400',
            'remark'                  => 'nullable|string',
            'next_visit_date'         => 'nullable|date',
            'agronomist_remark'       => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePaths = $visit->images ?? [];

        if ($request->hasFile('images')) {

            foreach ($request->file('images') as $image) {

                $path = $image->store('farm_visits/images', 'public');

                $imagePaths[] = $path;
            }
        }

        $videoPaths = $visit->videos ?? [];

        if ($request->hasFile('videos')) {
            foreach ($request->file('videos') as $video) {

                // original name
                $fileName = time() . '_' . uniqid() . '.' . strtolower($video->getClientOriginalExtension() ?: 'mp4');

                $compressedPath = $video->storeAs('farm_visits/videos', $fileName, 'public');

                $videoPaths[] = $compressedPath;
            }
        }

        $visit->update([
            'farmer_id'             => $request->farmer_id,
            'crop_id'               => $request->crop_id,
            'crop_days'             => $request->crop_days,
            'crop_sowing_land_area' => $request->crop_sowing_land_area,
            'land_area_size'        => $request->land_area_size,
            'crop_condition'        => $request->crop_condition,
            'pest_disease'          => $request->pest_disease,
            'product_suggested'     => $request->product_suggested,
            'images'                => $imagePaths,
            'videos'                => $videoPaths, // ✅ updated
            'remark'                => $request->remark,
            'next_visit_date'       => $request->next_visit_date,
            'agronomist_remark'     => $request->agronomist_remark,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Farm visit updated successfully',
            'data' => $visit
        ]);
    }
}
