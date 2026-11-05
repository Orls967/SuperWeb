<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Insurance\Application\Support\InsuranceNumbering;
use Modules\Insurance\Domain\Enums\BordereauStatus;
use Modules\Insurance\Domain\Models\ReinsuranceBordereau;
use Modules\Insurance\Domain\Models\ReinsuranceBordereauLine;

final class ReverseBordereauAction
{
    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(ReinsuranceBordereau $original, string $reason, ?int $actorId = null): ReinsuranceBordereau
    {
        $original->status->assertCanTransitionTo(BordereauStatus::Reversed);

        return DB::transaction(function () use ($original, $actorId) {
            $original->forceFill([
                'status' => BordereauStatus::Reversed,
            ])->save();

            $number = InsuranceNumbering::bordereau($this->numbering);

            $reversal = ReinsuranceBordereau::create([
                'number' => $number,
                'treaty_id' => $original->treaty_id,
                'reinsurer_party_id' => $original->reinsurer_party_id,
                'kind' => $original->kind,
                'period_ym' => $original->period_ym,
                'status' => BordereauStatus::Sent,
                'line_count' => $original->line_count,
                'total_ceded_idr' => -$original->total_ceded_idr,
                'total_commission_idr' => -$original->total_commission_idr,
                'total_reinstatement_idr' => -$original->total_reinstatement_idr,
                'reverses_id' => $original->id,
                'sent_at' => $this->clock->now(),
                'created_by' => $actorId,
            ]);

            foreach ($original->lines as $line) {
                ReinsuranceBordereauLine::create([
                    'bordereau_id' => $reversal->id,
                    'cession_line_id' => $line->cession_line_id,
                    'amount_idr' => -$line->amount_idr,
                    'commission_idr' => -$line->commission_idr,
                    'reinstatement_idr' => -$line->reinstatement_idr,
                ]);
            }

            return $reversal;
        });
    }
}
