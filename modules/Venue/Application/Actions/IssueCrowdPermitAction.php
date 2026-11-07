<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Contracts\EventCoverBinder;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\VenuePermit;
use Modules\Venue\Ledger\VenueTransactionTypes;

final class IssueCrowdPermitAction
{
    public function __construct(
        private readonly ?EventCoverBinder $insuranceBinder = null,
        private readonly ?Ledger $ledger = null,
    ) {}

    public function execute(
        EntertainmentEvent $event,
        string $issuingAuthority,
        int $maxLegalCapacity,
        CarbonImmutable $validFrom,
        CarbonImmutable $validUntil,
        string $idempotencyKey
    ): VenuePermit {
        return DB::transaction(function () use ($event, $issuingAuthority, $maxLegalCapacity, $validFrom, $validUntil, $idempotencyKey) {
            $existing = VenuePermit::where('permit_number', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $count = VenuePermit::count() + 1;
            $permitNumber = sprintf('SIK-POLDA-2026-%04d', $count);

            $permit = VenuePermit::create([
                'permit_number' => $permitNumber,
                'event_id' => $event->id,
                'issuing_authority' => $issuingAuthority,
                'max_legal_capacity' => $maxLegalCapacity,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'status' => 'APPROVED',
            ]);

            // Terbitkan asuransi event via G7-K15
            if ($this->insuranceBinder !== null) {
                $sumInsured = $maxLegalCapacity * 500_000; // Asumsi Rp 500.000 per pax
                $cover = $this->insuranceBinder->bind(
                    eventId: (int) $event->id,
                    sumInsuredMinor: $sumInsured,
                    attendanceCap: $maxLegalCapacity,
                    from: $validFrom,
                    to: $validUntil,
                    idempotencyKey: "ven:ins_bind:{$permitNumber}"
                );

                $premium = $cover['premium_minor'];
                if ($premium > 0) {
                    $ledger = $this->ledger ?? app(Ledger::class);
                    $ledger->post(new PostingDTO(
                        type: VenueTransactionTypes::VENUE_EVENT_INSURANCE->value,
                        description: "Premi asuransi pembatalan event {$event->event_code}",
                        idempotencyKey: "ven:ins_prem:{$permitNumber}",
                        entries: Posting::lines()
                            ->debit('ven:event_insurance_expense:IDR', $premium)
                            ->credit('ven:organizer_payable:IDR', $premium)
                            ->toEntries('IDR'),
                        referenceType: 'VENUE_PERMIT',
                        referenceId: (string) $permit->id
                    ));
                }
            }

            return $permit;
        });
    }
}

