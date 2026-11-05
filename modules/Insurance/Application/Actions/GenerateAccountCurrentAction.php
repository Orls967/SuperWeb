<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Insurance\Application\Support\InsuranceNumbering;
use Modules\Insurance\Domain\Enums\AccountCurrentStatus;
use Modules\Insurance\Domain\Models\CessionLine;
use Modules\Insurance\Domain\Models\ReinsuranceAccountCurrent;

final class GenerateAccountCurrentAction
{
    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(string $reinsurerPartyId, string $periodYm, ?int $actorId = null): ReinsuranceAccountCurrent
    {
        return DB::transaction(function () use ($reinsurerPartyId, $periodYm, $actorId) {
            $existing = ReinsuranceAccountCurrent::query()
                ->where('reinsurer_party_id', $reinsurerPartyId)
                ->where('period_ym', $periodYm)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $lines = CessionLine::query()
                ->join('ins_cession_events as e', 'e.id', '=', 'ins_cession_lines.event_id')
                ->where('ins_cession_lines.reinsurer_party_id', $reinsurerPartyId)
                ->where('e.period_ym', $periodYm)
                ->select('ins_cession_lines.*', 'e.kind as event_kind')
                ->get();

            if ($lines->isEmpty()) {
                throw new DomainException("Tidak ada transaksi cession untuk reinsurer {$reinsurerPartyId} pada periode {$periodYm}.");
            }

            $premiumCeded = 0;
            $commission = 0;
            $claimsRecoverable = 0;
            $reinstatement = 0;

            foreach ($lines as $line) {
                if (in_array($line->event_kind, ['premium', 'treaty_premium'], true)) {
                    $premiumCeded += (int) $line->ceded_idr;
                } elseif ($line->event_kind === 'claim') {
                    $claimsRecoverable += (int) $line->ceded_idr;
                }
                $commission += (int) $line->commission_idr;
                $reinstatement += (int) $line->reinstatement_premium_idr;
            }

            $netDue = ($premiumCeded + $reinstatement) - ($commission + $claimsRecoverable);
            $number = InsuranceNumbering::accountCurrent($this->numbering);

            return ReinsuranceAccountCurrent::create([
                'number' => $number,
                'reinsurer_party_id' => $reinsurerPartyId,
                'period_ym' => $periodYm,
                'premium_ceded_idr' => $premiumCeded,
                'commission_idr' => $commission,
                'claims_recoverable_idr' => $claimsRecoverable,
                'reinstatement_idr' => $reinstatement,
                'net_due_idr' => $netDue,
                'status' => AccountCurrentStatus::Issued,
                'issued_at' => $this->clock->now(),
                'created_by' => $actorId,
            ]);
        });
    }
}
