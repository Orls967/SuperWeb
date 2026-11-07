<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\BargeShipmentStatus;
use Modules\Mining\Domain\Models\MiningBargeDraftSurvey;
use Modules\Mining\Domain\StateMachines\BargeShipmentStateMachine;
use Modules\Mining\Ledger\MiningFase119LedgerPostingService;

/**
 * Domain Action: Penyelesaian Ongkos Angkut Tongkang & Demurrage / Despatch (Freight Settlement).
 * Menghitung biaya pengapalan berdasarkan tonase terverifikasi draf, deviasi laytime sandar,
 * denda demurrage / insentif despatch, serta membukukan jurnal pengeluaran ke buku besar.
 */
class SettleBargeFreightTariffAction
{
    public function __construct(
        private readonly BargeShipmentStateMachine $stateMachine,
        private readonly MiningFase119LedgerPostingService $ledgerService
    ) {}

    /**
     * @param  array{
     *     barge_draft_survey_id: string,
     *     contract_freight_rate_per_ton_idr: float,
     *     allowed_laytime_hours: float,
     *     actual_laytime_hours: float,
     *     demurrage_rate_per_day_idr: float,
     *     despatch_rate_per_day_idr?: float,
     *     transshipment_stevedoring_cost_idr?: float
     * } $data
     * @return array{
     *     barge_code: string,
     *     cargo_tonnage_wmt: float,
     *     base_freight_idr: float,
     *     demurrage_idr: float,
     *     despatch_idr: float,
     *     net_freight_payable_idr: float,
     *     status: string,
     *     ledger_journal_uuid?: string|null
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $survey = MiningBargeDraftSurvey::query()->findOrFail($data['barge_draft_survey_id']);
        $tonnageWmt = (float) ($survey->cargo_tonnage_wmt ?? 0.0);

        if ($tonnageWmt <= 0) {
            throw new InvalidArgumentException("Tongkang {$survey->barge_code} belum memiliki data muatan bersih terverifikasi.");
        }

        $ratePerTon = (float) $data['contract_freight_rate_per_ton_idr'];
        $baseFreight = round($tonnageWmt * $ratePerTon, 2);

        $allowedHours = (float) $data['allowed_laytime_hours'];
        $actualHours = (float) $data['actual_laytime_hours'];
        $demurrageRatePerDay = (float) $data['demurrage_rate_per_day_idr'];
        $despatchRatePerDay = (float) ($data['despatch_rate_per_day_idr'] ?? ($demurrageRatePerDay / 2.0));

        $demurrageIdr = 0.0;
        $despatchIdr = 0.0;

        $timeDeltaHours = $actualHours - $allowedHours;
        if ($timeDeltaHours > 0) {
            // Melebihi laytime: Demurrage dikenakan
            $excessDays = $timeDeltaHours / 24.0;
            $demurrageIdr = round($excessDays * $demurrageRatePerDay, 2);
        } elseif ($timeDeltaHours < 0) {
            // Lebih cepat dari laytime: Despatch dibayarkan
            $savedDays = abs($timeDeltaHours) / 24.0;
            $despatchIdr = round($savedDays * $despatchRatePerDay, 2);
        }

        $stevedoring = (float) ($data['transshipment_stevedoring_cost_idr'] ?? 0.0);
        $netPayable = round($baseFreight + $demurrageIdr - $despatchIdr + $stevedoring, 2);

        // Posting ke buku besar mining ledger
        $journalUuid = $this->ledgerService->postBargeFreightSettlement(
            referenceNumber: 'FRT-SETTLE-'.$survey->barge_code.'-'.date('Ymd'),
            bargeCode: $survey->barge_code,
            amountIdr: (int) round($netPayable),
            tonnageWmt: $tonnageWmt,
            demurrageIdr: (int) round($demurrageIdr),
            despatchIdr: (int) round($despatchIdr),
            description: 'Pelunasan ongkos angkut tongkang '.$survey->barge_code.' muatan '.$tonnageWmt.' WMT'
        );

        $currentStatus = BargeShipmentStatus::tryFrom($survey->status) ?? BargeShipmentStatus::IN_TRANSIT;
        $targetStatus = BargeShipmentStatus::SETTLED;

        if ($this->stateMachine->canTransition($currentStatus, $targetStatus)) {
            $survey->status = $targetStatus->value;
        }

        $meta = $survey->metadata ?? [];
        $meta['freight_settlement'] = [
            'base_freight_idr' => $baseFreight,
            'demurrage_idr' => $demurrageIdr,
            'despatch_idr' => $despatchIdr,
            'stevedoring_idr' => $stevedoring,
            'net_payable_idr' => $netPayable,
            'settled_at' => now()->toIso8601String(),
        ];
        $survey->metadata = $meta;
        $survey->save();

        return [
            'barge_code' => $survey->barge_code,
            'cargo_tonnage_wmt' => $tonnageWmt,
            'base_freight_idr' => $baseFreight,
            'demurrage_idr' => $demurrageIdr,
            'despatch_idr' => $despatchIdr,
            'net_freight_payable_idr' => $netPayable,
            'status' => $survey->status,
            'ledger_journal_uuid' => $journalUuid,
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

        if (($data['contract_freight_rate_per_ton_idr'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Tarif freight per ton harus lebih besar dari Rp 0.');
        }

        if (($data['allowed_laytime_hours'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Alokasi jam laytime harus lebih besar dari 0.');
        }
    }
}
