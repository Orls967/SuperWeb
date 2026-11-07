<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;

/**
 * Layanan Domain: Spa, Wellness & Penjadwalan Perawatan Kesehatan Tamu.
 */
final class HotelSpaAndWellnessService
{
    /**
     * @var array<string, array{name: string, duration_minutes: int, price_idr: int, category: string}>
     */
    private const SPA_TREATMENTS = [
        'TR-BALI-90' => [
            'name' => 'Authentic Balinese Deep Tissue Massage',
            'duration_minutes' => 90,
            'price_idr' => 650000,
            'category' => 'MASSAGE',
        ],
        'TR-AROMA-60' => [
            'name' => 'Aromatherapy Essential Oil Relaxation',
            'duration_minutes' => 60,
            'price_idr' => 450000,
            'category' => 'MASSAGE',
        ],
        'TR-FACIAL-45' => [
            'name' => 'Organic Botanical Facial Glow',
            'duration_minutes' => 45,
            'price_idr' => 400000,
            'category' => 'BEAUTY',
        ],
        'TR-SCRUB-60' => [
            'name' => 'Herbal Lulur Javanese Body Scrub',
            'duration_minutes' => 60,
            'price_idr' => 500000,
            'category' => 'BODY_CARE',
        ],
    ];

    /**
     * @return array{treatment_code: string, treatment_name: string, therapist_id: string, price_idr: int, folio_line_id: string}
     */
    public function bookSpaTreatment(
        string $folioId,
        string $treatmentCode,
        string $therapistId,
        string $appointmentTime
    ): array {
        if (! isset(self::SPA_TREATMENTS[$treatmentCode])) {
            throw new InvalidArgumentException("Kode perawatan spa '{$treatmentCode}' tidak valid.");
        }

        $treatment = self::SPA_TREATMENTS[$treatmentCode];
        $folio = HotelFolio::findOrFail($folioId);

        // Buat baris transaksi penagihan ke kamar
        $line = HotelFolioLine::create([
            'folio_id' => $folio->id,
            'business_date' => date('Y-m-d'),
            'line_type' => 'SPA',
            'description' => "Spa Treatment: {$treatment['name']} (Terapis: {$therapistId})",
            'amount_idr' => $treatment['price_idr'],
            'base_amount_idr' => $treatment['price_idr'],
            'service_charge_idr' => 0,
            'tax_pbjt_idr' => 0,
        ]);

        $folio->increment('total_addon_charges', $treatment['price_idr']);
        $folio->increment('outstanding_balance', $treatment['price_idr']);

        return [
            'treatment_code' => $treatmentCode,
            'treatment_name' => $treatment['name'],
            'therapist_id' => $therapistId,
            'price_idr' => $treatment['price_idr'],
            'folio_line_id' => (string) $line->id,
        ];
    }
}
