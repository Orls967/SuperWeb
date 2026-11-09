<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;
use Modules\Hospital\Application\DTOs\FolioItemDTO;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\FolioItem;

/**
 * Layanan Domain Kalkulasi Billing & Folio Rumah Sakit.
 * Mengelola agregasi item folio perawatan, validasi batas kewajaran harga,
 * sinkronisasi total tagihan bruto, dan konsistensi matematis item episode.
 */
class HospitalBillingCalculationService
{
    /**
     * Menambahkan item pelayanan ke folio billing episode.
     */
    public function addItemToEpisode(BillingEpisode $episode, FolioItemDTO $dto): FolioItem
    {
        if ($episode->status === 'CLOSED' || $episode->status === 'SETTLED') {
            throw new DomainException("Tidak dapat menambahkan item ke billing episode yang sudah [{$episode->status}].");
        }

        if ($dto->quantity <= 0) {
            throw new DomainException('Jumlah kuantitas item folio harus lebih dari nol.');
        }

        if ($dto->unitPriceIdr < 0) {
            throw new DomainException('Harga satuan item folio tidak boleh negatif.');
        }

        $expectedSubtotal = $dto->quantity * $dto->unitPriceIdr;

        $item = FolioItem::create([
            'billing_episode_id' => $episode->id,
            'item_category' => $dto->category,
            'description' => $dto->description,
            'quantity' => $dto->quantity,
            'unit_price_idr' => $dto->unitPriceIdr,
            'subtotal_idr' => $expectedSubtotal,
        ]);

        $this->recalculateEpisodeTotal($episode);

        return $item;
    }

    /**
     * Menghitung ulang total tagihan bruto billing episode dari seluruh baris folio.
     */
    public function recalculateEpisodeTotal(BillingEpisode $episode): int
    {
        $sum = (int) FolioItem::where('billing_episode_id', $episode->id)->sum('subtotal_idr');

        $episode->total_charges_idr = $sum;
        $episode->save();

        return $sum;
    }

    /**
     * Memvalidasi invarian saldo: Apakah total episode sama persis dengan penjumlahan folio.
     */
    public function verifyFolioIntegrity(BillingEpisode $episode): bool
    {
        $sum = (int) FolioItem::where('billing_episode_id', $episode->id)->sum('subtotal_idr');

        return $sum === $episode->total_charges_idr;
    }
}
