<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HtlExhibitionBoothReservation;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Services\HotelExhibitionTradeShowConnectorService;

final class ReserveExhibitionTradeShowBoothAction
{
    public function __construct(
        private readonly HotelExhibitionTradeShowConnectorService $connectorService
    ) {}

    public function execute(
        MiceContract $contract,
        string $boothNumber,
        string $boothType,
        int $gridX,
        int $gridY,
        int $widthM = 3,
        int $lengthM = 3,
        float $electricityKva = 0.5,
        bool $isDedicatedLan = false,
        int $baseRentalIdr = 15000000
    ): HtlExhibitionBoothReservation {
        return $this->connectorService->reserveBooth(
            contract: $contract,
            boothNumber: $boothNumber,
            boothType: $boothType,
            gridX: $gridX,
            gridY: $gridY,
            widthM: $widthM,
            lengthM: $lengthM,
            electricityKva: $electricityKva,
            isDedicatedLan: $isDedicatedLan,
            baseRentalIdr: $baseRentalIdr
        );
    }
}
