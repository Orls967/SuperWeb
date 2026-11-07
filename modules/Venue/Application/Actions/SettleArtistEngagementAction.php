<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Application\Services\VenueEconomyLedgerService;
use Modules\Venue\Domain\Algorithms\ArtistDoorSplitCalculator;
use Modules\Venue\Domain\Algorithms\ArtistDoorSplitInput;
use Modules\Venue\Domain\Models\ArtistEngagement;
use Modules\Venue\Domain\StateMachines\ArtistEngagementStateMachine;

final class SettleArtistEngagementAction extends BaseAction
{
    public function __construct(
        private readonly ArtistDoorSplitCalculator $calculator,
        private readonly VenueEconomyLedgerService $ledgerService
    ) {}

    public function execute(
        int $eventId,
        string $artistName,
        ArtistDoorSplitInput $input,
        int $actorId,
        ?string $managementCompany = null
    ): ArtistEngagement {
        return DB::transaction(function () use ($eventId, $artistName, $input, $actorId, $managementCompany) {
            // 1. Hitung via Algoritma V5
            $result = $this->calculator->calculate($input);

            // 2. Validasi Keabsahan Sertifikat Komputasi
            $result->certificate->isValid();

            // 3. Generate Kontrak Unik
            $hashEntropy = hash('sha256', "{$eventId}:{$artistName}:{$input->dealType}:{$result->grossPayIdr}");
            $contractNumber = 'ART-CON-'.strtoupper(substr($hashEntropy, 0, 10));

            // 4. Catat Engagement Artis
            $engagement = ArtistEngagement::query()->create([
                'event_id' => $eventId,
                'artist_name' => $artistName,
                'management_company' => $managementCompany ?? 'Artist Management Ltd',
                'contract_number' => $contractNumber,
                'deal_type' => $input->dealType,
                'guarantee_idr' => $input->guaranteeIdr,
                'door_share_bps' => $input->shareBps,
                'wht_bps' => $input->whtBps,
                'advance_paid_idr' => $input->advancePaidIdr,
                'gross_pay_idr' => $result->grossPayIdr,
                'wht_idr' => $result->whtIdr,
                'net_paid_idr' => $result->netPayableIdr,
                'currency' => $input->currency,
                'valas_minor_cents' => $result->valasMinorCents,
                'exchange_rate_idr' => $input->exchangeRateIdr,
                'status' => ArtistEngagementStateMachine::STATUS_SETTLED,
                'actor_id' => $actorId,
            ]);

            // 5. Posting Buku Besar Akrual Double-Entry
            $this->ledgerService->postArtistFinalSettlement(
                contractNumber: $contractNumber,
                grossPayIdr: $result->grossPayIdr,
                whtIdr: $result->whtIdr,
                advancePaidIdr: $input->advancePaidIdr,
                netPayableIdr: $result->netPayableIdr
            );

            return $engagement;
        });
    }
}
