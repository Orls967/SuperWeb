<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface DocumentNumberingInterface
{
    /**
     * Generate the next gapless document number under a strict database lock.
     *
     * @param  string  $entityCode  Business entity identifier (e.g. 'CORP', 'SRX', 'MALL')
     * @param  string  $documentType  Type of document (e.g. 'INV', 'PO', 'CLAIM', 'ORD')
     * @param  bool  $resetMonthly  Whether to reset number monthly or yearly
     * @param  string|null  $customPrefix  Optional prefix template (e.g. 'INV/{YYYY}/{MM}/')
     * @param  int  $padding  Zero-padding width for the sequential number (default 5 digits)
     * @return string Formatted gapless document number
     */
    public function nextNumber(
        string $entityCode,
        string $documentType,
        bool $resetMonthly = true,
        ?string $customPrefix = null,
        int $padding = 5
    ): string;
}
