<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\State;
use App\Models\User;
use App\Models\Customer;
use App\Models\TallySalesBill;
use Illuminate\Support\Facades\Log;
use Throwable;

class MobileSalesBillController extends Controller
{
    public function getStates(Request $request)
    {
        try {
            $company = $request->attributes->get('company');
            $companyStates = [];
            
            if ($company && !empty($company->state)) {
                $companyStates = array_map('intval', explode(',', $company->state));
            }

            $query = State::select('id', 'name')->orderBy('name', 'asc');
            
            if (!empty($companyStates)) {
                $query->whereIn('id', $companyStates);
            }
            
            $states = $query->get();
            
            return $this->successResponse($states, 'States fetched successfully');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'get-states');
        }
    }

    public function getEmployees(Request $request)
    {
        try {
            $request->validate([
                'state_id' => 'required|integer'
            ]);

            $employees = User::where('state_id', $request->state_id)
                ->where('status', 'active')
                ->select('id', 'name', 'user_code')
                ->orderBy('name', 'asc')
                ->get();

            return $this->successResponse($employees, 'Employees fetched successfully');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'get-employees');
        }
    }

    public function getParties(Request $request)
    {
        try {
            $request->validate([
                'employee_id' => 'required|integer'
            ]);

            $parties = Customer::where('user_id', $request->employee_id)
                ->where('is_active', true)
                ->select('id', 'agro_name as name', 'party_code', 'city')
                ->orderBy('agro_name', 'asc')
                ->get();

            return $this->successResponse($parties, 'Parties fetched successfully');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'get-parties');
        }
    }

    public function getSalesBills(Request $request)
    {
        try {
            $request->validate([
                'party_name' => 'required|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
            ]);

            $query = TallySalesBill::where('party_name', $request->party_name);

            if ($request->filled('start_date')) {
                $query->whereDate('invoice_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('invoice_date', '<=', $request->end_date);
            }

            $records = $query->orderBy('invoice_date', 'desc')->get();

            $grouped = $records->groupBy('invoice_no')->map(function ($items, $invoiceNo) {
                $first = $items->first();
                return [
                    'invoice_no' => $first->invoice_no,
                    'invoice_date' => $first->invoice_date ? $first->invoice_date->format('Y-m-d') : null,
                    'party_name' => $first->party_name,
                    'grand_total' => $items->sum('amount') + $items->sum('gst_amount'),
                ];
            })->values();

            return $this->successResponse($grouped, 'Sales bills fetched successfully');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'get-sales-bills');
        }
    }

    public function getSalesBillDetails(Request $request)
    {
        try {
            $request->validate([
                'invoice_no' => 'required|string',
                'party_name' => 'required|string'
            ]);

            $items = TallySalesBill::where('invoice_no', $request->invoice_no)
                ->where('party_name', $request->party_name)
                ->get();

            if ($items->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => 'Invoice not found',
                    'data' => null
                ], 404);
            }

            $firstItem = $items->first();

            $lineItems = $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product_name_with_packing,
                    'qty' => $item->qty,
                    'amount' => $item->amount,
                ];
            });

            $totalAmount = $items->sum('amount');
            $totalGst = $items->sum('gst_amount');
            $grandTotal = $totalAmount + $totalGst;

            $data = [
                'invoice_no' => $firstItem->invoice_no,
                'invoice_date' => $firstItem->invoice_date ? $firstItem->invoice_date->format('Y-m-d') : null,
                'party_name' => $firstItem->party_name,
                'bill_type' => $firstItem->bill_type,
                'items' => $lineItems,
                'total_amount' => $totalAmount,
                'gst_amount' => $totalGst,
                'grand_total' => $grandTotal,
            ];

            return $this->successResponse($data, 'Sales bill details fetched successfully');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'get-sales-bill-details');
        }
    }

    private function successResponse($data, string $message)
    {
        return response()->json([
            'status' => true,
            'success' => true,
            'count' => is_countable($data) ? count($data) : 1,
            'message' => $message,
            'data' => $data,
        ]);
    }

    private function errorResponse(Throwable $exception, string $endpoint)
    {
        Log::error('Mobile Sales Bill API failed.', [
            'endpoint' => $endpoint,
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        return response()->json([
            'status' => false,
            'success' => false,
            'message' => $exception->getMessage(),
        ], 500);
    }
}
