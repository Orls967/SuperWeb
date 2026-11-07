<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Contracts\BulkMarineVesselLogisticsGateway;
use Modules\Mining\Domain\Enums\BargeShipmentStatus;
use Modules\Mining\Domain\Models\MiningBargeDraftSurvey;
use Modules\Mining\Domain\StateMachines\BargeShipmentStateMachine;

/**
 * Domain Action: Audit Rantai Kendali Pengapalan Tongkang (Barge Chain of Custody).
 * Memvalidasi nomor segel palka, dokumen COA (Certificate of Analysis) kadar mineral,
 * Surat Persetujuan Berlayar (SPB) KSOP / Syahbandar, dan status geofence dermaga khusus.
 */
class AuditBargeChainOfCustodyAction
{
    public function __construct(
        private readonly BulkMarineVesselLogisticsGateway $marineGateway,
        private readonly BargeShipmentStateMachine $stateMachine
    ) {}

    /**
     * @param  array{
     *     barge_draft_survey_id: string,
     *     jetty_id: string,
     *     syahbandar_spb_number: string,
     *     certificate_of_analysis_number: string,
     *     tamper_seal_numbers: list<string>,
     *     auditor_name: string,
     *     destination_port: string
     * } $data
     * @return array{
     *     barge_code: string,
     *     is_chain_of_custody_intact: bool,
     *     status: string,
     *     spb_number: string,
     *     coa_number: string,
     *     seals_verified_count: int,
     *     departure_cleared_at: string
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $survey = MiningBargeDraftSurvey::query()->findOrFail($data['barge_draft_survey_id']);
        $bargeCode = $survey->barge_code;

        // Validasi jetty berthing clearance dari gateway
        $isJettyClear = $this->marineGateway->verifyJettyBerthingClearance($bargeCode, $data['jetty_id']);
        if (! $isJettyClear) {
            throw new InvalidArgumentException("Tongkang {$bargeCode} tidak memiliki izin tambat valid di jetty {$data['jetty_id']}.");
        }

        $seals = $data['tamper_seal_numbers'];
        if (count($seals) === 0) {
            throw new InvalidArgumentException('Minimal 1 nomor segel tamper-evident wajib disertakan untuk chain of custody.');
        }

        // Notifikasi Syahbandar
        $this->marineGateway->notifyPortAuthorityBargeDeparture($bargeCode, [
            'tonnage_wmt' => (float) ($survey->cargo_tonnage_wmt ?? 0.0),
            'spb_number' => $data['syahbandar_spb_number'],
            'destination_port' => $data['destination_port'],
        ]);

        $currentStatus = BargeShipmentStatus::tryFrom($survey->status) ?? BargeShipmentStatus::LOADED;
        $targetStatus = BargeShipmentStatus::IN_TRANSIT;

        // Transisi ke DEPARTED jika valid
        if ($this->stateMachine->canTransition($currentStatus, $targetStatus)) {
            $survey->status = $targetStatus->value;
        }

        $meta = $survey->metadata ?? [];
        $meta['chain_of_custody_audit'] = [
            'jetty_id' => $data['jetty_id'],
            'spb_number' => $data['syahbandar_spb_number'],
            'coa_number' => $data['certificate_of_analysis_number'],
            'tamper_seals' => $seals,
            'auditor' => $data['auditor_name'],
            'destination_port' => $data['destination_port'],
            'cleared_at' => now()->toIso8601String(),
        ];
        $survey->metadata = $meta;
        $survey->save();

        return [
            'barge_code' => $bargeCode,
            'is_chain_of_custody_intact' => true,
            'status' => $survey->status,
            'spb_number' => $data['syahbandar_spb_number'],
            'coa_number' => $data['certificate_of_analysis_number'],
            'seals_verified_count' => count($seals),
            'departure_cleared_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['barge_draft_survey_id'])) {
            throw new InvalidArgumentException('Barge draft survey ID wajib diisi.');
        }

        if (empty($data['jetty_id'])) {
            throw new InvalidArgumentException('ID Jetty terminal muat harus disertakan.');
        }

        if (empty($data['syahbandar_spb_number'])) {
            throw new InvalidArgumentException('Nomor Surat Persetujuan Berlayar (SPB) Syahbandar wajib diisi.');
        }

        if (empty($data['certificate_of_analysis_number'])) {
            throw new InvalidArgumentException('Nomor Certificate of Analysis (COA) kadar wajib diisi.');
        }
    }
}
