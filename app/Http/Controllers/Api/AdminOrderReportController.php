<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminOrderReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|integer',
            'state_id' => 'nullable|integer',
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user.',
            ], 401);
        }

        if (!$user->hasAnyRole(['master_admin', 'sub_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Master Admin and Sub Admin can access this report.',
            ], 403);
        }

        $orders = Order::query()
            ->with([
                'user:id,name,mobile,state_id',
                'user.state:id,name',
                'customer:id,agro_name,contact_person_name,phone',
                'items.product:id,product_name,gst',
                'items.packing:id,packing_value,packing_size',
            ])
            ->when($validated['state_id'] ?? null, function (Builder $query, int $stateId) {
                $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('state_id', $stateId));
            })
            ->when($validated['employee_id'] ?? null, function (Builder $query, int $employeeId) {
                $query->where('user_id', $employeeId);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $items = $orders->map(function (Order $order) {
            $amount = (float) $order->items->sum(fn ($item) =>
                max(0, (float) $item->total_price - (float) $item->discount)
            );
            $gstAmount = (float) $order->items->sum('gst');
            $total = (float) $order->items->sum('grand_total');
            $gstRates = $order->items
                ->map(fn ($item) => $item->product?->gst !== null ? (float) $item->product->gst : null)
                ->filter(fn ($rate) => $rate !== null)
                ->unique()
                ->values();

            return [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'order_reference' => 'REF '.$order->order_no,
                'order_date' => $order->created_at?->format('Y-m-d'),
                'order_date_formatted' => $order->created_at?->format('jS F Y'),
                'employee_id' => $order->user_id,
                'employee_name' => $order->user?->name,
                'employee_mobile' => $order->user?->mobile,
                'state_id' => $order->user?->state_id,
                'state_name' => $order->user?->state?->name,
                'party_id' => $order->party_id,
                'party_name' => $order->customer?->agro_name,
                'contact_person_name' => $order->customer?->contact_person_name,
                'party_phone' => $order->customer?->phone,
                'order_type' => $order->order_type,
                'payment_mode' => ucfirst((string) $order->order_type),
                'status' => $order->status,
                'amount' => round($amount, 2),
                'gst_percent' => $gstRates->count() === 1 ? $gstRates->first() : null,
                'gst_amount' => round($gstAmount, 2),
                'total' => round($total, 2),
                'employee_remark' => $order->remark,
                'back_office_remark' => $order->remark2,
                'delivery_place' => $order->delivery_place,
                'preferred_transport' => $order->preferred_transport,
                'products' => $order->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->product_name,
                    'packing_id' => $item->packing_id,
                    'packing' => trim(($item->packing?->packing_value ?? '').' '.($item->packing?->packing_size ?? '')),
                    'quantity' => (float) $item->qty,
                    'shipper_size' => (float) $item->shipper_size,
                    'price' => (float) $item->price,
                    'amount' => (float) $item->total_price,
                    'discount' => (float) $item->discount,
                    'gst_percent' => $item->product?->gst !== null ? (float) $item->product->gst : null,
                    'gst_amount' => (float) $item->gst,
                    'total' => (float) $item->grand_total,
                ])->values(),
                'created_at' => $order->created_at?->toISOString(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'selected_filters' => [
                    'employee_id' => $validated['employee_id'] ?? null,
                    'state_id' => $validated['state_id'] ?? null,
                ],
                'filters' => [
                    'states' => State::query()
                        ->where('status', 1)
                        ->orderBy('name')
                        ->get(['id', 'name']),
                    'employees' => $this->employees($validated['state_id'] ?? null),
                ],
                'summary' => [
                    'total_orders' => $items->count(),
                    'total_amount' => round((float) $items->sum('total'), 2),
                ],
                'items' => $items,
            ],
        ]);
    }

    private function employees(?int $stateId)
    {
        return User::query()
            ->where('status', 'Active')
            ->when($stateId, fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->where(function (Builder $query) {
                $query->whereNull('user_level')
                    ->orWhereNotIn('user_level', ['master_admin', 'sub_admin']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'state_id']);
    }
}
