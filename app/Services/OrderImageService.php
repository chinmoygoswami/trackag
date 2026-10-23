<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderImage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class OrderImageService
{
    public function authorize(User $user, Order $order, bool $admin = false): void
    {
        if ((string) $order->user_id === (string) $user->id) {
            return;
        }

        if ($admin && ($user->hasAnyRole(['master_admin', 'sub_admin'])
            || User::whereKey($order->user_id)->where('reporting_to', $user->id)->exists())) {
            return;
        }

        abort(403, 'You do not have access to this order.');
    }

    public function append(Order $order, User $user, array $files): Collection
    {
        // Resolve once: the existing tenant trait reconnects on each lookup.
        $connection = $order->getConnection();
        $connectionName = $connection->getName();
        $disk = config('order_images.disk');
        $tenantKey = hash('sha256', $connection->getDatabaseName());
        $directory = 'order-images/'.$tenantKey.'/'.$order->id;
        $storedPaths = [];

        try {
            return $connection->transaction(function () use ($connection, $connectionName, $order, $user, $files, $disk, $directory, &$storedPaths) {
                // Serialize uploads to the same order without modifying it.
                abort_unless($connection->table('orders')->where('id', $order->id)->lockForUpdate()->first(), 404);
                $count = $connection->table('order_images')->where('order_id', $order->id)->count();
                $limit = config('order_images.max_images');
                if ($count + count($files) > $limit) {
                    throw ValidationException::withMessages(['images' => "An order can have at most {$limit} images."]);
                }

                $images = collect();
                foreach ($files as $file) {
                    $path = $file->store($directory, $disk);
                    if (! $path) {
                        throw new RuntimeException('Unable to store the order image.');
                    }
                    $storedPaths[] = $path;
                    $image = new OrderImage([
                        'order_id' => $order->id,
                        'disk' => $disk,
                        'file_path' => $path,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $user->id,
                    ]);
                    $image->setConnection($connectionName);
                    $image->saveOrFail();
                    $images->push($image);
                }

                return $images;
            });
        } catch (Throwable $exception) {
            // The transaction rolls back metadata; remove files from this attempt.
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }
            throw $exception;
        }
    }

    public function remove(Order $order, int $imageId): void
    {
        $connection = $order->getConnection();

        $connection->transaction(function () use ($connection, $order, $imageId) {
            // Use the same order lock as uploads so attachment counts stay consistent.
            abort_unless($connection->table('orders')->where('id', $order->id)->lockForUpdate()->first(), 404);
            $model = (new OrderImage)->setConnection($connection->getName());
            $image = $model->newQuery()->where('order_id', $order->id)->lockForUpdate()->findOrFail($imageId);
            $disk = Storage::disk($image->disk);

            // Delete metadata first: a failed database delete must not remove the file.
            // A failed file deletion throws and rolls back the metadata deletion.
            if (! $image->deleteOrFail()) {
                throw new RuntimeException('Unable to delete the order image record.');
            }
            if ($disk->exists($image->file_path) && ! $disk->delete($image->file_path)) {
                throw new RuntimeException('Unable to delete the order image.');
            }
        });
    }
}
