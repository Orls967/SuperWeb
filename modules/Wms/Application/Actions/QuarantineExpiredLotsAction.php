<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Application\Services\LotQuarantineService;

/**
 * Memindai bin stock yang telah melewati tanggal kedaluwarsa & memindahkannya ke karantina otomatis.
 */
final class QuarantineExpiredLotsAction
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly LotQuarantineService $quarantineService,
    ) {}

    /**
     * @return array{scanned_count: int, quarantined_ids: list<int>}
     */
    public function execute(): array
    {
        $today = $this->clock->now()->toDateString();
        $expiredRows = DB::table('wms_bin_stocks')
            ->where('stock_status', 'available')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->where('qty_base', '>', 0)
            ->pluck('id')
            ->all();

        $quarantined = [];
        foreach ($expiredRows as $id) {
            $this->quarantineService->quarantineBinStock((int) $id, "Expired lot auto-quarantine ({$today})");
            $quarantined[] = (int) $id;
        }

        return [
            'scanned_count' => count($expiredRows),
            'quarantined_ids' => $quarantined,
        ];
    }
}
