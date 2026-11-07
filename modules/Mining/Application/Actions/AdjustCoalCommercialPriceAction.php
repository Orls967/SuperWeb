<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use DomainException;
use InvalidArgumentException;
use Modules\Mining\Domain\Algorithms\CoalPriceAdjuster;
use Modules\Mining\Domain\Algorithms\CoalPriceAdjusterCertificateVerifier;

class AdjustCoalCommercialPriceAction
{
    public function __construct(
        private readonly CoalPriceAdjuster $adjuster = new CoalPriceAdjuster,
        private readonly CoalPriceAdjusterCertificateVerifier $verifier = new CoalPriceAdjusterCertificateVerifier
    ) {}

    /**
     * Penyesuaian harga komersial batubara berdasarkan kualitas faktual pengapalan (D94-4, M6).
     *
     * Menghitung penalti kalori (GAR), penalti abu (ash), dan penalti sulfur (TS)
     * terhadap harga acuan kontrak (base price FOB barge/vessel).
     *
     * @param array{
     *     base_price_cents: int,
     *     actual_gar_kcal: int,
     *     reference_gar_kcal: int,
     *     actual_ash_permil: int,
     *     base_ash_limit_permil: int,
     *     actual_sulfur_permil: int,
     *     base_sulfur_limit_permil: int,
     *     volume_tons: int
     * } $params
     * @return array{
     *     base_price_cents: int,
     *     final_price_cents_per_ton: int,
     *     total_invoice_usd_cents: int,
     *     gar_adjusted_price_cents: int,
     *     ash_penalty_cents: int,
     *     sulfur_penalty_cents: int,
     *     is_rejected: bool,
     *     rejection_reason: ?string,
     *     certificate_hash: string
     * }
     */
    public function execute(array $params): array
    {
        $basePrice = (int) $params['base_price_cents'];
        $actualGar = (int) $params['actual_gar_kcal'];
        $refGar = (int) $params['reference_gar_kcal'];
        $actualAsh = (int) $params['actual_ash_permil'];
        $baseAsh = (int) $params['base_ash_limit_permil'];
        $actualSulfur = (int) $params['actual_sulfur_permil'];
        $baseSulfur = (int) $params['base_sulfur_limit_permil'];
        $volumeTons = (int) $params['volume_tons'];

        if ($basePrice <= 0 || $refGar <= 0 || $volumeTons <= 0) {
            throw new InvalidArgumentException('Harga dasar, kalori acuan, dan volume tonase harus bernilai positif.');
        }

        $result = $this->adjuster->adjust(
            basePriceCents: $basePrice,
            actualGarKcal: $actualGar,
            referenceGarKcal: $refGar,
            actualAshPermil: $actualAsh,
            baseAshLimitPermil: $baseAsh,
            actualSulfurPermil: $actualSulfur,
            baseSulfurLimitPermil: $baseSulfur,
            volumeTons: $volumeTons
        );

        if (! $this->verifier->verify($result)) {
            throw new DomainException('Integritas sertifikat penyesuaian harga batubara M6 tidak valid atau rusak.');
        }

        return [
            'base_price_cents' => $result->basePriceCents,
            'final_price_cents_per_ton' => $result->finalPriceCentsPerTon,
            'total_invoice_usd_cents' => $result->totalInvoiceUsdCents,
            'gar_adjusted_price_cents' => $result->garAdjustedPriceCents,
            'ash_penalty_cents' => $result->ashPenaltyCents,
            'sulfur_penalty_cents' => $result->sulfurPenaltyCents,
            'is_rejected' => $result->isRejected,
            'rejection_reason' => $result->rejectionReason,
            'certificate_hash' => (string) ($result->certificate['canonical_hash'] ?? ''),
        ];
    }
}
