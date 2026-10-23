<?php

declare(strict_types=1);

namespace Modules\Trade\Application\Actions;

use InvalidArgumentException;
use Modules\Trade\Domain\Models\ExportOrder;
use Modules\Trade\Domain\Models\ImportOrder;

class SubmitCustomsPibPebDeclarationAction
{
    /**
     * Submit formal customs declaration: PIB for Import or PEB for Export.
     *
     * @return array{declaration_number: string, type: string, status: string}
     */
    public function execute(
        ImportOrder|ExportOrder $order,
        string $customsOfficeCode = '040300' // Tanjung Priok
    ): array {
        if ($order instanceof ImportOrder) {
            $pibNumber = sprintf('PIB-%s-%s-%s', $customsOfficeCode, date('Ymd'), strtoupper(substr(md5($order->id), 0, 6)));
            $order->update([
                'pib_number' => $pibNumber,
                'status' => 'customs_cleared',
            ]);

            return [
                'declaration_number' => $pibNumber,
                'type' => 'PIB_IMPORT',
                'status' => 'customs_cleared',
            ];
        }

        if ($order instanceof ExportOrder) {
            $pebNumber = sprintf('PEB-%s-%s-%s', $customsOfficeCode, date('Ymd'), strtoupper(substr(md5($order->id), 0, 6)));
            $order->update([
                'peb_number' => $pebNumber,
                'status' => 'shipped',
                'risk_transferred_at' => now(),
            ]);

            return [
                'declaration_number' => $pebNumber,
                'type' => 'PEB_EXPORT',
                'status' => 'shipped',
            ];
        }

        throw new InvalidArgumentException('Tipe order tidak didukung untuk deklarasi kepabeanan.');
    }
}
