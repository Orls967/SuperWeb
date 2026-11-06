<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Mobility\Contracts\EvChargingSessionSource;

/**
 * G7-K8 Implementation: EvChargingSessionSource
 *
 * Mengambil data sesi pengisian EV dari modul Mobility (oto_ev_sessions).
 */
final class EvChargingSessionSourceService implements EvChargingSessionSource
{
    /**
     * @return list<array{
     *     id: int,
     *     started_at: string,
     *     ended_at: string,
     *     kwh_milli: int,
     *     flexible: bool
     * }>
     */
    public function sessionsBetween(int $siteAssetId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $fromStr = $from->toDateTimeString();
        $toStr = $to->toDateTimeString();

        $query = DB::table('oto_ev_sessions')
            ->join('oto_ev_chargers', 'oto_ev_chargers.id', '=', 'oto_ev_sessions.charger_id')
            ->where(function ($q) use ($siteAssetId) {
                $q->where('oto_ev_chargers.station_id', $siteAssetId)
                    ->orWhere('oto_ev_sessions.charger_id', $siteAssetId);
            })
            ->where(function ($q) use ($fromStr, $toStr) {
                $q->whereBetween('oto_ev_sessions.plugged_in_at', [$fromStr, $toStr])
                    ->orWhereBetween('oto_ev_sessions.reserved_from', [$fromStr, $toStr]);
            })
            ->select([
                'oto_ev_sessions.id',
                'oto_ev_sessions.plugged_in_at',
                'oto_ev_sessions.reserved_from',
                'oto_ev_sessions.completed_at',
                'oto_ev_sessions.reserved_to',
                'oto_ev_sessions.energy_wh',
                'oto_ev_sessions.deferred_minutes',
                'oto_ev_sessions.status',
            ])
            ->orderByRaw('COALESCE(oto_ev_sessions.plugged_in_at, oto_ev_sessions.reserved_from) ASC')
            ->orderBy('oto_ev_sessions.id', 'ASC')
            ->get();

        $results = [];
        foreach ($query as $row) {
            $startedAt = $row->plugged_in_at ?? $row->reserved_from ?? $fromStr;
            $endedAt = $row->completed_at ?? $row->reserved_to ?? $toStr;
            $isFlexible = ((int) ($row->deferred_minutes ?? 0)) > 0 || $row->status === 'reserved';

            $results[] = [
                'id' => (int) $row->id,
                'started_at' => (string) $startedAt,
                'ended_at' => (string) $endedAt,
                'kwh_milli' => (int) ($row->energy_wh ?? 0),
                'flexible' => $isFlexible,
            ];
        }

        return $results;
    }
}
