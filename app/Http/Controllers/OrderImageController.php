<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderImage;
use App\Services\OrderImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class OrderImageController extends Controller
{
    public function __construct(private OrderImageService $images) {}

    // Resolve order IDs inside the action, after TenantAuthenticate has switched
    // the API database. Implicit route binding would run before that middleware.
    public function index(Request $request, int $order)
    {
        return $this->listImages($request, $this->findOrder($order), false);
    }

    public function adminIndex(Request $request, int $order)
    {
        return $this->listImages($request, $this->findOrder($order), true);
    }

    private function listImages(Request $request, Order $order, bool $admin)
    {
        $this->images->authorize($request->user(), $order, $admin);

        return response()->json([
            'status' => true,
            'order_id' => $order->id,
            'images' => $this->imageData($order, $order->images()->orderBy('id')->get(), $admin),
            'max_images' => config('order_images.max_images'),
            'max_file_size_kb' => config('order_images.max_file_size_kb'),
        ]);
    }

    public function store(Request $request, int $order)
    {
        return $this->upload($request, $this->findOrder($order), false);
    }

    public function adminStore(Request $request, int $order)
    {
        return $this->upload($request, $this->findOrder($order), true);
    }

    private function upload(Request $request, Order $order, bool $admin)
    {
        $this->images->authorize($request->user(), $order, $admin);
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.config('order_images.max_images')],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('order_images.max_file_size_kb')],
        ]);
        $images = $this->images->append($order, $request->user(), $request->file('images'));

        return response()->json([
            'status' => true,
            'message' => 'Images uploaded successfully.',
            'order_id' => $order->id,
            'images' => $this->imageData($order, $images, $admin),
        ], 201);
    }

    public function show(Request $request, int $order, int $image)
    {
        return $this->serve($request, $this->findOrder($order), $image, false);
    }

    public function adminShow(Request $request, int $order, int $image)
    {
        return $this->serve($request, $this->findOrder($order), $image, true);
    }

    public function destroy(Request $request, int $order, int $image)
    {
        $record = $this->findOrder($order);
        $this->images->authorize($request->user(), $record);
        $this->images->remove($record, $image);

        return response()->json([
            'status' => true,
            'order_id' => $record->id,
            'image_id' => $image,
        ]);
    }

    private function findOrder(int $id): Order
    {
        $model = new Order;
        $connection = $model->getConnection();
        if ($tenant = tenancy()->tenant) {
            $database = $tenant->getDatabaseName();
            abort_if(! $database || $database === 'default_tenant_db'
                || $connection->getDatabaseName() !== $database, 503, 'Tenant database is not configured for order images.');
        }
        abort_unless(OrderImage::isAvailable(), 503, 'Order images are not available until the attachment migration is installed.');

        return $model->newQuery()->findOrFail($id);
    }

    private function serve(Request $request, Order $order, int $imageId, bool $admin)
    {
        $this->images->authorize($request->user(), $order, $admin);
        $image = $order->images()->findOrFail($imageId);
        $disk = Storage::disk($image->disk);
        abort_unless($disk->exists($image->file_path), 404);

        return $disk->response($image->file_path, null, [
            'Content-Type' => $image->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private function imageData(Order $order, Collection $images, bool $admin): Collection
    {
        return $images->map(fn ($image) => [
            'id' => $image->id,
            'name' => $image->original_name,
            'mime_type' => $image->mime_type,
            'size' => $image->file_size,
            'uploaded_at' => $image->created_at?->toIso8601String(),
            'url' => route($admin ? 'order.images.show' : 'api.order.images.show', ['order' => $order->id, 'image' => $image->id]),
        ]);
    }
}
