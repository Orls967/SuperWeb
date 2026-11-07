<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Application\DTOs\AlliancePartnerTransferDTO;
use Modules\Hotel\Domain\Services\HotelPartnerPointTransferSaga;

final class TransferPointsToAlliancePartnerAction
{
    public function __construct(
        protected ?HotelPartnerPointTransferSaga $saga = null
    ) {
        $this->saga = $saga ?? app(HotelPartnerPointTransferSaga::class);
    }

    public function execute(
        string $travelPassId,
        string $partnerCode,
        string $memberPartnerAccount,
        int $pointsToTransfer,
        bool $forceFail = false
    ): AlliancePartnerTransferDTO {
        return $this->saga->execute(
            travelPassId: $travelPassId,
            partnerCode: $partnerCode,
            memberPartnerAccount: $memberPartnerAccount,
            pointsToTransfer: $pointsToTransfer,
            forceFail: $forceFail
        );
    }
}
