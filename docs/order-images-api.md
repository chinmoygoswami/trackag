# Images for existing party orders

Images are optional attachments to an existing order. Uploading appends files; it does not update the order, its items, timestamps, status, GST, dispatches, or notifications. Existing order creation and update payloads are unchanged.

## Mobile API

List, upload, and delete requests require:

```http
Authorization: Bearer <token>
X-Company-Code: <company-code>
Accept: application/json
```

The token must belong to the owner of the order in that company. Uploading files does not require the order to be pending.

### Upload

`POST /api/orders-images-store/{order_id}`

Send `multipart/form-data` with one or more `images[]` file fields. Do not manually set the multipart Content-Type boundary.

```bash
curl --request POST 'https://agrina.trackag.in/api/orders-images-store/123' \
  --header 'Authorization: Bearer <token>' \
  --header 'X-Company-Code: <company-code>' \
  --header 'Accept: application/json' \
  --form 'images[]=@/path/to/first.jpg' \
  --form 'images[]=@/path/to/second.png'
```

Response: HTTP 200, following the existing mobile order API success format.

```json
{
  "status": true,
  "message": "Order images uploaded successfully",
  "data": {
    "order_id": 123,
    "images": [
    {
      "id": 1,
      "name": "first.jpg",
      "mime_type": "image/jpeg",
      "size": 12456,
      "uploaded_at": "2026-10-23T12:00:00+00:00",
      "url": "https://agrina.trackag.in/api/orders/123/images/1"
    }
    ]
  }
}
```

The response contains newly uploaded images. Fetch the list to get all attachments. JPEG, PNG, and WebP are supported. Defaults: 10 images total per order and 5 MB per image. Both limits and the private storage disk are configured in `config/order_images.php`. Configure PHP/web-server request size limits to accommodate a batch (for example, `upload_max_filesize=5M`, `post_max_size=60M`, and the corresponding proxy body-size limit).

### List

`GET /api/orders-images-list/{order_id}`

Returns HTTP 200 with `status: true`, `message: "Order images fetched successfully"`, and `data` containing `order_id`, an `images` array (same image fields as upload), `max_images`, and `max_file_size_kb`. Orders without attachments return an empty `data.images` array.

### View image content

`GET /api/orders-images-view/{order_id}/{image_id}`

Returns the original image inline without authentication, a browser session, bearer token, or company header. Anyone with an image link can view it. Only these two image-content GET routes are public; listing, uploading, and deleting images still require the existing owner authentication.

The URL's domain selects the tenant. Headers and cookies cannot select another tenant for public image viewing. Unknown domains are rejected, and the image must belong to the specified order within that domain's database. Files remain on their existing storage disk; the new behavior does not expose storage directories or dispatch images.

Uploads and lists made through the central API host return public image links on the authenticated tenant's registered domain. When using one of that tenant's domains directly, links retain the current domain. Tenants must have a registered domain; a missing domain returns 503 before an API upload writes files.

The previously documented URLs (`GET/POST /api/orders/{order_id}/images` and `GET /api/orders/{order_id}/images/{image_id}`) remain supported. Both URL styles use the same mobile response format. The returned image URL can use the original content route.

### Delete one image

`DELETE /api/orders-images-delete/{order_id}/{image_id}`

REST alias: `DELETE /api/orders/{order_id}/images/{image_id}`.

Use the same bearer and company headers. No request body is needed. The order owner can permanently remove one attachment from their own order. The attachment must belong to the specified order in the authenticated tenant. Other attachments, order values, status, timestamps, items, and dispatch images are unchanged.

```bash
curl --request DELETE 'https://agrina.trackag.in/api/orders-images-delete/123/1' \
  --header 'Authorization: Bearer <token>' \
  --header 'X-Company-Code: <company-code>' \
  --header 'Accept: application/json'
```

Success response: HTTP 200.

```json
{
  "status": true,
  "message": "Order image deleted successfully",
  "data": {
    "order_id": 123,
    "image_id": 1
  }
}
```

The image file and attachment record are removed. A missing file does not prevent removing its stale attachment record. A missing/already deleted image, or an image from another order, returns 404. Filesystem errors roll back the metadata deletion. Deleting an image frees a slot within the per-order upload limit. The admin gallery shows the updated count when the list is refreshed.

### Errors

- Existing tenant authentication handles missing/invalid company and bearer credentials.
- `403`: user does not own the order.
- `404`: order/image does not exist in that tenant, image belongs to another order, or stored file is missing.
- `422`: files missing, unsupported format, too large, or total image limit exceeded.
- `503`: the tenant database is not configured correctly, or the attachment migration has not been installed.
- Failed batches roll back attachment records and remove files written by that attempt. Previously uploaded images remain.

Attachment errors use `status: false` and `message`; validation errors also include `errors`. Existing tenant-authentication error responses are unchanged. The admin gallery keeps its original response format.

## Admin screen

The order index has an **Images** column immediately after **Status**. **View (count)** opens the gallery and multi-file upload form. Viewing full-size images uses the existing admin session. Admin endpoints are:

- `GET /admin/order/{order_id}/images`
- `POST /admin/order/{order_id}/images` (multipart `images[]`, session authentication and CSRF token)
- `GET /admin/order/{order_id}/images/{image_id}`

The order owner, their direct reporting manager, and `master_admin`/`sub_admin` roles can use these admin endpoints. Existing order-list visibility rules are unchanged.

## Installation

Only a new `order_images` table is created. Existing tables are not altered.

Before the attachment migration is installed, the existing order index continues to work with an unavailable Images column; only the new attachment endpoints return 503.

Apply the specific migration to the central database and tenants:

```bash
php artisan migrate --path=database/migrations/2026_10_23_000001_create_order_images_table.php
php artisan tenants:migrate --path=database/migrations/tenant/2026_10_23_000001_create_order_images_table.php
```

The disk must be writable. Images use private storage and tenant-specific directories; no new public storage symlink is required. No additional migration is needed for the image deletion API. The foreign key removes attachment rows when an order is deleted; storage lifecycle cleanup of files after existing order deletion is separate from this feature.

