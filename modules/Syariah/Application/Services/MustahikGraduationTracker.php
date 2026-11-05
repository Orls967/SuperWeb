<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Syariah\Domain\Models\SybMustahikGraduation;

class MustahikGraduationTracker
{
    /**
     * Mendaftarkan permohonan asesmen graduasi mustahik menjadi muzakki mandiri.
     */
    public function initiateAssessment(
        string $partyRef,
        string $currentStage,
        string $targetStage,
        int $omzetBaselineIdr,
        int $omzetCurrentIdr,
        string $fieldOfficerUserId
    ): SybMustahikGraduation {
        if ($omzetBaselineIdr <= 0) {
            throw new InvalidArgumentException('Omzet baseline harus lebih besar dari 0.');
        }

        $growthRateBps = (int) round((($omzetCurrentIdr - $omzetBaselineIdr) / (float) $omzetBaselineIdr) * 10000);

        $code = 'GRD-'.strtoupper(Str::random(8));

        return SybMustahikGraduation::create([
            'graduation_code' => $code,
            'party_ref' => $partyRef,
            'current_stage' => $currentStage,
            'target_stage' => $targetStage,
            'omzet_baseline_idr' => $omzetBaselineIdr,
            'omzet_current_idr' => $omzetCurrentIdr,
            'growth_rate_bps' => $growthRateBps,
            'field_officer_verifier' => $fieldOfficerUserId,
            'amil_supervisor_verifier' => null,
            'status' => 'PENDING',
        ]);
    }

    /**
     * Verifikasi four-eyes supervisi amil untuk kelayakan penetapan graduasi mustahik.
     */
    public function verifyGraduation(string $graduationCode, string $amilSupervisorUserId): SybMustahikGraduation
    {
        return DB::transaction(function () use ($graduationCode, $amilSupervisorUserId) {
            $grad = SybMustahikGraduation::where('graduation_code', $graduationCode)->lockForUpdate()->firstOrFail();

            if ($grad->field_officer_verifier === $amilSupervisorUserId) {
                throw new InvalidArgumentException('Prinsip Four-Eyes: Supervisor amil tidak boleh sama dengan petugas lapangan.');
            }

            // Kriteria graduasi: Kenaikan omzet minimal 20% (2.000 bps)
            if ($grad->growth_rate_bps < 2000) {
                $grad->status = 'REJECTED';
                $grad->amil_supervisor_verifier = $amilSupervisorUserId;
                $grad->verified_at = CarbonImmutable::now();
                $grad->save();

                return $grad;
            }

            $grad->status = 'GRADUATED';
            $grad->amil_supervisor_verifier = $amilSupervisorUserId;
            $grad->verified_at = CarbonImmutable::now();
            $grad->current_stage = $grad->target_stage;
            $grad->save();

            return $grad;
        });
    }
}

