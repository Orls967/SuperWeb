<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Insurance\Application\Support\InsuranceNumbering;
use Modules\Insurance\Domain\Enums\BordereauStatus;
use Modules\Insurance\Domain\Models\CessionLine;
use Modules\Insurance\Domain\Models\ReinsuranceBordereau;
use Modules\Insurance\Domain\Models\ReinsuranceBordereauLine;

final class GenerateBordereauAction
{
    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(int $treatyId, string $reinsurerPartyId, string $kind, string $periodYm, ?int $actorId = null): ReinsuranceBordereau
    {
        return DB::transaction(function () use ($treatyId, $reinsurerPartyId, $kind, $periodYm, $actorId) {
            // Cek bordereau aktif yang sudah ada
            $existing = ReinsuranceBordereau::query()
                ->where('treaty_id', $treatyId)
                ->where('reinsurer_party_id', $reinsurerPartyId)
                ->where('kind', $kind)
                ->where('period_ym', $periodYm)
                ->whereIn('status', [BordereauStatus::Draft, BordereauStatus::Sent, BordereauStatus::Acknowledged])
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // Ambil seluruh cession line yang belum masuk ke bordereau aktif
            $lines = CessionLine::query()
                ->join('ins_cession_events as e', 'e.id', '=', 'ins_cession_lines.event_id')
                ->where('ins_cession_lines.treaty_id', $treatyId)
                ->where('ins_cession_lines.reinsurer_party_id', $reinsurerPartyId)
                ->where('e.period_ym', $periodYm)
                ->where('e.kind', $kind)
                ->select('ins_cession_lines.*')
                ->get();

            if ($lines->isEmpty()) {
                throw new DomainException("Tidak ada data cession {$kind} untuk periode {$periodYm}.");
            }

            $totalCeded = (int) $lines->sum('ceded_idr');
            $totalCommission = (int) $lines->sum('commission_idr');
            $totalReinstatement = (int) $lines->sum('reinstatement_premium_idr');

            $number = InsuranceNumbering::bordereau($this->numbering);

            $bordereau = ReinsuranceBordereau::create([
                'number' => $number,
                'treaty_id' => $treatyId,
                'reinsurer_party_id' => $reinsurerPartyId,
                'kind' => $kind,
                'period_ym' => $periodYm,
                'status' => BordereauStatus::Draft,
                'line_count' => $lines->count(),
                'total_ceded_idr' => $totalCeded,
                'total_commission_idr' => $totalCommission,
                'total_reinstatement_idr' => $totalReinstatement,
                'created_by' => $actorId,
            ]);

            foreach ($lines as $line) {
                ReinsuranceBordereauLine::create([
                    'bordereau_id' => $bordereau->id,
                    'cession_line_id' => $line->id,
                    'amount_idr' => (int) $line->ceded_idr,
                    'commission_idr' => (int) $line->commission_idr,
                    'reinstatement_idr' => (int) $line->reinstatement_premium_idr,
                ]);
            }

            return $bordereau->load('lines');
        });
    }
}
