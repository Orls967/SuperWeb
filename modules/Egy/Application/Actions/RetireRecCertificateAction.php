<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Carbon\Carbon;
use InvalidArgumentException;
use Modules\Egy\Domain\Models\RecCertificate;
use Modules\Egy\Domain\StateMachines\RecCertificateStateMachine;
use Modules\Esg\Contracts\CarbonCreditRetirementRecorder;
use Modules\Shared\Domain\Algorithms\Utility\RecLifecycleTracker;

/**
 * Memensiunkan unit REC secara permanen untuk klaim keberlanjutan (Green Lease / Net-Zero Customer).
 * Mencegah klaim ganda dan mencatat pencatatan ke modul ESG via G7-K10.
 */
final class RetireRecCertificateAction
{
    public function __construct(
        private readonly RecLifecycleTracker $lifecycleTracker,
        private readonly RecCertificateStateMachine $stateMachine,
        private readonly CarbonCreditRetirementRecorder $esgRecorder,
    ) {}

    /**
     * @param  int  $recCertificateId  ID sertifikat REC
     * @param  string  $beneficiaryName  Nama penerima manfaat klaim (misal Tenant Mall Blok B)
     * @param  string  $retirementReason  Alasan klaim netralitas karbon
     * @param  int|null  $claimantPartyId  Pihak pengklaim
     */
    public function execute(
        int $recCertificateId,
        string $beneficiaryName,
        string $retirementReason,
        ?int $claimantPartyId = null,
    ): RecCertificate {
        $certificate = RecCertificate::findOrFail($recCertificateId);

        if ($certificate->status === RecCertificateStateMachine::STATUS_RETIRED) {
            throw new InvalidArgumentException(
                "Sertifikat REC {$certificate->certificate_serial} sudah berstatus retired sebelumnya."
            );
        }

        // Validasi dan hitung sertifikat lifecycle
        $lifecycleResult = $this->lifecycleTracker->transition(
            serialNumber: (string) $certificate->certificate_serial,
            currentStatus: (string) $certificate->status,
            targetStatus: RecCertificateStateMachine::STATUS_RETIRED,
            energyWh: (int) $certificate->energy_wh,
            generatorAssetId: (int) $certificate->generation_asset_id,
            vintageYear: (string) $certificate->vintage_year,
            ownerPartyId: $claimantPartyId ?? (int) $certificate->owner_party_id,
            claimReason: $retirementReason
        );

        // Catat ke modul ESG via kontrak lintas modul G7-K10
        // Konversi Wh ke kg CO2e terhindarkan (faktor emisi grid rata-rata ~ 0.8 kg CO2e / kWh)
        $avoidedCo2eKg = (int) intdiv((int) $certificate->energy_wh * 8, 10000);
        $this->esgRecorder->recordRetirement(
            vintageYear: (string) $certificate->vintage_year,
            quantityKgCo2e: $avoidedCo2eKg,
            serialNumber: (string) $certificate->certificate_serial,
            reason: $retirementReason,
            entityPartyId: (string) ($claimantPartyId ?? $certificate->owner_party_id)
        );

        $certificate->status = $this->stateMachine->transition(
            $certificate->status,
            RecCertificateStateMachine::STATUS_RETIRED
        );
        $certificate->retired_at = Carbon::now();
        $certificate->retirement_beneficiary = $beneficiaryName;
        $certificate->retirement_reason = $retirementReason;
        $certificate->certificate_hash = $lifecycleResult->certificateHash;
        $certificate->save();

        return $certificate;
    }
}
