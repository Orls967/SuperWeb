<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Insurance\Domain\Enums\TreatyStatus;
use Modules\Insurance\Domain\Enums\TreatyType;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;

/**
 * Pemilihan treaty yang berlaku untuk suatu kejadian. ORDER BY eksplisit di setiap query (PR3).
 */
final class TreatyCatalog
{
    /**
     * Treaty proporsional untuk premi (basis risk-attaching: tanggal mulai polis); hanya status aktif.
     *
     * @return Collection<int, ReinsuranceTreaty>
     */
    public function proportionalForPremium(string $lineCategory, CarbonInterface $riskStart): Collection
    {
        return ReinsuranceTreaty::query()
            ->with('panel')
            ->where('status', TreatyStatus::Active->value)
            ->whereIn('type', [TreatyType::QuotaShare->value, TreatyType::Surplus->value, TreatyType::Facultative->value])
            ->where(fn ($q) => $q->whereNull('line_category')->orWhere('line_category', $lineCategory))
            ->whereDate('inception_date', '<=', $riskStart->toDateString())
            ->whereDate('expiry_date', '>=', $riskStart->toDateString())
            ->orderBy('id')
            ->get();
    }

    /**
     * Treaty XoL per-risk untuk klaim (basis losses-occurring: tanggal kejadian); aktif atau run-off.
     * Urut menaik menurut attachment lalu id.
     *
     * @return Collection<int, ReinsuranceTreaty>
     */
    public function xolForLoss(string $lineCategory, CarbonInterface $incidentDate, TreatyType $type = TreatyType::XolRisk): Collection
    {
        return ReinsuranceTreaty::query()
            ->with('panel')
            ->whereIn('status', [TreatyStatus::Active->value, TreatyStatus::RunOff->value])
            ->where('type', $type->value)
            ->where(fn ($q) => $q->whereNull('line_category')->orWhere('line_category', $lineCategory))
            ->whereDate('inception_date', '<=', $incidentDate->toDateString())
            ->whereDate('expiry_date', '>=', $incidentDate->toDateString())
            ->orderBy('attachment_idr')
            ->orderBy('id')
            ->when(DB::transactionLevel() > 0, static fn (Builder $q) => $q->lockForUpdate())
            ->get();
    }

    /**
     * @return Collection<int, ReinsuranceTreaty>
     */
    public function stopLossTreaties(): Collection
    {
        return ReinsuranceTreaty::query()
            ->with('panel')
            ->whereIn('status', [TreatyStatus::Active->value, TreatyStatus::RunOff->value])
            ->where('type', TreatyType::StopLoss->value)
            ->orderBy('id')
            ->get();
    }
}
