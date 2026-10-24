<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\OrderImageController as AttachmentController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class OrderImageController extends AttachmentController
{
    public function index(Request $request, int $order)
    {
        return $this->apiResponse(fn () => parent::index($request, $order), 'Order images fetched successfully');
    }

    public function store(Request $request, int $order)
    {
        return $this->apiResponse(fn () => parent::store($request, $order), 'Order images uploaded successfully');
    }

    public function show(Request $request, int $order, int $image)
    {
        if ($request->attributes->get('order_image_browser_session')) {
            return $this->apiResponse(fn () => parent::adminShow($request, $order, $image));
        }

        return $this->apiResponse(fn () => parent::show($request, $order, $image));
    }

    public function destroy(Request $request, int $order, int $image)
    {
        return $this->apiResponse(fn () => parent::destroy($request, $order, $image), 'Order image deleted successfully');
    }

    private function apiResponse(callable $action, ?string $message = null)
    {
        try {
            $response = $action();
            if (! $response instanceof JsonResponse) {
                // Successful image requests return binary content, not JSON.
                return $response;
            }

            $data = $response->getData(true);
            unset($data['status'], $data['message']);

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $data,
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => false,
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'status' => false,
                'message' => 'Order or image not found',
            ], 404);
        } catch (HttpExceptionInterface $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage() ?: 'Unable to access this order image',
            ], $exception->getStatusCode(), $exception->getHeaders());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to process order images. Please try again.',
            ], 500);
        }
    }
}
