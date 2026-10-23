<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderImage extends Model
{
    public static function isAvailable(): bool
    {
        $model = new static;

        return $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable());
    }

    protected $fillable = [
        'order_id', 'disk', 'file_path', 'original_name', 'mime_type', 'file_size', 'uploaded_by',
    ];

    protected $hidden = ['disk', 'file_path'];

    public function getConnectionName()
    {
        // Respect an explicitly pinned connection during an attachment transaction.
        return $this->connection ?? (new Order)->getConnectionName();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
