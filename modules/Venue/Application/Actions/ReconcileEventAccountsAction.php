<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Venue\Application\Services\EventPostMortemFinancialReconciliationService;
use Modules\Venue\Domain\Models\EntertainmentEvent;

final class ReconcileEventAccountsAction
{
    public function __construct(
        private readonly EventPostMortemFinancialReconciliationService $reconciliationService
    ) {}

    /**
     * @return array{
     *     event_code: string,
     *     ticket_settlement: array<string, int>,
     *     table_settlement: array<string, int>,
     *     tax_compliance: array<string, int>,
     *     net_promoter_payout_idr: int,
     *     reconciliation_status: string
     * }
     */
    public function execute(int $eventId): array
    {
        /** @var EntertainmentEvent $event */
        $event = EntertainmentEvent::findOrFail($eventId);

        return $this->reconciliationService->reconcileEventAccounts($event);
    }
}
