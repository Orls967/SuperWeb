<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Billing;

use DomainException;
use Illuminate\Support\Str;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\StateMachines\BillingEpisodeStateMachine;

final class CreateBillingEpisodeAction
{
    /**
     * @param  array{encounter_id: int, initial_deposit_idr?: int}  $data
     */
    public function execute(array $data): BillingEpisode
    {
        $encounterId = (int) $data['encounter_id'];
        $encounter = Encounter::find($encounterId);

        if (! $encounter) {
            throw new DomainException("Encounter #{$encounterId} tidak ditemukan.");
        }

        // Cek apakah sudah ada episode aktif
        $existing = BillingEpisode::where('encounter_id', $encounterId)
            ->whereIn('status', [BillingEpisodeStateMachine::STATUS_OPEN, BillingEpisodeStateMachine::STATUS_VERIFIED_CODER])
            ->first();

        if ($existing) {
            return $existing;
        }

        $deposit = max(0, (int) ($data['initial_deposit_idr'] ?? 0));
        $code = 'EP-'.date('Ymd').'-'.strtoupper(Str::random(6));

        return BillingEpisode::create([
            'episode_code' => $code,
            'encounter_id' => $encounterId,
            'escrow_deposit_idr' => $deposit,
            'total_charges_idr' => 0,
            'bpjs_coverage_idr' => 0,
            'insurance_copay_idr' => 0,
            'patient_share_idr' => 0,
            'status' => BillingEpisodeStateMachine::STATUS_OPEN,
        ]);
    }
}
