<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Contract\Domain\Models\ClauseTemplate;
use Modules\Contract\Domain\Models\Contract;

/**
 * Kepatuhan & skor risiko kontrak (29.7).
 *
 * Skor adalah aturan simulasi (bukan penilaian legal): total 100,
 * makin tinggi makin berisiko. Flag disimpan sebagai JSON di kontrak.
 */
class ContractRiskService
{
    /** Aturan: [kode, bobot, deskripsi]. */
    private const RULES = [
        ['missing_arbitration', 20, 'Forum arbitrase tidak ditetapkan'],
        ['high_value_no_escalation', 15, 'Kontrak besar tanpa klausul eskalasi'],
        ['long_term_no_notice', 15, 'Jangka panjang tanpa notice period memadai'],
        ['no_confidentiality', 15, 'Tidak ada klausul kerahasiaan (CL-CONFIDENTIAL)'],
        ['no_force_majeure', 15, 'Tidak ada klausul force majeure (CL-FORCE-MAJEURE)'],
        ['near_expiry', 10, 'Kontrak mendekati kedaluwarsa (< 60 hari)'],
        ['over_budget', 10, 'Pemakaian plafon melebihi 100%'],
    ];

    /**
     * Hitung skor risiko 0–100 dan daftar flag, lalu simpan ke kontrak.
     *
     * @return array{score: int, flags: array<int, string>}
     */
    public function score(Contract $contract, bool $persist = true): array
    {
        $score = 0;
        $flags = [];

        foreach (self::RULES as [$code, $weight, $description]) {
            if ($this->flagApplies($contract, $code)) {
                $score += $weight;
                $flags[] = $description;
            }
        }

        $score = min(100, $score);

        if ($persist) {
            DB::table('ctr_contracts')
                ->where('id', $contract->id)
                ->update([
                    'risk_score' => $score,
                    'risk_flags' => json_encode($flags),
                    'updated_at' => now(),
                ]);
        }

        return ['score' => $score, 'flags' => $flags];
    }

    private function flagApplies(Contract $contract, string $code): bool
    {
        return match ($code) {
            'missing_arbitration' => $contract->arbitration_rules === null && $contract->dispute_forum === null,

            'high_value_no_escalation' => (int) $contract->total_value_idr >= 1_000_000_000
                && ! $contract->escalation_enabled,

            'long_term_no_notice' => $contract->end_date !== null
                && $contract->start_date !== null
                && $contract->start_date->diffInDays($contract->end_date) > 365 * 2
                && (int) $contract->notice_period_days < 60,

            'no_confidentiality' => ! $this->hasClauseCode($contract, 'CL-CONFIDENTIAL'),

            'no_force_majeure' => ! $this->hasClauseCode($contract, 'CL-FORCE-MAJEURE'),

            'near_expiry' => $contract->status?->value === 'active'
                && $contract->end_date !== null
                && $contract->end_date->diffInDays(now(), false) <= 60
                && $contract->end_date->isFuture(),

            'over_budget' => (int) $contract->used_value_idr > (int) $contract->total_value_idr,

            default => false,
        };
    }

    private function hasClauseCode(Contract $contract, string $code): bool
    {
        return $contract->clauses()
            ->whereHas('clauseTemplate', fn ($q) => $q->where('code', $code))
            ->exists()
            || str_contains((string) $contract->current_body, $code)
            || $this->bodyHasTemplateText($contract, $code);
    }

    private function bodyHasTemplateText(Contract $contract, string $code): bool
    {
        $template = ClauseTemplate::query()
            ->where('code', $code)
            ->first();

        if ($template === null) {
            return false;
        }

        // Cocokkan judul klausul pada body sebagai fallback.
        return str_contains((string) $contract->current_body, $template->title);
    }
}
