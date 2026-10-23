<?php

declare(strict_types=1);

namespace Modules\Supplier\Application\Actions;

use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Supplier\Domain\Enums\SupplierStatus;
use Modules\Supplier\Domain\Models\Supplier;
use Modules\Supplier\Domain\Models\SupplierScorecard;

final class QualifySupplierAvlScorecardAction extends BaseAction
{
    /**
     * Evaluates supplier audit scores, updates AVL status to approved/preferred/probation, and records scorecard.
     *
     * @param  array{
     *     period: string,
     *     otd_score: int,
     *     quality_score: int,
     *     price_score: int,
     *     responsiveness_score: int,
     *     notes?: string
     * } $scores
     */
    public function execute(Supplier $supplier, array $scores): SupplierScorecard
    {
        $otd = $scores['otd_score'] ?? 0;
        $quality = $scores['quality_score'] ?? 0;
        $price = $scores['price_score'] ?? 0;
        $resp = $scores['responsiveness_score'] ?? 0;

        if ($otd < 0 || $otd > 100 || $quality < 0 || $quality > 100 || $price < 0 || $price > 100) {
            throw new InvalidArgumentException('Nilai skor scorecard harus berada dalam rentang 0 s/d 100.');
        }

        return $this->transaction(function () use ($supplier, $scores, $otd, $quality, $price, $resp) {
            // Weighted average: OTD (35%), Quality (35%), Price (20%), Responsiveness (10%)
            $overall = (int) round(($otd * 0.35) + ($quality * 0.35) + ($price * 0.20) + ($resp * 0.10));

            $newStatus = match (true) {
                $overall >= 85 => SupplierStatus::Preferred,
                $overall >= 70 => SupplierStatus::Approved,
                $overall >= 50 => SupplierStatus::Probation,
                default => SupplierStatus::Disqualified,
            };

            $actionText = match ($newStatus) {
                SupplierStatus::Preferred => 'Promote to Preferred AVL Supplier',
                SupplierStatus::Approved => 'Approve for AVL PO Issuance',
                SupplierStatus::Probation => 'Place under Quality Probation',
                SupplierStatus::Disqualified => 'Disqualify and Block Purchasing',
            };

            $scorecard = SupplierScorecard::updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'period' => $scores['period'],
                ],
                [
                    'otd_score' => $otd,
                    'quality_score' => $quality,
                    'price_score' => $price,
                    'responsiveness_score' => $resp,
                    'overall_score' => $overall,
                    'action' => $actionText,
                    'notes' => $scores['notes'] ?? 'Scorecard audit calculated via automated AVL engine.',
                ]
            );

            $supplier->update([
                'status' => $newStatus,
                'rating' => (int) round($overall / 20), // 1-5 stars
            ]);

            $this->audit('supplier.avl_scorecard_qualified', $supplier, null, [
                'scorecard_id' => $scorecard->id,
                'overall_score' => $overall,
                'new_status' => $newStatus->value,
            ]);

            return $scorecard;
        });
    }
}
