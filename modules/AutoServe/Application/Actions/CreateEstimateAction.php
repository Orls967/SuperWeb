<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use App\Models\User;
use Exception;
use Modules\AutoServe\Domain\Enums\EstimateStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\AutoServe\Domain\Models\Service;
use Modules\AutoServe\Domain\Models\Sparepart;
use Modules\Shared\Application\BaseAction;

/**
 * Mekanik menyusun estimasi biaya perbaikan (jasa + sparepart) untuk sebuah booking.
 */
class CreateEstimateAction extends BaseAction
{
    /**
     * @param  array<int, array{type: string, ref_id: int|string|null, name?: string, qty: int|string, unit_price?: int|string}>  $rawItems
     */
    public function execute(Booking $booking, User $mechanic, array $rawItems): Estimate
    {
        if ($booking->isCancelled() || $booking->isCompleted() || $booking->isInvoiced()) {
            throw new Exception('Estimasi hanya dapat dibuat untuk booking yang masih berjalan.');
        }

        if ($booking->approvedEstimate() !== null) {
            throw new Exception('Booking ini sudah memiliki estimasi yang disetujui customer.');
        }

        $items = $this->normalizeItems($rawItems);

        if ($items === []) {
            throw new Exception('Estimasi harus memuat minimal satu item jasa atau sparepart.');
        }

        return $this->transaction(function () use ($booking, $mechanic, $items) {
            $serviceTotal = $this->sumOf($items, 'service');
            $partsTotal = $this->sumOf($items, 'part');

            // Draft lama ditimpa agar mekanik dapat merevisi estimasi sebelum dikirim
            $estimate = $booking->estimates()
                ->where('status', EstimateStatus::Draft->value)
                ->first() ?? new Estimate(['booking_id' => $booking->id]);

            $estimate->fill([
                'booking_id' => $booking->id,
                'created_by' => $mechanic->id,
                'items' => $items,
                'service_total' => $serviceTotal,
                'parts_total' => $partsTotal,
                'total' => $serviceTotal + $partsTotal,
                'status' => EstimateStatus::Draft,
            ]);

            $estimate->save();

            return $estimate->fresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $rawItems): array
    {
        $items = [];

        foreach ($rawItems as $raw) {
            $type = $raw['type'] ?? null;
            if (! in_array($type, ['service', 'part'], true)) {
                continue;
            }

            $qty = max(1, (int) ($raw['qty'] ?? 1));
            $refId = isset($raw['ref_id']) && $raw['ref_id'] !== '' ? (int) $raw['ref_id'] : null;

            $name = $raw['name'] ?? null;
            $unitPrice = isset($raw['unit_price']) && $raw['unit_price'] !== '' ? (int) $raw['unit_price'] : null;

            if ($refId !== null) {
                $reference = $type === 'service' ? Service::find($refId) : Sparepart::find($refId);

                if ($reference === null) {
                    throw new Exception('Item estimasi merujuk pada data master yang tidak ditemukan.');
                }

                $name = $name ?: $reference->name;
                $unitPrice = $unitPrice ?? (int) $reference->price;
            }

            if ($name === null || $unitPrice === null) {
                throw new Exception('Setiap item estimasi wajib memiliki nama dan harga satuan.');
            }

            if ($unitPrice < 0) {
                throw new Exception('Harga satuan item estimasi tidak boleh negatif.');
            }

            $items[] = [
                'type' => $type,
                'ref_id' => $refId,
                'name' => $name,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $unitPrice * $qty,
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function sumOf(array $items, string $type): int
    {
        return array_sum(array_map(
            fn (array $item) => (int) $item['subtotal'],
            array_filter($items, fn (array $item) => $item['type'] === $type)
        ));
    }
}
