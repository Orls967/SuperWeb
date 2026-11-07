<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Venue\Application\Services\TaxPbjtReconciliationService;
use Modules\Venue\Domain\Models\EntertainmentEvent;

final class ReconcileTaxPbjtAction
{
    public function __construct(
        private readonly TaxPbjtReconciliationService $taxService
    ) {}

    /**
     * @return array{
     *     event_code: string,
     *     ticket_gross_idr: int,
     *     ticket_pbjt_tax_idr: int,
     *     table_gross_idr: int,
     *     table_pbjt_tax_idr: int,
     *     total_taxable_base_idr: int,
     *     total_pbjt_payable_idr: int,
     *     is_reconciled: bool
     * }
     */
    public function execute(int $eventId): array
    {
        /** @var EntertainmentEvent $event */
        $event = EntertainmentEvent::findOrFail($eventId);

        return $this->taxService->reconcileEventPbjt($event);
    }
}
