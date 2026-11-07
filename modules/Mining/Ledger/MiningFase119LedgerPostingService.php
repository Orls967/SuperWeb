<?php

declare(strict_types=1);

namespace Modules\Mining\Ledger;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;

/**
 * Layanan Posting Buku Besar Double-Entry Fase 119 (Underground, Geotech & Marine Ops).
 * Mengawal 4 kejadian akuntansi operasional peledakan, tongkang, geoteknik, dan fotogrametri:
 * 1. Biaya Bahan Peledak ANFO:
 *    Debit min:explosives_expense:IDR | Kredit min:bank_cash:IDR
 * 2. Akrual Biaya Sewa Tongkang / Marine Freight:
 *    Debit min:marine_freight_expense:IDR | Kredit min:barge_freight_payable:IDR
 * 3. Beban Pemantauan Geoteknik & Survei Drone 3D:
 *    Debit expense:min:geotech_monitoring:IDR | Kredit min:bank_cash:IDR
 * 4. Provisi Mitigasi & Pelandaian Lereng Geoteknik:
 *    Debit min:slope_remediation_expense:IDR | Kredit min:slope_remediation_provision:IDR
 */
final class MiningFase119LedgerPostingService
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * 1. Pencatatan konsumsi bahan peledak ANFO dan detonator pasca-detonasi aman.
     */
    public function postExplosivesConsumption(
        string $referenceNumber,
        string $blastClearanceRunId = '',
        int $amountIdr = 0,
        float $anfoKg = 0.0,
        float $emulsionKg = 0.0,
        int $detonatorsCount = 0,
        string $description = ''
    ): string {
        $amountInt = $amountIdr;
        $this->assertPositiveAmount($amountInt, 'konsumsi bahan peledak');

        $dto = new PostingDTO(
            type: MiningTransactionTypes::MINING_EXPLOSIVES_EXPENSE->value,
            description: $description ?: "Konsumsi bahan peledak ANFO peledakan {$referenceNumber}",
            idempotencyKey: "min:explosives:{$referenceNumber}:".substr($blastClearanceRunId, 0, 8),
            entries: Posting::lines()
                ->debit(MiningAccounts::explosivesExpense(), $amountInt, 'IDR')
                ->credit(MiningAccounts::bankCash(), $amountInt, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_BLAST_CLEARANCE',
            referenceId: $referenceNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-BLS-{$referenceNumber}";
    }

    /**
     * 2. Akrual biaya sewa kapal tongkang (marine freight) atas pengapalan curah.
     */
    public function postBargeFreightSettlement(
        string $referenceNumber,
        string $bargeCode,
        int $amountIdr,
        float $tonnageWmt = 0.0,
        int $demurrageIdr = 0,
        int $despatchIdr = 0,
        string $description = ''
    ): string {
        return $this->postMarineFreightAccrual(
            bargeCode: $bargeCode,
            vesselName: 'Barge '.$bargeCode,
            freightAmountIdr: $amountIdr
        );
    }

    public function postGeotechRemediationCost(
        string $referenceNumber,
        string $incidentId,
        int $amountIdr,
        string $remediationType = '',
        float $targetFos = 1.30,
        string $description = ''
    ): string {
        return $this->postSlopeRemediationProvision(
            incidentCode: $referenceNumber,
            provisionAmountIdr: $amountIdr
        );
    }

    /**
     * 2b. Akrual biaya sewa kapal tongkang (marine freight) atas pengapalan curah.
     */
    public function postMarineFreightAccrual(
        string $bargeCode,
        string $vesselName,
        int $freightAmountIdr
    ): string {
        $this->assertPositiveAmount($freightAmountIdr, 'sewa tongkang');

        $dto = new PostingDTO(
            type: MiningTransactionTypes::MINING_BARGE_FREIGHT_COST->value,
            description: "Akrual biaya sewa tongkang {$vesselName} ({$bargeCode})",
            idempotencyKey: "min:barge-freight:{$bargeCode}",
            entries: Posting::lines()
                ->debit(MiningAccounts::marineFreightExpense(), $freightAmountIdr, 'IDR')
                ->credit(MiningAccounts::bargeFreightPayable(), $freightAmountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_BARGE_SHIPMENT',
            referenceId: $bargeCode,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-BRG-{$bargeCode}";
    }

    /**
     * 3. Beban operasional survei fotogrametri drone & sensor inklinometer geoteknik.
     */
    public function postDroneSurveyDepreciation(
        string $surveyCode,
        int $expenseAmountIdr
    ): string {
        $this->assertPositiveAmount($expenseAmountIdr, 'survei drone geoteknik');

        $dto = new PostingDTO(
            type: MiningTransactionTypes::MINING_GEOTECH_SURVEY->value,
            description: "Beban operasional fotogrametri drone 3D survei {$surveyCode}",
            idempotencyKey: "min:drone-survey:{$surveyCode}",
            entries: Posting::lines()
                ->debit(MiningAccounts::geotechMonitoringExpense(), $expenseAmountIdr, 'IDR')
                ->credit(MiningAccounts::bankCash(), $expenseAmountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_DRONE_SURVEY',
            referenceId: $surveyCode,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-DRN-{$surveyCode}";
    }

    /**
     * 4. Pengakuan provisi remediasi lereng dan buttressing pasca-insiden geoteknik.
     */
    public function postSlopeRemediationProvision(
        string $incidentCode,
        int $provisionAmountIdr
    ): string {
        $this->assertPositiveAmount($provisionAmountIdr, 'provisi remediasi lereng');

        $dto = new PostingDTO(
            type: MiningTransactionTypes::MINING_SLOPE_REMEDIATION->value,
            description: "Provisi perkuatan dan remediasi lereng insiden {$incidentCode}",
            idempotencyKey: "min:slope-remediation:{$incidentCode}",
            entries: Posting::lines()
                ->debit(MiningAccounts::slopeRemediationExpense(), $provisionAmountIdr, 'IDR')
                ->credit(MiningAccounts::slopeRemediationProvision(), $provisionAmountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_GEOTECH_INCIDENT',
            referenceId: $incidentCode,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-SLP-{$incidentCode}";
    }

    private function assertPositiveAmount(int $amount, string $label): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Nilai nominal {$label} harus berupa bilangan positif lebih dari 0.");
        }
    }
}
