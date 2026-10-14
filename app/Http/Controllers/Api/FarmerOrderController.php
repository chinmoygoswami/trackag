<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\FarmerOrder;
use App\Models\FarmerOrderDispatch;
use App\Models\FarmerOrderDispatchDetail;
use App\Models\FarmerOrderItem;
use App\Models\Product;
use App\Models\ProductPacking;
use App\Models\State;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FarmerOrderController extends Controller
{
    public function farmers()
    {
        return response()->json(['status' => true, 'data' => Farmer::where('user_id', Auth::id())
            ->orderBy('farmer_name')->get(['id', 'farmer_name', 'mobile_no', 'village', 'state_id', 'district_id', 'taluka_id'])]);
    }

    public function products(Request $request)
    {
        $request->validate(['order_type' => 'required|in:cash,debit']);
        $user = Auth::user();
        $products = Product::where('status', 1)->whereHas('productStates', function ($query) use ($user, $request) {
            $query->where('state_id', $user->state_id)
                ->where($request->order_type === 'cash' ? 'is_ncr' : 'is_rpl', 1);
        })->select('id', 'product_name')->orderBy('product_name')->get();

        return response()->json(['status' => true, 'data' => $products]);
    }

    public function packings(Request $request)
    {
        $request->validate(['product_id' => 'required|integer', 'order_type' => 'required|in:cash,debit']);
        $exists = Product::whereKey($request->product_id)->where('status', 1)
            ->whereHas('productStates', function ($query) use ($request) {
                $query->where('state_id', Auth::user()->state_id)
                    ->where($request->order_type === 'cash' ? 'is_ncr' : 'is_rpl', 1);
            })->exists();
        if (!$exists) return response()->json(['status' => false, 'message' => 'Product not available'], 404);

        $data = ProductPacking::where('product_id', $request->product_id)->where('status', 1)
            ->whereHas('packingStates', fn ($query) => $query->where('state_id', Auth::user()->state_id))
            ->select('id', 'packing_value', 'packing_size')->get();

        return response()->json(['status' => true, 'data' => $data]);
    }

    public function packingDetails(Request $request)
    {
        $request->validate([
            'packing_id' => 'required|integer', 'order_type' => 'required|in:cash,debit',
            'qty' => 'required|numeric|min:1',
        ]);
        $stateId = Auth::user()->state_id;
        $packing = ProductPacking::with([
            'product:id,product_name,gst',
            'prices' => fn ($query) => $query->where('state_id', $stateId),
        ])->where('id', $request->packing_id)->where('status', 1)
            ->whereHas('packingStates', fn ($query) => $query->where('state_id', $stateId))
            ->firstOrFail();
        $price = $packing->prices->first();
        $unitPrice = $request->order_type === 'cash' ? ($price->cash_price ?? 0) : ($price->credit_price ?? 0);
        $total = (float) $request->qty * (float) $packing->unit_in_shipper * (float) $unitPrice;
        $gstPercent = (float) ($packing->product->gst ?? 0);
        $gstAmount = $total * $gstPercent / 100;

        return response()->json(['status' => true, 'data' => [
            'product_name' => $packing->product->product_name ?? null,
            'packing_id' => $packing->id, 'packing_value' => $packing->packing_value,
            'packing_size' => $packing->packing_size, 'shipper_type' => $packing->shipper_type,
            'shipper_size' => (string) $packing->shipper_size, 'unit_in_shipper' => $packing->unit_in_shipper,
            'qty' => $request->qty, 'gst_percent' => $gstPercent, 'gst_amount' => $gstAmount,
            'unit_price' => $unitPrice, 'total_price' => $total, 'grand_total' => $total + $gstAmount,
        ]]);
    }

    public function index()
    {
        $orders = FarmerOrder::with(['farmer:id,farmer_name,mobile_no,village', 'items.product:id,product_name',
            'items.packing:id,packing_value,packing_size', 'items.dispatches'])
            ->where('user_id', Auth::id())->latest()->get();

        return response()->json(['status' => true, 'data' => $orders->map(fn ($order) => [
            'order_id' => $order->id, 'order_no' => $order->order_no, 'farmer_id' => $order->farmer_id,
            'farmer_name' => $order->farmer->farmer_name ?? null, 'farmer_mobile' => $order->farmer->mobile_no ?? null,
            'village' => $order->farmer->village ?? null, 'order_type' => $order->order_type,
            'depo_id' => $order->depo_id, 'delivery_place' => $order->delivery_place,
            'preferred_transport' => $order->preferred_transport, 'remark' => $order->remark,
            'status' => $order->status, 'created_at' => $order->created_at?->format('d-m-Y'),
            'products' => $order->items->map(fn ($item) => [
                'item_id' => $item->id, 'product_id' => $item->product_id, 'product_name' => $item->product->product_name ?? null,
                'packing_id' => $item->packing_id, 'packing_value' => $item->packing->packing_value ?? null,
                'packing_size' => $item->packing->packing_size ?? null, 'shipper_size' => $item->shipper_size,
                'price' => $item->price, 'total_price' => $item->total_price, 'gst' => $item->gst,
                'discount' => $item->discount, 'grand_total' => $item->grand_total, 'qty' => $item->qty,
                'dispatched_qty' => $item->dispatches->sum('dispatch_qty'),
                'pending_qty' => (int) $item->shipper_size - (int) $item->dispatches->sum('dispatch_qty'),
            ]),
        ])]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedOrder($request);
        $user = Auth::user();
        if (!Farmer::where('user_id', $user->id)->whereKey($data['farmer_id'])->exists()) {
            return response()->json(['status' => false, 'message' => 'Farmer not found'], 404);
        }

        $connection = (new FarmerOrder())->getConnection();
        $order = $connection->transaction(function () use ($data, $user, $connection) {
            $stateCode = State::find($user->state_id)?->state_code ?: 'ORD';
            $connection->table('farmer_order_sequences')->insertOrIgnore([
                'state_code' => $stateCode, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $sequence = $connection->table('farmer_order_sequences')->where('state_code', $stateCode)->lockForUpdate()->first();
            $number = $sequence->last_number + 1;
            $connection->table('farmer_order_sequences')->where('state_code', $stateCode)->update(['last_number' => $number, 'updated_at' => now()]);
            $order = FarmerOrder::create([
                'order_no' => $stateCode.'-FAR-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'user_id' => $user->id, 'farmer_id' => $data['farmer_id'], 'order_type' => $data['order_type'],
                'depo_id' => $data['depo_id'] ?? null, 'delivery_place' => $data['delivery_place'] ?? null,
                'preferred_transport' => $data['preferred_transport'] ?? null, 'remark' => $data['remark'] ?? null,
                'status' => 'pending',
            ]);
            foreach ($data['products'] as $product) {
                $order->items()->create($product);
            }
            return $order;
        });

        return response()->json(['status' => true, 'message' => 'Farmer order created successfully',
            'order_id' => $order->id, 'order_no' => $order->order_no, 'farmer_id' => $order->farmer_id], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $this->validatedOrder($request);
        $order = FarmerOrder::where('user_id', Auth::id())->whereKey($id)->firstOrFail();
        if (!in_array($order->status, ['pending', 'hold'], true)) {
            return response()->json(['status' => false, 'message' => 'Only pending or held farmer orders can be edited'], 409);
        }
        if (!Farmer::where('user_id', Auth::id())->whereKey($data['farmer_id'])->exists()) {
            return response()->json(['status' => false, 'message' => 'Farmer not found'], 404);
        }

        (new FarmerOrder())->getConnection()->transaction(function () use ($order, $data) {
            $order->update([
                'farmer_id' => $data['farmer_id'], 'order_type' => $data['order_type'],
                'depo_id' => $data['depo_id'] ?? null, 'delivery_place' => $data['delivery_place'] ?? null,
                'preferred_transport' => $data['preferred_transport'] ?? null, 'remark' => $data['remark'] ?? null,
            ]);
            $order->items()->delete();
            foreach ($data['products'] as $product) $order->items()->create($product);
        });

        return response()->json(['status' => true, 'message' => 'Farmer order updated successfully', 'order_id' => $order->id]);
    }

    public function destroy(int $id)
    {
        $order = FarmerOrder::where('user_id', Auth::id())->whereKey($id)->firstOrFail();
        if (!in_array($order->status, ['pending', 'hold', 'rejected'], true)) {
            return response()->json(['status' => false, 'message' => 'This farmer order can no longer be deleted'], 409);
        }
        $order->delete();
        return response()->json(['status' => true, 'message' => 'Farmer order deleted successfully']);
    }

    public function details(Request $request, int $id)
    {
        $order = FarmerOrder::with(['farmer', 'items.product', 'items.packing', 'items.dispatches.detail'])
            ->where('user_id', Auth::id())->find($id);
        if (!$order) return response()->json(['status' => false, 'message' => 'Farmer order not found'], 404);

        $items = $order->items->map(fn ($item) => [
            'product_name' => $item->product->product_name ?? '-',
            'packing' => trim(($item->packing->packing_value ?? '').' '.($item->packing->packing_size ?? '')) ?: '-',
            'qty' => $item->qty, 'shipper_size' => $item->shipper_size, 'price' => $item->price,
            'total_price' => $item->total_price, 'gst' => $item->gst, 'discount' => $item->discount,
            'grand_total' => $item->grand_total,
            'dispatched_qty' => $item->dispatches->sum('dispatch_qty'),
        ])->all();
        $dispatchDetail = $order->dispatches()->with('detail')->latest()->first()?->detail;
        $pdf = Pdf::loadView('api.order.farmer-pdf', compact('order', 'items', 'dispatchDetail'))->setPaper('a4', 'portrait');
        $path = 'farmer_order_pdfs/farmer_order_'.$order->id.'_'.time().'.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        return response()->json(['status' => true, 'message' => 'Farmer order PDF generated successfully', 'data' => [
            'order_id' => $order->id, 'order_no' => $order->order_no,
            'farmer_id' => $order->farmer_id, 'farmer_name' => $order->farmer->farmer_name,
            'pdf_url' => $request->getSchemeAndHttpHost().'/storage/'.$path,
        ]]);
    }

    private function validatedOrder(Request $request): array
    {
        return $request->validate([
            'farmer_id' => 'required|integer', 'order_type' => 'required|in:cash,debit',
            'depo_id' => 'nullable|integer', 'delivery_place' => 'nullable|string|max:255',
            'preferred_transport' => 'nullable|string|max:255', 'remark' => 'nullable|string',
            'products' => 'required|array|min:1', 'products.*.product_id' => 'required|integer',
            'products.*.packing_id' => 'nullable|integer', 'products.*.shipper_size' => 'nullable|integer|min:0',
            'products.*.price' => 'required|numeric|min:0', 'products.*.total_price' => 'required|numeric|min:0',
            'products.*.gst' => 'nullable|numeric|min:0', 'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.grand_total' => 'required|numeric|min:0', 'products.*.qty' => 'nullable|integer|min:1',
        ]);
    }

    public function adminDispatchData(FarmerOrder $farmerOrder)
    {
        $farmerOrder->load(['farmer', 'items.product', 'items.packing', 'items.dispatches.detail']);
        return response()->json(['status' => true, 'order' => [
            'id' => $farmerOrder->id, 'order_no' => $farmerOrder->order_no,
            'farmer_name' => $farmerOrder->farmer->farmer_name ?? null, 'status' => $farmerOrder->status,
        ], 'items' => $farmerOrder->items->map(fn ($item) => [
            'id' => $item->id, 'product' => $item->product->product_name ?? null,
            'packing' => trim(($item->packing->packing_value ?? '').' '.($item->packing->packing_size ?? '')),
            'order_qty' => $item->qty, 'shipper_size' => $item->shipper_size,
            'dispatched_qty' => $item->dispatches->sum('dispatch_qty'),
            'pending_qty' => max(0, (int) $item->shipper_size - (int) $item->dispatches->sum('dispatch_qty')),
        ]), 'dispatches' => $farmerOrder->dispatches()->with('detail')->get()]);
    }

    public function adminUpdateItem(Request $request)
    {
        $data = $request->validate(['item_id' => 'required|integer', 'shipper_size' => 'required|integer|min:0']);
        $item = FarmerOrderItem::with(['order', 'product'])->findOrFail($data['item_id']);
        if (!in_array($item->order->status, ['pending', 'hold'], true)) {
            return response()->json(['success' => false, 'message' => 'Only pending or held farmer order items can be edited'], 409);
        }
        $amount = (float) $item->price * $data['shipper_size'];
        $discounted = max(0, $amount - (float) $item->discount);
        $gst = $discounted * (float) ($item->product->gst ?? 0) / 100;
        $item->update(['shipper_size' => $data['shipper_size'], 'total_price' => $amount, 'gst' => $gst, 'grand_total' => round($discounted + $gst, 2)]);
        return response()->json(['success' => true, 'message' => 'Farmer order item updated', 'data' => ['grand_total' => number_format($item->grand_total, 2, '.', '')]]);
    }

    public function adminUpdateItemsQty(Request $request)
    {
        $data = $request->validate([
            'farmer_order_id' => 'required|integer', 'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer', 'items.*.shipper_size' => 'required|integer|min:0',
        ]);
        $order = FarmerOrder::findOrFail($data['farmer_order_id']);
        if (!in_array($order->status, ['pending', 'hold'], true)) {
            return response()->json(['success' => false, 'message' => 'Only pending or held farmer orders can be edited'], 409);
        }
        foreach ($data['items'] as $itemData) {
            $item = FarmerOrderItem::with('product')->where('farmer_order_id', $order->id)->find($itemData['id']);
            if (!$item) continue;
            $amount = (float) $item->price * $itemData['shipper_size'];
            $discounted = max(0, $amount - (float) $item->discount);
            $gst = $discounted * (float) ($item->product->gst ?? 0) / 100;
            $item->update(['shipper_size' => $itemData['shipper_size'], 'total_price' => $amount, 'gst' => $gst, 'grand_total' => round($discounted + $gst, 2)]);
        }
        return response()->json(['success' => true, 'message' => 'Farmer order quantities updated']);
    }

    public function adminDispatch(Request $request)
    {
        $data = $request->validate([
            'farmer_order_id' => 'required|integer', 'dispatch_items' => 'required|array|min:1',
            'dispatch_items.*.item_id' => 'required|integer', 'dispatch_items.*.dispatch_qty' => 'required|integer|min:0',
            'lr_number' => 'nullable|string|max:255', 'transport_name' => 'nullable|string|max:255',
            'vehicle_no' => 'nullable|string|max:255', 'dispatch_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        $order = FarmerOrder::with('items')->findOrFail($data['farmer_order_id']);
        if (!in_array($order->status, ['approved', 'part_dispatched'], true)) {
            return response()->json(['status' => false, 'message' => 'Only approved farmer orders can be dispatched'], 409);
        }
        $image = $request->hasFile('dispatch_image') ? $request->file('dispatch_image')->store('farmer_dispatch_images', 'public') : null;
        $connection = $order->getConnection();
        $connection->transaction(function () use ($data, $order, $image, $request) {
            $dispatchedAny = false;
            foreach ($data['dispatch_items'] as $dispatchItem) {
                $item = $order->items->firstWhere('id', $dispatchItem['item_id']);
                if (!$item) throw ValidationException::withMessages(['dispatch_items' => 'An item does not belong to this farmer order.']);
                $before = (int) $item->dispatches()->sum('dispatch_qty');
                $remaining = max(0, (int) $item->shipper_size - $before);
                $qty = (int) $dispatchItem['dispatch_qty'];
                if ($qty > $remaining) throw ValidationException::withMessages(['dispatch_items' => 'Dispatch quantity exceeds pending quantity.']);
                if ($qty > 0) {
                    $dispatchedAny = true;
                    $dispatch = FarmerOrderDispatch::create([
                        'farmer_order_id' => $order->id, 'farmer_order_item_id' => $item->id,
                        'dispatch_qty' => $qty, 'dispatch_type' => $qty === $remaining ? 'final' : 'partial',
                    ]);
                    FarmerOrderDispatchDetail::create([
                        'farmer_order_dispatch_id' => $dispatch->id, 'lr_number' => $request->lr_number,
                        'transport_name' => $request->transport_name, 'vehicle_no' => $request->vehicle_no,
                        'dispatch_image' => $image, 'dispatch_date' => now(),
                    ]);
                }
            }
            if (!$dispatchedAny) {
                throw ValidationException::withMessages(['dispatch_items' => 'Enter at least one dispatch quantity greater than zero.']);
            }
            $pendingTotal = $order->items->sum(function ($item) {
                return max(0, (int) $item->shipper_size - (int) $item->dispatches()->sum('dispatch_qty'));
            });
            $order->status = $pendingTotal === 0 ? 'dispatched' : 'part_dispatched';
            $order->save();
        });

        $this->notifyEmployee($order, $order->status === 'dispatched' ? 'Your farmer order is fully dispatched.' : 'Your farmer order is partially dispatched.');

        return response()->json(['status' => true, 'message' => 'Farmer order dispatch saved successfully']);
    }

    public function adminStatus(Request $request)
    {
        $data = $request->validate([
            'farmer_order_id' => 'required|integer',
            'status' => 'required|in:approved,hold,rejected', 'remark' => 'nullable|string',
        ]);
        if (in_array($data['status'], ['hold', 'rejected'], true) && empty($data['remark'])) {
            throw ValidationException::withMessages(['remark' => 'A remark is required for hold or rejected orders.']);
        }
        $order = FarmerOrder::findOrFail($data['farmer_order_id']);
        if (!($order->status === 'pending' && in_array($data['status'], ['approved', 'hold', 'rejected'], true))
            && !($order->status === 'hold' && $data['status'] === 'approved')) {
            return response()->json(['status' => false, 'message' => 'Invalid farmer order status transition'], 409);
        }
        $order->status = $data['status'];
        if (isset($data['remark'])) $order->remark2 = $data['remark'];
        $order->save();
        $this->notifyEmployee($order, 'Farmer order #'.$order->order_no.' status updated to '.$order->status.'.');
        return response()->json(['status' => true, 'message' => 'Farmer order status updated']);
    }

    private function notifyEmployee(FarmerOrder $order, string $message): void
    {
        try {
            $order->loadMissing('user');
            if ($order->user && $order->user->fcm_token) {
                app(\App\Services\FirebaseService::class)->sendNotification(
                    $order->user->fcm_token,
                    'Farmer Order #'.$order->order_no,
                    $message,
                    ['type' => 'farmer_order_status', 'farmer_order_id' => (string) $order->id, 'status' => $order->status],
                    $order->user->id
                );
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function adminIndex(Request $request)
    {
        $orders = FarmerOrder::with(['user.state', 'farmer', 'items.product', 'items.packing', 'items.dispatches.detail'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('employee_id'), fn ($query) => $query->where('user_id', $request->employee_id))
            ->when($request->filled('farmer_id'), fn ($query) => $query->where('farmer_id', $request->farmer_id))
            ->when($request->filled('from_date'), fn ($query) => $query->whereDate('created_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn ($query) => $query->whereDate('created_at', '<=', $request->to_date))
            ->latest()->get();

        return view('admin.farmer-order.index', [
            'orders' => $orders,
            'employees' => \App\Models\User::where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'farmers' => Farmer::orderBy('farmer_name')->get(['id', 'farmer_name', 'village']),
        ]);
    }

    public function adminReport(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|integer', 'state_id' => 'nullable|integer',
            'farmer_id' => 'nullable|integer', 'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['master_admin', 'sub_admin'])) {
            return response()->json(['success' => false, 'message' => 'Only Master Admin and Sub Admin can access this report.'], 403);
        }
        $orders = FarmerOrder::with(['user:id,name,mobile,state_id', 'user.state:id,name', 'farmer:id,farmer_name,mobile_no,village',
            'items.product:id,product_name,gst', 'items.packing:id,packing_value,packing_size'])
            ->when($validated['employee_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($validated['farmer_id'] ?? null, fn ($query, $id) => $query->where('farmer_id', $id))
            ->when($validated['state_id'] ?? null, fn ($query, $id) => $query->whereHas('user', fn ($userQuery) => $userQuery->where('state_id', $id)))
            ->when($validated['start_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($validated['end_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()->get();

        $rows = $orders->map(fn ($order) => [
            'id' => $order->id, 'order_no' => $order->order_no, 'order_date' => $order->created_at?->toISOString(),
            'employee_id' => $order->user_id, 'employee_name' => $order->user->name ?? null,
            'state_id' => $order->user->state_id ?? null, 'state_name' => $order->user->state->name ?? null,
            'farmer_id' => $order->farmer_id, 'farmer_name' => $order->farmer->farmer_name ?? null,
            'farmer_mobile' => $order->farmer->mobile_no ?? null, 'village' => $order->farmer->village ?? null,
            'order_type' => $order->order_type, 'status' => $order->status,
            'amount' => round((float) $order->items->sum('total_price'), 2),
            'gst_amount' => round((float) $order->items->sum('gst'), 2),
            'total' => round((float) $order->items->sum('grand_total'), 2),
            'products' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id, 'product_name' => $item->product->product_name ?? null,
                'packing_id' => $item->packing_id, 'packing' => trim(($item->packing->packing_value ?? '').' '.($item->packing->packing_size ?? '')),
                'qty' => $item->qty, 'shipper_size' => $item->shipper_size, 'price' => $item->price,
                'amount' => $item->total_price, 'gst' => $item->gst, 'discount' => $item->discount, 'total' => $item->grand_total,
            ]),
        ]);
        return response()->json(['success' => true, 'data' => [
            'selected_filters' => [
                'employee_id' => $validated['employee_id'] ?? null,
                'state_id' => $validated['state_id'] ?? null,
                'farmer_id' => $validated['farmer_id'] ?? null,
            ],
            'filters' => [
                'states' => \App\Models\State::where('status', 1)->orderBy('name')->get(['id', 'name']),
                'employees' => \App\Models\User::where('status', 'Active')->orderBy('name')->get(['id', 'name', 'state_id']),
                'farmers' => Farmer::orderBy('farmer_name')->get(['id', 'farmer_name', 'village']),
            ],
            'summary' => ['total_orders' => $rows->count(), 'total_amount' => round((float) $rows->sum('total'), 2)],
            'items' => $rows,
        ]]);
    }
}
