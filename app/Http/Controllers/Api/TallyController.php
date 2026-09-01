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
            $data = collect($validated['Data']);
            
            // Get all unique, non-empty master_ids from the request
            $masterIds = $data->pluck('master_id')->filter(function ($val) {
                return $val !== null && $val !== '';
            })->unique();
            
            // Fetch all existing master_ids in a single query
            $existingMasterIds = \App\Models\TallyPartySync::whereIn('master_id', $masterIds)
                ->pluck('master_id')
                ->toArray();
            
            foreach ($data as $item) {
                // Skip if this master_id already exists in the database
                if (isset($item['master_id']) && $item['master_id'] !== '' && in_array($item['master_id'], $existingMasterIds)) {
                    continue;
                }
                
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
                $item['gst_amount'] = $item['gst_amount'] ?? 0;
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
            
            $data = collect($validated['Data']);
            
            foreach ($data as $item) {
                $item['raw_payload'] = $item;
                
                if (isset($item['master_id']) && $item['master_id'] !== '') {
                    \App\Models\TallyOpeningClosing::updateOrCreate(
                        ['master_id' => $item['master_id']],
                        $item
                    );
                } else {
                    \App\Models\TallyOpeningClosing::create($item);
                }
            }

            return $this->successResponse();
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'opening-closing');
        }
    }

    public function partywisePaymentCredit(\App\Http\Requests\Tally\PartywisePaymentCreditRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            foreach ($validated['Data'] as $item) {
                $item['raw_payload'] = $item;
                
                if (isset($item['voucher_no']) && $item['voucher_no'] !== '') {
                    \App\Models\TallyPartywisePaymentCredit::updateOrCreate(
                        ['voucher_no' => $item['voucher_no']],
                        $item
                    );
                } else {
                    \App\Models\TallyPartywisePaymentCredit::create($item);
                }
            }

            return $this->successResponse();
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, 'partywise-payment-credit');
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
