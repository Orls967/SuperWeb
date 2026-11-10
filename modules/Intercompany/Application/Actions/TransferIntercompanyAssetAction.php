<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Models\IntercompanyAssetTransfer;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Psr\Clock\ClockInterface;

class TransferIntercompanyAssetAction
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null
    ) {}

    /**
     * @param array{
     *     asset_code: string,
     *     source_entity: string,
     *     target_entity: string,
     *     historical_cost_idr: int,
     *     accumulated_depreciation_idr: int,
     *     transfer_price_idr: int,
     *     transfer_code?: string
     * } $data
     */
    public function execute(array $data): IntercompanyAssetTransfer
    {
        $source = $data['source_entity'];
        $target = $data['target_entity'];

        if ($source === $target) {
            throw new InvalidArgumentException('Pengalihan aset tidak boleh dalam entitas yang sama.');
        }

        $sourceEntity = IntercompanyEntity::where('entity_code', $source)->first();
        $targetEntity = IntercompanyEntity::where('entity_code', $target)->first();

        $nbv = $data['historical_cost_idr'] - $data['accumulated_depreciation_idr'];
        $gainLoss = $data['transfer_price_idr'] - $nbv;

        $now = $this->clock !== null ? $this->clock->now() : new \DateTimeImmutable;
        $code = $data['transfer_code'] ?? 'AST-TRF-'.$now->format('Ymd').'-'.strtoupper(substr(hash('sha256', "{$data['asset_code']}:{$source}:{$target}"), 0, 8));

        return IntercompanyAssetTransfer::create([
            'transfer_code' => $code,
            'asset_code' => $data['asset_code'],
            'source_entity_id' => $sourceEntity?->id,
            'target_entity_id' => $targetEntity?->id,
            'net_book_value_idr' => $nbv,
            'accumulated_depreciation_idr' => $data['accumulated_depreciation_idr'],
            'transfer_price_idr' => $data['transfer_price_idr'],
            'gain_loss_idr' => $gainLoss,
            'status' => 'transferred',
        ]);
    }
}
