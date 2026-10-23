<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Application\Actions;

use InvalidArgumentException;
use Modules\TradeFinance\Domain\Models\LcDocument;
use Modules\TradeFinance\Domain\Models\LetterOfCredit;

class VerifyLcDocumentPresentationAction
{
    /**
     * @param array<int, array{
     *     doc_name: string,
     *     document_number: string,
     *     has_discrepancy?: bool,
     *     discrepancy_details?: string|null,
     *     is_waived_by_applicant?: bool
     * }> $documents
     * @return array{
     *     letter_of_credit_id: string,
     *     total_documents: int,
     *     discrepancies_count: int,
     *     unwaived_discrepancies: int,
     *     presentation_status: string,
     *     documents: array<int, LcDocument>
     * }
     */
    public function execute(LetterOfCredit $lc, array $documents): array
    {
        if (empty($documents)) {
            throw new InvalidArgumentException('Document presentation must include at least one shipping/commercial document.');
        }

        $createdDocs = [];
        $unwaivedCount = 0;
        $totalDiscrepancies = 0;

        foreach ($documents as $docData) {
            $hasDiscrepancy = (bool) ($docData['has_discrepancy'] ?? false);
            $isWaived = (bool) ($docData['is_waived_by_applicant'] ?? false);

            if ($hasDiscrepancy) {
                $totalDiscrepancies++;
                if (! $isWaived) {
                    $unwaivedCount++;
                }
            }

            $createdDocs[] = LcDocument::create([
                'letter_of_credit_id' => $lc->id,
                'doc_name' => $docData['doc_name'],
                'document_number' => $docData['document_number'],
                'has_discrepancy' => $hasDiscrepancy,
                'discrepancy_details' => $docData['discrepancy_details'] ?? null,
                'is_waived_by_applicant' => $isWaived,
            ]);
        }

        $newStatus = $unwaivedCount > 0 ? 'discrepancies_found' : 'presented';
        $lc->update(['status' => $newStatus]);

        return [
            'letter_of_credit_id' => $lc->id,
            'total_documents' => count($createdDocs),
            'discrepancies_count' => $totalDiscrepancies,
            'unwaived_discrepancies' => $unwaivedCount,
            'presentation_status' => $newStatus,
            'documents' => $createdDocs,
        ];
    }
}
