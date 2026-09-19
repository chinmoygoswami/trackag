<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\State;
use App\Models\User;
use App\Models\Customer;
use App\Models\TallySalesBill;
use Illuminate\Support\Facades\DB;

class MobileSalesBillController extends Controller
{
    /**
     * Get all states (optionally filter by active users, but here we return all)
     */
    public function getStates(Request $request)
    {
        $states = State::select('id', 'name')->orderBy('name', 'asc')->get();
        return response()->json([
            'success' => true,
            'data' => $states
        ]);
    }

    /**
     * Get employees belonging to a specific state
     */
    public function getEmployees(Request $request)
    {
        $request->validate([
            'state_id' => 'required|integer|exists:states,id'
        ]);

        // Assuming user_type or role differentiates employees, or we just return all users in the state
        // For safety, returning users with the state_id
        $employees = User::where('state_id', $request->state_id)
            ->where('status', 'active')
            ->select('id', 'name', 'user_code')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $employees
        ]);
    }

    /**
     * Get parties (customers) assigned to a specific employee
     */
    public function getParties(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:users,id'
        ]);

        $parties = Customer::where('user_id', $request->employee_id)
            ->where('is_active', true)
            ->select('id', 'name', 'agro_name', 'party_code', 'city')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $parties
        ]);
    }

    /**
     * Get sales bills grouped by invoice for a specific party within a date range
     */
    public function getSalesBills(Request $request)
    {
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

        // Fetch all matching records, then group them by invoice_no in PHP
        // Because a bill might have multiple items, we group them to show unique bills in the listing
        $records = $query->orderBy('invoice_date', 'desc')->get();

        $grouped = $records->groupBy('invoice_no')->map(function ($items, $invoiceNo) {
            $first = $items->first();
            return [
                'invoice_no' => $first->invoice_no,
                'invoice_date' => $first->invoice_date ? $first->invoice_date->format('Y-m-d') : null,
                'party_name' => $first->party_name,
                // Summing up grand_total across all items of this invoice if they are split
                'grand_total' => $items->sum('amount') + $items->sum('gst_amount'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $grouped
        ]);
    }

    /**
     * Get detailed items for a specific sales bill
     */
    public function getSalesBillDetails(Request $request)
    {
        $request->validate([
            'invoice_no' => 'required|string',
            'party_name' => 'required|string'
        ]);

        $items = TallySalesBill::where('invoice_no', $request->invoice_no)
            ->where('party_name', $request->party_name)
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        $firstItem = $items->first();

        // Prepare items array
        $lineItems = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_name' => $item->product_name_with_packing,
                'qty' => $item->qty,
                'amount' => $item->amount,
            ];
        });

        // Calculate totals
        $totalAmount = $items->sum('amount');
        $totalGst = $items->sum('gst_amount');
        $grandTotal = $totalAmount + $totalGst;

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_no' => $firstItem->invoice_no,
                'invoice_date' => $firstItem->invoice_date ? $firstItem->invoice_date->format('Y-m-d') : null,
                'party_name' => $firstItem->party_name,
                'bill_type' => $firstItem->bill_type,
                'items' => $lineItems,
                'total_amount' => $totalAmount,
                'gst_amount' => $totalGst,
                'grand_total' => $grandTotal,
            ]
        ]);
    }
}
