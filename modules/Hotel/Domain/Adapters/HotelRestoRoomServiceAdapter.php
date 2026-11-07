<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Adapters;

use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Resto\Contracts\OutletSaleRecorder;

/**
 * Adaptor Integrasi: Resto Room Service ke Folio Hotel (G7-K17).
 */
final class HotelRestoRoomServiceAdapter
{
    public function __construct(
        private readonly ?OutletSaleRecorder $restoRecorder = null,
    ) {}

    /**
     * @param  array<int, array{menu_item: string, qty: int, price_idr: int}>  $orderItems
     * @return array{order_ref: string, total_amount_idr: int, folio_line_id: string}
     */
    public function recordRoomServiceOrder(string $folioId, string $roomNumber, array $orderItems): array
    {
        $folio = HotelFolio::findOrFail($folioId);

        $total = 0;
        $descParts = [];
        foreach ($orderItems as $item) {
            $subtotal = $item['qty'] * $item['price_idr'];
            $total += $subtotal;
            $descParts[] = "{$item['qty']}x {$item['menu_item']}";
        }

        $line = HotelFolioLine::create([
            'folio_id' => $folio->id,
            'business_date' => date('Y-m-d'),
            'line_type' => 'ROOM_SERVICE',
            'description' => "Room Service Room {$roomNumber}: ".implode(', ', $descParts),
            'amount_idr' => $total,
            'base_amount_idr' => $total,
            'service_charge_idr' => 0,
            'tax_pbjt_idr' => 0,
        ]);

        $folio->increment('total_addon_charges', $total);
        $folio->increment('outstanding_balance', $total);

        return [
            'order_ref' => 'RS-'.date('Ymd').'-'.substr(md5((string) $line->id), 0, 8),
            'total_amount_idr' => $total,
            'folio_line_id' => (string) $line->id,
        ];
    }
}
