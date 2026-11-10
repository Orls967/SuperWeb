<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyStockTransfer;
use Psr\Clock\ClockInterface;

class TransferIntercompanyStockAction
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null
    ) {}

    /**
     * @param array{
     *     source_entity: string,
     *     target_entity: string,
     *     cif_value_idr: int,
     *     import_duty_idr?: int,
     *     import_vat_idr?: int,
     *     customs_peb_number?: string,
     *     customs_pib_number?: string,
     *     shipment_code?: string
     * } $data
     */
    public function execute(array $data): IntercompanyStockTransfer
    {
        $source = $data['source_entity'];
        $target = $data['target_entity'];

        if ($source === $target) {
            throw new InvalidArgumentException('Pengiriman stok antar-entitas tidak boleh dalam entitas yang sama.');
        }

        $sourceEntity = IntercompanyEntity::where('entity_code', $source)->first();
        $targetEntity = IntercompanyEntity::where('entity_code', $target)->first();

        $cif = (int) $data['cif_value_idr'];
        $duty = (int) ($data['import_duty_idr'] ?? floor($cif * 0.05));
        $vat = (int) ($data['import_vat_idr'] ?? floor(($cif + $duty) * 0.11));

        $now = $this->clock !== null ? $this->clock->now() : new \DateTimeImmutable;
        $code = $data['shipment_code'] ?? 'STK-TRF-'.$now->format('Ymd').'-'.strtoupper(substr(hash('sha256', "{$source}:{$target}:{$cif}"), 0, 8));

        return IntercompanyStockTransfer::create([
            'shipment_code' => $code,
            'source_entity_id' => $sourceEntity?->id,
            'target_entity_id' => $targetEntity?->id,
            'customs_peb_number' => $data['customs_peb_number'] ?? 'PEB-'.$now->format('Ymd').'-'.strtoupper(substr(hash('sha256', $code), 0, 6)),
            'customs_pib_number' => $data['customs_pib_number'] ?? 'PIB-'.$now->format('Ymd').'-'.strtoupper(substr(hash('sha256', $code), 6, 6)),
            'cif_value_idr' => $cif,
            'import_duty_idr' => $duty,
            'import_vat_idr' => $vat,
            'status' => 'delivered',
        ]);
    }
}
