<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Modules\Shared\Application\BaseAction;

class AuditShelfPlanogramComplianceAction extends BaseAction
{
    /**
     * @param  array{shelf_id: string, expected_facings: int, detected_facings: int, out_of_stock_detected: int}  $params
     * @return array{shelf_id: string, compliance_score_pct: int, is_compliant: bool, status: string}
     */
    public function execute(array $params): array
    {
        $shelfId = (string) $params['shelf_id'];
        $expected = max(1, (int) $params['expected_facings']);
        $detected = (int) $params['detected_facings'];
        $oos = (int) ($params['out_of_stock_detected'] ?? 0);

        $complianceRatio = min(1.0, max(0.0, ($detected - $oos) / $expected));
        $scorePct = (int) round($complianceRatio * 100);

        $isCompliant = $scorePct >= 80;
        $status = $isCompliant ? 'COMPLIANT' : 'NON_COMPLIANT_RESTOCK_NEEDED';

        $this->audit(
            action: 'ret_planogram_audited',
            newValues: ['shelf_id' => $shelfId, 'score' => $scorePct, 'status' => $status],
            impactType: 'store_operations',
        );

        return [
            'shelf_id' => $shelfId,
            'compliance_score_pct' => $scorePct,
            'is_compliant' => $isCompliant,
            'status' => $status,
        ];
    }
}
