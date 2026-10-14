<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\FarmerOrder;
use App\Models\FarmerOrderDispatch;
use App\Models\FarmerOrderDispatchDetail;
use App\Models\FarmerOrderItem;
use App\Models\Depo;
use App\Models\Product;
use App\Models\ProductPacking;
use App\Models\State;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                ->where('status', 1)
                ->where($request->order_type === 'cash' ? 'is_ncr' : 'is_rpl', 1);
        })->select('id', 'product_name')->orderBy('product_name')->get();

        return response()->json(['status' => true, 'data' => $products]);
    }

    public function packings(Request $request)
    {
        $request->validate(['product_id' => 'required|integer', 'order_type' => 'required|in:cash,debit']);
        $user = Auth::user();
        $priceColumn = $request->order_type === 'cash' ? 'cash_price' : 'credit_price';
        $data = ProductPacking::where('product_id', $request->product_id)->where('status', 1)
            ->whereHas('product', function ($query) use ($user, $request) {
                $query->where('status', 1)->whereHas('productStates', function ($stateQuery) use ($user, $request) {
                    $stateQuery->where('state_id', $user->state_id)->where('status', 1)
                        ->where($request->order_type === 'cash' ? 'is_ncr' : 'is_rpl', 1);
                });
            })
            ->whereHas('packingStates', fn ($query) => $query->where('state_id', $user->state_id))
            ->whereHas('farmerPrices', fn ($query) => $query->where('state_id', $user->state_id)->whereNotNull($priceColumn))
            ->select('id', 'packing_value', 'packing_size')->get();

        if ($data->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No eligible packings found for this product and order type'], 404);
        }

        return response()->json(['status' => true, 'data' => $data]);
    }

    public function packingDetails(Request $request)
    {
        $request->validate([
            'packing_id' => 'required|integer', 'order_type' => 'required|in:cash,debit',
            'qty' => 'required|integer|min:1', 'discount' => 'nullable|numeric|min:0',
        ]);
        $user = Auth::user();
        $packing = $this->findOrderablePacking((int) $request->packing_id, $request->order_type, $user->state_id);
        if (!$packing) {
            return response()->json(['status' => false, 'message' => 'Packing is not available for this state and order type'], 404);
        }
        $quote = $this->calculateLine($packing, $request->order_type, (int) $request->qty, (float) ($request->discount ?? 0));
        if (isset($quote['error'])) {
            return response()->json(['status' => false, 'message' => $quote['error']], 422);
        }

        return response()->json(['status' => true, 'data' => [
            'product_name' => $packing->product->product_name ?? null,
            'packing_id' => $packing->id, 'packing_value' => $packing->packing_value,
            'packing_size' => $packing->packing_size, 'shipper_type' => $packing->shipper_type,
            'qty' => (int) $request->qty, 'gst_percent' => $quote['gst_percent'], 'gst_amount' => $quote['gst'],
            'unit_price' => $quote['price'], 'total_price' => $quote['total_price'], 'discount' => $quote['discount'],
            'grand_total' => $quote['grand_total'],
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
                'packing_size' => $item->packing->packing_size ?? null,
                'price' => $item->price, 'total_price' => $item->total_price, 'gst' => $item->gst,
                'discount' => $item->discount, 'grand_total' => $item->grand_total, 'qty' => $item->qty,
                'dispatched_qty' => $item->dispatches->sum('dispatch_qty'),
                'pending_qty' => max(0, (int) $item->qty - (int) $item->dispatches->sum('dispatch_qty')),
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
            'qty' => $item->qty, 'price' => $item->price,
            'total_price' => $item->total_price, 'gst' => $item->gst, 'discount' => $item->discount,
            'grand_total' => $item->grand_total,
            'dispatched_qty' => $item->dispatches->sum('dispatch_qty'),
        ])->all();
        $dispatchDetail = $order->dispatches()->with('detail')->latest()->first()?->detail;
        $pdf = Pdf::loadView('api.order.farmer-pdf', compact('order', 'items', 'dispatchDetail'))->setPaper('a4', 'portrait');
        $path = 'farmer_order_pdfs/farmer_order_'.$order->id.'_'.Str::uuid().'.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        return response()->json(['status' => true, 'message' => 'Farmer order PDF generated successfully', 'data' => [
            'order_id' => $order->id, 'order_no' => $order->order_no,
            'farmer_id' => $order->farmer_id, 'farmer_name' => $order->farmer->farmer_name,
            'pdf_url' => $request->getSchemeAndHttpHost().'/storage/'.$path,
        ]]);
    }

    private function validatedOrder(Request $request): array
    {
        $data = $request->validate([
            'farmer_id' => 'required|integer', 'order_type' => 'required|in:cash,debit',
            'depo_id' => 'nullable|integer', 'delivery_place' => 'nullable|string|max:255',
            'preferred_transport' => 'nullable|string|max:255', 'remark' => 'nullable|string',
            'products' => 'required|array|min:1', 'products.*.product_id' => 'required|integer',
            'products.*.packing_id' => 'required|integer',
            // Retained as an ignored compatibility field for older farmer clients.
            'products.*.shipper_size' => 'nullable|numeric|min:0',
            // Keep accepting the old client fields, but never trust their totals.
            'products.*.price' => 'sometimes|nullable|numeric|min:0',
            'products.*.total_price' => 'sometimes|nullable|numeric|min:0',
            'products.*.gst' => 'sometimes|nullable|numeric|min:0',
            'products.*.discount' => 'sometimes|nullable|numeric|min:0',
            'products.*.grand_total' => 'sometimes|nullable|numeric|min:0',
            'products.*.qty' => 'nullable|integer|min:1',
        ]);

        $user = Auth::user();
        foreach ($data['products'] as $index => $line) {
            $packing = $this->findOrderablePacking((int) $line['packing_id'], $data['order_type'], $user->state_id);
            if (!$packing || (int) $packing->product_id !== (int) $line['product_id']) {
                throw ValidationException::withMessages([
                    "products.{$index}.packing_id" => 'The selected packing is not available for this product, state, or order type.',
                ]);
            }

            $qty = (int) ($line['qty'] ?? 1);
            $discount = (float) ($line['discount'] ?? 0);
            $calculated = $this->calculateLine($packing, $data['order_type'], $qty, $discount);
            if (isset($calculated['error'])) {
                throw ValidationException::withMessages(["products.{$index}.discount" => $calculated['error']]);
            }

            // Persist server-calculated values while preserving the existing line-item contract.
            $data['products'][$index] = [
                'product_id' => (int) $packing->product_id,
                'packing_id' => (int) $packing->id,
                'shipper_size' => null,
                'price' => $calculated['price'],
                'total_price' => $calculated['total_price'],
                'gst' => $calculated['gst'],
                'discount' => $calculated['discount'],
                'grand_total' => $calculated['grand_total'],
                'qty' => $qty,
            ];
        }

        return $data;
    }

    private function findOrderablePacking(int $packingId, string $orderType, ?int $stateId): ?ProductPacking
    {
        if (!$stateId) return null;

        $priceColumn = $orderType === 'cash' ? 'cash_price' : 'credit_price';
        return ProductPacking::with([
            'product:id,product_name,gst,status',
            'farmerPrices' => fn ($query) => $query->where('state_id', $stateId)->whereNotNull($priceColumn),
        ])->whereKey($packingId)
            ->where('status', 1)
            ->whereHas('product', function ($query) use ($stateId, $orderType) {
                $query->where('status', 1)->whereHas('productStates', function ($stateQuery) use ($stateId, $orderType) {
                    $stateQuery->where('state_id', $stateId)->where('status', 1)
                        ->where($orderType === 'cash' ? 'is_ncr' : 'is_rpl', 1);
                });
            })
            ->whereHas('packingStates', fn ($query) => $query->where('state_id', $stateId))
            ->whereHas('farmerPrices', fn ($query) => $query->where('state_id', $stateId)->whereNotNull($priceColumn))
            ->first();
    }

    private function calculateLine(ProductPacking $packing, string $orderType, int $qty, float $discount): array
    {
        $price = $packing->farmerPrices->first();
        $unitPrice = $orderType === 'cash' ? ($price->cash_price ?? null) : ($price->credit_price ?? null);
        if ($unitPrice === null) {
            return ['error' => 'A valid price is not configured for this packing and order type.'];
        }

        // Farmer qty is the number of ordered units. Packing/shipper quantities are not price multipliers.
        $unitPrice = round((float) $unitPrice, 2);
        $total = round($qty * $unitPrice, 2);
        $discount = round($discount, 2);
        if ($discount > $total) {
            return ['error' => 'Discount cannot exceed the line amount.'];
        }

        $taxableAmount = round($total - $discount, 2);
        $gstPercent = (float) ($packing->product->gst ?? 0);
        $gstAmount = round($taxableAmount * $gstPercent / 100, 2);

        return [
            'price' => $unitPrice,
            'total_price' => $total,
            'gst_percent' => $gstPercent,
            'gst' => $gstAmount,
            'discount' => $discount,
            'grand_total' => round($taxableAmount + $gstAmount, 2),
        ];
    }

    public function adminDispatchData(FarmerOrder $farmerOrder)
    {
        $farmerOrder->load(['farmer', 'items.product', 'items.packing', 'items.dispatches.detail']);
        return response()->json(['status' => true, 'order' => [
            'id' => $farmerOrder->id, 'order_no' => $farmerOrder->order_no,
            'farmer_name' => $farmerOrder->farmer->farmer_name ?? null, 'status' => $farmerOrder->status,
        ], 'items' => $farmerOrder->items->map(fn ($item) => [
            'id' => $item->id, 'product' => $item->product->product_name ?? null,
            'packing_value' => $item->packing->packing_value ?? null,
            'packing' => $item->packing->packing_size ?? null,
            'order_qty' => $item->qty, 'qty' => $item->qty,
            'price' => $item->price, 'gst_percent' => $item->product->gst ?? 0,
            'gst' => $item->gst, 'discount' => $item->discount,
            'total_price' => $item->total_price, 'grand_total' => $item->grand_total,
            'dispatched_qty' => $item->dispatches->sum('dispatch_qty'),
            'pending_qty' => max(0, (int) $item->qty - (int) $item->dispatches->sum('dispatch_qty')),
        ]), 'dispatches' => $farmerOrder->dispatches()->with('detail')->get()->map(fn ($dispatch) => [
            'id' => $dispatch->id,
            'order_item_id' => $dispatch->farmer_order_item_id,
            'dispatch_qty' => $dispatch->dispatch_qty,
            'dispatch_type' => $dispatch->dispatch_type,
            'created_at' => $dispatch->created_at,
            'detail' => $dispatch->detail,
        ])]);
    }

    public function adminUpdateItem(Request $request)
    {
        $data = $request->validate(['item_id' => 'required|integer', 'qty' => 'required|integer|min:1']);
        $item = FarmerOrderItem::with('order')->findOrFail($data['item_id']);
        $item->getConnection()->transaction(function () use ($data, $item) {
            $order = FarmerOrder::whereKey($item->farmer_order_id)->lockForUpdate()->firstOrFail();
            if (!in_array($order->status, ['pending', 'hold'], true)) {
                throw ValidationException::withMessages(['item_id' => 'Only pending or held farmer order items can be edited.']);
            }
            $lockedItem = FarmerOrderItem::with('product')->where('farmer_order_id', $order->id)
                ->lockForUpdate()->findOrFail($item->id);
            $this->recalculateAdminQty($lockedItem, (int) $data['qty']);
        });
        $item->refresh();
        return response()->json(['success' => true, 'message' => 'Farmer order item updated', 'data' => ['grand_total' => number_format($item->grand_total, 2, '.', '')]]);
    }

    public function adminUpdateItemsQty(Request $request)
    {
        $data = $request->validate([
            'farmer_order_id' => 'required|integer', 'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|distinct',
            'items.*.qty' => 'required|integer|min:1',
        ]);
        $order = FarmerOrder::findOrFail($data['farmer_order_id']);
        $order->getConnection()->transaction(function () use ($data, $order) {
            $lockedOrder = FarmerOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!in_array($lockedOrder->status, ['pending', 'hold'], true)) {
                throw ValidationException::withMessages(['farmer_order_id' => 'Only pending or held farmer orders can be edited.']);
            }

            $ids = collect($data['items'])->pluck('id')->map(fn ($id) => (int) $id);
            $items = FarmerOrderItem::with('product')->where('farmer_order_id', $lockedOrder->id)
                ->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            if ($items->count() !== $ids->count()) {
                throw ValidationException::withMessages(['items' => 'Every item must belong to the selected farmer order.']);
            }

            foreach ($data['items'] as $itemData) {
                $item = $items->get((int) $itemData['id']);
                $this->recalculateAdminQty($item, (int) $itemData['qty']);
            }
        });
        return response()->json(['success' => true, 'message' => 'Farmer order quantities updated']);
    }

    private function recalculateAdminQty(FarmerOrderItem $item, int $qty): void
    {
        $item->loadMissing('product');
        $amount = round((float) $item->price * $qty, 2);
        $discount = min((float) $item->discount, $amount);
        $taxable = round($amount - $discount, 2);
        $gst = round($taxable * (float) ($item->product->gst ?? 0) / 100, 2);
        $item->update([
            'qty' => $qty,
            'total_price' => $amount,
            'discount' => $discount,
            'gst' => $gst,
            'grand_total' => round($taxable + $gst, 2),
        ]);
    }

    public function adminDispatch(Request $request)
    {
        $data = $request->validate([
            'farmer_order_id' => 'required|integer', 'dispatch_items' => 'required|array|min:1',
            'dispatch_items.*.item_id' => 'required|integer|distinct', 'dispatch_items.*.dispatch_qty' => 'required|integer|min:0',
            'lr_number' => 'nullable|string|max:255', 'transport_name' => 'nullable|string|max:255',
            'vehicle_no' => 'nullable|string|max:255', 'dispatch_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        $connection = (new FarmerOrder())->getConnection();
        $image = null;
        try {
            $order = $connection->transaction(function () use ($data, $request, &$image) {
                $order = FarmerOrder::with('items')->whereKey($data['farmer_order_id'])->lockForUpdate()->firstOrFail();
                if (!in_array($order->status, ['approved', 'part_dispatched'], true)) {
                    throw ValidationException::withMessages(['farmer_order_id' => 'Only approved farmer orders can be dispatched.']);
                }

                $dispatchPlan = [];
                $dispatchedAny = false;
                foreach ($data['dispatch_items'] as $dispatchItem) {
                    $item = $order->items->firstWhere('id', $dispatchItem['item_id']);
                    if (!$item) throw ValidationException::withMessages(['dispatch_items' => 'An item does not belong to this farmer order.']);
                    $before = (int) $item->dispatches()->sum('dispatch_qty');
                    $remaining = max(0, (int) $item->qty - $before);
                    $qty = (int) $dispatchItem['dispatch_qty'];
                    if ($qty > $remaining) throw ValidationException::withMessages(['dispatch_items' => 'Dispatch quantity exceeds pending quantity.']);
                    if ($qty > 0) {
                        $dispatchedAny = true;
                        $dispatchPlan[] = ['item' => $item, 'qty' => $qty, 'remaining' => $remaining];
                    }
                }
                if (!$dispatchedAny) {
                    throw ValidationException::withMessages(['dispatch_items' => 'Enter at least one dispatch quantity greater than zero.']);
                }

                // Save the upload only after all order and quantity checks passed.
                $image = $request->hasFile('dispatch_image')
                    ? $request->file('dispatch_image')->store('farmer_dispatch_images', 'public')
                    : null;
                foreach ($dispatchPlan as $planned) {
                    $dispatch = FarmerOrderDispatch::create([
                        'farmer_order_id' => $order->id,
                        'farmer_order_item_id' => $planned['item']->id,
                        'dispatch_qty' => $planned['qty'],
                        'dispatch_type' => $planned['qty'] === $planned['remaining'] ? 'final' : 'partial',
                    ]);
                    FarmerOrderDispatchDetail::create([
                        'farmer_order_dispatch_id' => $dispatch->id,
                        'lr_number' => $request->lr_number,
                        'transport_name' => $request->transport_name,
                        'vehicle_no' => $request->vehicle_no,
                        'dispatch_image' => $image,
                        'dispatch_date' => now(),
                    ]);
                }

                $pendingTotal = $order->items->sum(function ($item) {
                    return max(0, (int) $item->qty - (int) $item->dispatches()->sum('dispatch_qty'));
                });
                $order->status = $pendingTotal === 0 ? 'dispatched' : 'part_dispatched';
                $order->save();
                return $order;
            });
        } catch (\Throwable $exception) {
            if ($image) Storage::disk('public')->delete($image);
            throw $exception;
        }

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
        $connection = (new FarmerOrder())->getConnection();
        $order = $connection->transaction(function () use ($data) {
            $order = FarmerOrder::whereKey($data['farmer_order_id'])->lockForUpdate()->firstOrFail();
            if (!($order->status === 'pending' && in_array($data['status'], ['approved', 'hold', 'rejected'], true))
                && !($order->status === 'hold' && $data['status'] === 'approved')) {
                return null;
            }
            $order->status = $data['status'];
            if (isset($data['remark'])) $order->remark2 = $data['remark'];
            $order->save();
            return $order;
        });
        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Invalid farmer order status transition'], 409);
        }
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
        $orders = FarmerOrder::with([
            'user.state', 'farmer.district:id,name', 'farmer.talukaDetail:id,name', 'items.product', 'items.packing', 'items.dispatches.detail',
        ])
            ->when($request->filled('from_date'), fn ($query) => $query->whereDate('created_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn ($query) => $query->whereDate('created_at', '<=', $request->to_date))
            ->when($request->filled('state_id'), fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery->where('state_id', $request->state_id)))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->user_id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('farmer_id'), fn ($query) => $query->where('farmer_id', $request->farmer_id))
            ->when($request->filled('district_id'), fn ($query) => $query->whereHas('farmer', fn ($farmerQuery) => $farmerQuery->where('district_id', $request->district_id)))
            ->when($request->filled('taluka_id'), fn ($query) => $query->whereHas('farmer', fn ($farmerQuery) => $farmerQuery->where('taluka_id', $request->taluka_id)))
            ->when($request->filled('village'), fn ($query) => $query->whereHas('farmer', fn ($farmerQuery) => $farmerQuery->where('village', $request->village)))
            ->when($request->filled('product'), fn ($query) => $query->whereHas('items.product', fn ($productQuery) => $productQuery->where('product_name', 'like', '%'.$request->product.'%')))
            ->when($request->filled('order_type'), fn ($query) => $query->where('order_type', $request->order_type))
            ->when($request->filled('order_no'), fn ($query) => $query->where('order_no', 'like', '%'.$request->order_no.'%'))
            ->when($request->filled('depo_id'), fn ($query) => $query->where('depo_id', $request->depo_id))
            ->latest()->get();

        $availableStates = $orders->map(fn ($order) => $order->user->state ?? null)
            ->filter()->unique('id')->sortBy('name')->values();
        $availableDistricts = $orders->map(fn ($order) => $order->farmer->district ?? null)
            ->filter()->unique('id')->sortBy('name')->values();
        $availableTalukas = $orders->map(fn ($order) => $order->farmer->talukaDetail ?? null)
            ->filter()->unique('id')->sortBy('name')->values();
        $availableVillages = $orders->map(fn ($order) => trim((string) ($order->farmer->village ?? '')))
            ->filter()->unique()->sort()->values();

        return view('admin.farmer-order.index', [
            'orders' => $orders,
            'states' => $availableStates,
            'users' => User::where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'farmers' => Farmer::orderBy('farmer_name')->get(['id', 'farmer_name', 'village']),
            'districts' => $availableDistricts,
            'talukas' => $availableTalukas,
            'villages' => $availableVillages,
            'depos' => Depo::where('status', 1)->orderBy('depo_name')->get(['id', 'depo_name']),
        ]);
    }

    public function adminReportPage(Request $request)
    {
        $filters = $this->getRoleBasedStateAndEmployeeFilters();
        return view('admin.farmer-order.report', [
            'orders' => $this->farmerReportQuery($request, $filters)->get(),
            'states' => $filters['states'],
            'users' => $filters['employees'],
            'farmers' => Farmer::orderBy('farmer_name')->get(['id', 'farmer_name']),
        ]);
    }

    public function exportAdminReportCsv(Request $request)
    {
        $filters = $this->getRoleBasedStateAndEmployeeFilters();
        $orders = $this->farmerReportQuery($request, $filters)->get();
        $filename = 'farmer-order-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($orders) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            $headers = [
                'Order Number', 'Created At', 'Updated At', 'Order Type', 'Order Status',
                'Employee Name', 'Employee Mobile', 'Employee State',
                'Farmer Name', 'Farmer Mobile', 'Farmer Alternate Mobile', 'Farmer State',
                'Farmer District', 'Farmer Taluka', 'Farmer Village',
                'Farmer Land Acres', 'Farmer Land Acres Size',
                'Farmer Irrigation Type', 'Farmer Latitude', 'Farmer Longitude',
                'Depo Name', 'Delivery Place', 'Preferred Transport', 'Employee Remark',
                'Back Office Remark', 'LR Number', 'Transport Name', 'Destination', 'Dispatch Date', 'Dispatch Image',
                'Product Name', 'Product GST Rate', 'Packing',
                'Quantity', 'Shipper Size', 'Unit Price', 'Amount', 'Discount', 'GST Amount', 'Grand Total',
                'Order Amount Total', 'Order Discount Total', 'Order GST Total', 'Order Grand Total',
            ];
            fputcsv($output, $headers);

            foreach ($orders as $order) {
                $items = $order->items->isNotEmpty() ? $order->items : collect([null]);

                foreach ($items as $item) {
                    $row = [
                        $order->order_no, $order->created_at?->toDateTimeString(), $order->updated_at?->toDateTimeString(),
                        $order->order_type, $order->status, $order->user?->name, $order->user?->mobile,
                        $order->user?->state?->name, $order->farmer?->farmer_name, $order->farmer?->mobile_no,
                        $order->farmer?->mobile_no_2, $order->farmer?->state?->name,
                        $order->farmer?->district?->name, $order->farmer?->talukaDetail?->name, $order->farmer?->village,
                        $order->farmer?->land_acr,
                        $order->farmer?->land_acr_size, $order->farmer?->irrigation_type, $order->farmer?->latitude,
                        $order->farmer?->longitude, $order->depo?->depo_name,
                        $order->delivery_place, $order->preferred_transport, $order->remark, $order->remark2,
                        $order->lr_number, $order->transport_name, $order->destination,
                        $order->dispatch_date?->toDateTimeString(), $order->dispatch_image,
                        $item?->product?->product_name, $item?->product?->gst,
                        $item ? trim(($item->packing?->packing_value ?? '').' '.($item->packing?->packing_size ?? '')) : null,
                        $item?->qty, $item?->shipper_size, $item?->price, $item?->total_price,
                        $item?->discount, $item?->gst, $item?->grand_total,
                        $order->items->sum('total_price'), $order->items->sum('discount'),
                        $order->items->sum('gst'), $order->items->sum('grand_total'),
                    ];

                    fputcsv($output, array_map(function ($value) {
                        if (is_string($value) && preg_match('/^[=+@\\-\\t\\r]/', $value)) {
                            return "'".$value;
                        }

                        return $value;
                    }, $row));
                }
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function farmerReportQuery(Request $request, array $filters)
    {
        $query = FarmerOrder::with([
            'user.state', 'farmer.state', 'farmer.district', 'farmer.talukaDetail', 'depo', 'items.product', 'items.packing',
        ])->where('status', 'approved')->latest();

        if (! in_array($filters['roleName'], ['master_admin', 'sub_admin'])) {
            $employeeIds = $filters['employees']->pluck('id')->push($filters['user']->id)->unique();
            $query->whereIn('user_id', $employeeIds);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('state_id')) {
            $query->whereHas('user', fn ($q) => $q->where('state_id', $request->state_id));
        }

        foreach (['user_id', 'farmer_id', 'order_type'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('order_no')) {
            $query->where('order_no', 'like', '%'.$request->order_no.'%');
        }

        return $query;
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
                'qty' => $item->qty, 'price' => $item->price,
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
