<?php

declare(strict_types=1);

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use InvalidArgumentException;
use Modules\Procurement\Contracts\MrpRequisitionProposer as MrpRequisitionProposerContract;

/**
 * Menerima usulan pembelian dari MRP (36.6) dan menerbitkan PR
 * melalui ProcurementService (PR tetap melewati approval).
 */
class MrpRequisitionProposer implements MrpRequisitionProposerContract
{
    public function __construct(private readonly ProcurementService $procurement) {}

    public function proposeRequisition(array $lines, User $creator, string $sourceRef): string
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Usulan PR MRP harus berisi setidaknya satu baris.');
        }

        $prLines = [];
        foreach ($lines as $line) {
            $prLines[] = [
                'description' => sprintf(
                    '%s — kebutuhan MRP %s (lead %dd, MOQ %s)',
                    $line['material_code'],
                    $sourceRef,
                    (int) $line['lead_time_days'],
                    $line['moq']
                ),
                'qty' => max(1, (int) ceil((float) $line['qty'])),
                'unit' => $line['unit'] ?? 'pcs',
                'estimated_unit_price_idr' => (int) ($line['estimated_unit_price_idr'] ?? 0),
            ];
        }

        $pr = $this->procurement->createRequisition([
            'title' => "PR usulan MRP ({$sourceRef})",
            'notes' => "Dihasilkan otomatis oleh MRP — sumber {$sourceRef}.",
            'source' => 'mrp',
            'lines' => $prLines,
        ], $creator);

        return $pr->number;
    }
}
