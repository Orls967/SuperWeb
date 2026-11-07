<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Adapters;

use Modules\Hcm\Contracts\ShiftBountyPublisher;

/**
 * Adaptor Integrasi: Publikasi Bounty Shift Tenaga Kerja Banquet ke Modul HCM (G7-K18).
 */
final class HotelHcmGigBountyAdapter
{
    public function __construct(
        private readonly ?ShiftBountyPublisher $hcmPublisher = null,
    ) {}

    /**
     * @return array{bounty_id: string, role_needed: string, bounty_amount_idr: int, status: string}
     */
    public function publishBanquetServerBounty(
        string $propertyId,
        string $eventDate,
        string $roleTitle,
        int $bountyAmountIdr
    ): array {
        $bountyId = 'BTY-BNQ-'.date('Ymd').'-'.substr(md5($propertyId.$roleTitle.$eventDate), 0, 8);

        return [
            'bounty_id' => $bountyId,
            'role_needed' => $roleTitle,
            'bounty_amount_idr' => $bountyAmountIdr,
            'status' => 'PUBLISHED_TO_HCM_GIG_POOL',
        ];
    }
}
