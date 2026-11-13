<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\ControlTower\Contracts\PromiseQuoteInterface;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Http\Requests\QuotePromiseRequest;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Domain\Exceptions\ApiIdempotencyConflictException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PromiseController extends Controller
{
    public function quote(
        QuotePromiseRequest $request,
        PromiseQuoteInterface $promiseQuote,
        ClockInterface $clock,
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('manageSop', SopCycle::class);

        $data = $request->validated();
        $itemCode = (string) $data['item_code'];
        $quantity = (int) $data['quantity'];
        $requestedPeriod = (int) $data['requested_period'];
        $orderReference = isset($data['order_reference']) && $data['order_reference'] !== ''
            ? (string) $data['order_reference']
            : null;
        $asOfDate = isset($data['as_of_date']) && $data['as_of_date'] !== ''
            ? (string) $data['as_of_date']
            : null;

        $headerKey = $request->header('Idempotency-Key');
        $idempotencyKey = (string) ($data['idempotency_key'] ?? ($headerKey !== null && $headerKey !== '' ? $headerKey : ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = 'HTTP-'.hash('sha256', implode('|', [
                $itemCode,
                (string) $quantity,
                (string) $requestedPeriod,
                $orderReference ?? '',
                $asOfDate ?? $clock->now()->toIso8601String(),
                (string) ($request->user()?->id ?? 0),
            ]));
        }

        try {
            $result = $promiseQuote->quote(
                itemCode: $itemCode,
                quantity: $quantity,
                requestedPeriod: $requestedPeriod,
                idempotencyKey: $idempotencyKey,
                orderReference: $orderReference,
                asOfDate: $asOfDate,
            );
        } catch (ApiIdempotencyConflictException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            ...$result,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'promise_code',
                'order_reference',
                'item_code',
                'requested_qty',
                'promised_period',
                'late_periods',
                'atp_cumulative',
                'ctp_cumulative',
                'promise_status',
                'reason_code',
                'certificate_hash',
                'as_of_date',
                'promised_delivery_date',
            ]);

            foreach (OrderPromise::query()->orderBy('id')->cursor() as $row) {
                fputcsv($out, [
                    $row->promise_code,
                    $row->order_reference,
                    $row->item_code,
                    $row->requested_qty,
                    $row->promised_period,
                    $row->late_periods,
                    $row->atp_cumulative,
                    $row->ctp_cumulative,
                    $row->promise_status,
                    $row->reason_code,
                    $row->certificate_hash,
                    $row->as_of_date?->toDateString() ?? '',
                    $row->promised_delivery_date?->toDateString() ?? '',
                ]);
            }

            fclose($out);
        }, 'control-tower-promises.csv');
    }
}
