<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Logistics\Contracts\RateCardOverrideResolver;

/**
 * Implementasi 29.6: rate card kontrak mengalahkan tarif standar.
 *
 * Kontrak tipe `service` aktif yang menautkan `linked_rate_card_id` dan
 * memiliki pihak kedua dengan party_id pada lgx_shipper_accounts akan
 * menentukan rate card override untuk shipper tersebut.
 *
 * Seluruh akses tabel hanya lewat query DB (tanpa import Domain Logistics
 * selain interface contract), sehingga batas modul tetap terjaga.
 */
class ContractRateResolver implements RateCardOverrideResolver
{
    public function resolve(
        int $shipperId,
        string $serviceLevel,
        string $mode
    ): ?int {
        $partyIds = DB::table('lgx_shipper_accounts')
            ->where('shipper_id', $shipperId)
            ->where('is_active', true)
            ->whereNotNull('party_id')
            ->pluck('party_id');

        if ($partyIds->isEmpty()) {
            return null;
        }

        return $this->resolveForParties($partyIds->all());
    }

    /**
     * Rate card kontrak aktif untuk sekumpulan party (dipakai juga oleh
     * ContractUsageSyncService agar sumber usage rate-card dan resolver
     * mengikuti aturan yang sama).
     *
     * @param  array<int, string>  $partyIds
     */
    public function resolveForParties(array $partyIds): ?int
    {
        if ($partyIds === []) {
            return null;
        }

        $rateCardId = DB::table('ctr_contracts')
            ->join('ctr_contract_parties', 'ctr_contract_parties.contract_id', '=', 'ctr_contracts.id')
            ->whereIn('ctr_contract_parties.party_id', $partyIds)
            ->where('ctr_contract_parties.role', 'second_party')
            ->where('ctr_contracts.status', 'active')
            ->where('ctr_contracts.contract_type', 'service')
            ->whereNotNull('ctr_contracts.linked_rate_card_id')
            ->where('ctr_contracts.end_date', '>=', now()->toDateString())
            ->orderByDesc('ctr_contracts.end_date')
            ->value('ctr_contracts.linked_rate_card_id');

        return $rateCardId === null ? null : (int) $rateCardId;
    }
}
