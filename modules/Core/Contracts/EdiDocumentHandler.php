<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Contract implemented by owner modules receiving parsed EDI documents (ORDERS, INVOIC, DESADV).
 * Owner modules NEVER post ledger entries from inbound EDI directly unless their domain workflow requires explicit AP settlement later.
 */
interface EdiDocumentHandler
{
    /**
     * Unique module owner name (e.g. 'Distribution', 'Procurement', 'Wms').
     */
    public function moduleName(): string;

    /**
     * Whether this handler accepts the given canonical EDI message type (ORDERS, 850, INVOIC, DESADV).
     */
    public function supports(string $messageType): bool;

    /**
     * Apply the canonical EDI document idempotently using the provided idempotency key.
     */
    public function handle(CanonicalEdiDocument $document, string $idempotencyKey): EdiHandleResult;
}

