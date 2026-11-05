<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Domain\Models\BlacklistedEntity;
use Modules\Insurance\Domain\Models\SiuInvestigation;

final class DecideSiuCaseAction
{
    public function __construct(private readonly ClockInterface $clock) {}

    public function execute(
        int $investigationId,
        string $decision, // confirmed_fraud | cleared
        string $decisionNotes,
        int $decidedByUserId,
        ?string $identityNumberToBlacklist = null,
    ): SiuInvestigation {
        return DB::transaction(function () use (
            $investigationId,
            $decision,
            $decisionNotes,
            $decidedByUserId,
            $identityNumberToBlacklist
        ) {
            $case = SiuInvestigation::query()->lockForUpdate()->find($investigationId);
            if ($case === null) {
                throw new DomainException("Berkas SIU #{$investigationId} tidak ditemukan.");
            }

            if (in_array($case->status, ['confirmed_fraud', 'cleared'], true)) {
                throw new DomainException("Berkas SIU #{$investigationId} sudah diputus sebelumnya ({$case->status}).");
            }

            // Aturan Four-Eyes: Penyelidik awal tidak boleh memutus sendiri kasusnya
            if ($case->assigned_to !== null && $case->assigned_to === $decidedByUserId) {
                throw new DomainException('Prinsip Four-Eyes: Penyelidik yang menangani kasus tidak dapat menjadi pemutus.');
            }

            $now = CarbonImmutable::instance($this->clock->now());

            $case->update([
                'status' => $decision,
                'decision_notes' => $decisionNotes,
                'decided_by' => $decidedByUserId,
                'decided_at' => $now,
            ]);

            // Jika confirmed fraud, blacklist entitas terkait
            if ($decision === 'confirmed_fraud') {
                BlacklistedEntity::create([
                    'party_id' => $case->subject_party_id,
                    'identity_number' => $identityNumberToBlacklist,
                    'reason' => "Putusan kasus SIU {$case->case_number}: {$decisionNotes}",
                    'source_case_number' => $case->case_number,
                    'blacklisted_at' => $now,
                    'created_by' => $decidedByUserId,
                ]);
            }

            return $case;
        });
    }
}
