<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Services\HotelMinibarAndLaundryPostingService;

/**
 * Tindakan Audit & Pembebanan Konsumsi Minibar Pasca Pemeriksaan Kamar.
 */
final class ManageRoomMinibarInspectionAction
{
    public function __construct(
        private readonly HotelMinibarAndLaundryPostingService $postingService
    ) {}

    /**
     * @param  list<array{item_name: string, quantity: int, price_idr: int}>  $consumedItems
     * @return array{total_billed_idr: int, items_count: int, folio_line_id: string|null}
     */
    public function execute(string|int $reservationId, array $consumedItems): array
    {
        $folio = HotelFolio::where('reservation_id', (string) $reservationId)->firstOrFail();

        $totalBase = 0;
        $summaryParts = [];

        foreach ($consumedItems as $item) {
            $cost = $item['quantity'] * $item['price_idr'];
            $totalBase += $cost;
            $summaryParts[] = "{$item['quantity']}x {$item['item_name']}";
        }

        if ($totalBase <= 0) {
            return [
                'total_billed_idr' => 0,
                'items_count' => 0,
                'folio_line_id' => null,
            ];
        }

        $summary = 'Konsumsi Minibar: '.implode(', ', $summaryParts);
        $res = $this->postingService->postIncidentalCharge(
            folioId: $folio->id,
            incidentalType: 'MINIBAR',
            baseAmountIdr: $totalBase,
            itemSummary: $summary
        );

        return [
            'total_billed_idr' => $res['total_charged_idr'],
            'items_count' => count($consumedItems),
            'folio_line_id' => $res['line_id'],
        ];
    }
}
