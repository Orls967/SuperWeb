<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Shared\Application\BaseAction;
use Modules\Venue\Application\Services\RandomInspectionAuditService;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\VenueBagInspection;

class RecordRandomBagInspectionAction extends BaseAction
{
    public function __construct(
        private readonly RandomInspectionAuditService $inspectionAuditService
    ) {}

    public function execute(
        EntertainmentEvent $event,
        string $gateCode,
        string $inspectorBadge,
        ?EntertainmentTicket $ticket = null,
        ?string $contrabandType = null,
        bool $policeReferred = false
    ): VenueBagInspection {
        return $this->inspectionAuditService->recordInspection(
            event: $event,
            gateCode: $gateCode,
            inspectorBadge: $inspectorBadge,
            ticket: $ticket,
            contrabandFound: $contrabandType,
            policeReferred: $policeReferred
        );
    }
}
