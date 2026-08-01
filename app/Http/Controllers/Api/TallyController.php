<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tally\OpeningClosingRequest;
use App\Http\Requests\Tally\PartySyncRequest;
use App\Http\Requests\Tally\SalesBillRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class TallyController extends Controller
{
    public function partySync(PartySyncRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            foreach ($validated['Data'] as $item) {
                $item['raw_payload'] = $item;
                \App\Models\TallyPartySync::create($item);
            }

            return $this->successResponse();
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'party-sync');
        }
    }

    public function salesBill(SalesBillRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            foreach ($validated['Data'] as $item) {
                $item['raw_payload'] = $item;
                \App\Models\TallySalesBill::create($item);
            }

            return $this->successResponse();
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'sales-bill');
        }
    }

    public function openingClosing(OpeningClosingRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            foreach ($validated['Data'] as $item) {
                $item['raw_payload'] = $item;
                \App\Models\TallyOpeningClosing::create($item);
            }

            return $this->successResponse();
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'opening-closing');
        }
    }

    private function successResponse(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'success' => true,
            'count' => 0,
            'message' => 'Data received successfully',
            'data' => [],
        ]);
    }

    private function errorResponse(Throwable $exception, string $endpoint): JsonResponse
    {
        Log::error('Tally integration failed.', [
            'endpoint' => $endpoint,
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
        ], 500);
    }
}
