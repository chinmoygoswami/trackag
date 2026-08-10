<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    public function products(Request $request)
    {
        // if ($response = $this->authorizeAdmin($request)) {
        //     return $response;
        // }

        $products = Product::query()
            ->where('status', 1)
            ->orderBy('product_name')
            ->get([
                'id',
                'product_name',
                'technical_name',
                'item_code',
                'product_category_id',
                'gst',
            ]);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function employees(Request $request)
    {
        // if ($response = $this->authorizeAdmin($request)) {
        //     return $response;
        // }

        $employees = User::query()
            ->with('state:id,name')
            ->where('status', 'Active')
            ->where('is_active', 1)
            
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'mobile',
                'email',
                'user_code',
                'designation_id',
                'state_id',
            ]);

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    private function authorizeAdmin(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user.',
            ], 401);
        }

        if (! $user->hasAnyRole(['master_admin', 'sub_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Master Admin and Sub Admin can access this API.',
            ], 403);
        }

        return null;
    }
}
