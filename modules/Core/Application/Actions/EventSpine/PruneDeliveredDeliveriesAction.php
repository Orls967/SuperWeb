<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\EventSpine;

use Carbon\Carbon;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\StateMachines\EventDeliveryStateMachine;

/**
 * Action PruneDeliveredDeliveriesAction
 *
 * Membersihkan rekaman pengiriman event yang telah berstatus delivered
 * dan melewati batas retensi hari untuk menjaga efisiensi penyimpanan basis data.
 */
class PruneDeliveredDeliveriesAction
{
    /**
     * @return int Jumlah baris yang berhasil dipangkas
     */
    public function execute(int $retentionDays = 14): int
    {
        $threshold = Carbon::now()->subDays($retentionDays);

        return CoreEventDelivery::where('status', EventDeliveryStateMachine::STATUS_DELIVERED)
            ->where('delivered_at', '<', $threshold)
            ->delete();
    }
}

