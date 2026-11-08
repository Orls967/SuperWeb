<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Models\FeedInventoryBatch;

/**
 * Service formulasi pakan berkelanjutan dan kalkulasi Forage Fish Dependency Ratio (FIFO) (Fase 171).
 */
class SustainableFeedFormulationService
{
    // Standar batas FIFO maksimum ASC (Aquaculture Stewardship Council) untuk udang vannamei
    public const MAX_ASC_FIFO_RATIO = 1.20;

    /**
     * Hitung Forage Fish Dependency Ratio (FIFO) untuk mengevaluasi keberlanjutan pakan akuakultur.
     *
     * FIFO_fishmeal = (persentase tepung ikan dalam pakan * eFCR) / 22.5% rendemen ikan liar
     * FIFO_fishoil = (persentase minyak ikan dalam pakan * eFCR) / 5.0% rendemen ikan liar
     *
     * @return array{
     *     feed_lot_number: string,
     *     fishmeal_inclusion_pct: float,
     *     fishoil_inclusion_pct: float,
     *     fifo_fishmeal_ratio: float,
     *     fifo_fishoil_ratio: float,
     *     composite_fifo_score: float,
     *     is_asc_compliant: bool,
     *     sustainability_rating: string
     * }
     */
    public function calculateFifoRatio(
        int $feedInventoryId,
        float $fishmealInclusionPct,
        float $fishoilInclusionPct,
        float $batchEconomicFcr = 1.25
    ): array {
        $feed = FeedInventoryBatch::findOrFail($feedInventoryId);

        if ($fishmealInclusionPct < 0.0 || $fishoilInclusionPct < 0.0 || $batchEconomicFcr <= 0.0) {
            throw new DomainException('Parameter inklusi tepung/minyak ikan dan FCR harus valid.');
        }

        // Rendemen ekstraksi tepung ikan dari tangkapan liar adalah 22.5%
        $fifoFishmeal = round(($fishmealInclusionPct * $batchEconomicFcr) / 22.5, 3);

        // Rendemen minyak ikan dari tangkapan liar adalah 5.0%
        $fifoFishoil = round(($fishoilInclusionPct * $batchEconomicFcr) / 5.0, 3);

        $compositeFifo = max($fifoFishmeal, $fifoFishoil);
        $isCompliant = ($compositeFifo <= self::MAX_ASC_FIFO_RATIO);

        $rating = $compositeFifo <= 0.80
            ? 'EXCELLENT_LOW_MARINE_FOOTPRINT'
            : ($isCompliant ? 'ASC_COMPLIANT_ACCEPTABLE' : 'UNSUSTAINABLE_OVERDEPENDENT_ON_WILD_FISH');

        return [
            'feed_lot_number' => $feed->feed_lot_number,
            'fishmeal_inclusion_pct' => $fishmealInclusionPct,
            'fishoil_inclusion_pct' => $fishoilInclusionPct,
            'fifo_fishmeal_ratio' => $fifoFishmeal,
            'fifo_fishoil_ratio' => $fifoFishoil,
            'composite_fifo_score' => $compositeFifo,
            'is_asc_compliant' => $isCompliant,
            'sustainability_rating' => $rating,
        ];
    }
}
