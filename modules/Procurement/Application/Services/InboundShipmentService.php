<?php

declare(strict_types=1);

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Logistics\Contracts\ShipmentBooking;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Procurement\Domain\Models\PurchaseOrder;
use Modules\Supplier\Domain\Models\Supplier;

/**
 * 33.7 Integrasi Logistics: PO inbound membuat shipment masuk + jadwal
 * kedatangan (expected_date PO) + appointment dock (via interface contract,
 * tanpa mengimpor Domain Mall/Logistics).
 *
 * Idempotensi: shipment untuk PO dibuat per `source_type='procurement_po'`
 * + `source_id=PO.id` — replay menghasilkan pengiriman yang sama, bukan ganda.
 */
class InboundShipmentService
{
    public function __construct(
        private readonly ShipmentBooking $booking,
    ) {}

    /**
     * Buat shipment masuk dari PO pemasok (33.7).
     *
     * @param  array{origin_code: string, street: string, city: string, consignee_name?: string, consignee_phone?: string}  $route
     * @return array{tracking_number: string, shipment_id: int}
     */
    public function bookInbound(PurchaseOrder $po, User $shipper, array $route): array
    {
        if (in_array($po->status, ['cancelled', 'closed'], true)) {
            throw new InvalidArgumentException('PO tertutup/batal tidak dapat dijadwalkan pengiriman masuk.');
        }

        // Booking contract writes two ledger entries; amount=0 would produce an
        // invalid one-sided journal. Until inbound freight is actually charged,
        // call this only when PO has a non-zero inbound freight estimate. (That
        // amount is landed-cost simulation and is handled at GRN/34.7.)
        if ((int) $po->total_amount <= 0) {
            throw new InvalidArgumentException('PO bernilai nol tidak dapat menjadwalkan shipment.');
        }

        return DB::transaction(function () use ($po, $shipper, $route) {
            $po->refresh();

            $existing = Shipment::query()
                ->where('source_type', 'procurement_po')
                ->where('source_id', $po->id)
                ->first();
            if ($existing !== null) {
                return ['tracking_number' => $existing->tracking_number, 'shipment_id' => (int) $existing->id];
            }

            // Pakai pengirim teknis: pemilik akun pemasok, atau pemohon bila belum ditetapkan.
            $ownerId = Supplier::find($po->supplier_id)?->owner_user_id;
            $sender = $ownerId !== null ? User::find($ownerId) : $shipper;
            if ($sender === null) {
                throw new InvalidArgumentException('Tidak ada pengguna yang dapat dijadikan shipper untuk PO ini.');
            }

            $packages = $po->lines()->get()->map(fn ($line) => [
                'weight_g' => max(100, (int) $line->qty * 1000),
                'length_mm' => 300,
                'width_mm' => 200,
                'height_mm' => 200,
                'description' => $line->description,
            ])->values()->all();

            if ($packages === []) {
                throw new InvalidArgumentException('PO tanpa baris tidak dapat dikirim.');
            }

            return $this->booking->bookForOrder($sender, [
                'origin_code' => $route['origin_code'],
                'destination_address' => [
                    'street' => $route['street'],
                    'city' => $route['city'],
                ],
                'consignee_name' => $route['consignee_name'] ?? 'Penerima PO',
                'consignee_phone' => $route['consignee_phone'] ?? '-',
                'packages' => $packages,
                'declared_value_idr' => (int) $po->total_amount,
                'source_type' => 'procurement_po',
                'source_id' => (string) $po->id,
                'amount_idr' => 1, // nilai ongkir simulasi (IDR integer); landed cost final di GRN 34.7
            ]);
        });
    }

    /** Jadwal kedatangan = expected_date PO (jadwal operasional, tanpa duplikasi data). */
    public function arrivalDate(PurchaseOrder $po): ?string
    {
        return $po->expected_date?->toDateString();
    }

    /** Batalkan shipment inbound ketika PO dibatalkan (source-based, idempoten). */
    public function cancelInbound(int $poId, ?string $reason = null): bool
    {
        return $this->booking->cancelForOrder('procurement_po', $poId, $reason ?? 'PO dibatalkan');
    }
}
