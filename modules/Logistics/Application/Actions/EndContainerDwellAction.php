<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Exceptions\DemurrageException;
use Modules\Logistics\Domain\Models\ContainerDwell;

class EndContainerDwellAction
{
    public function __construct(
        private readonly AccrueDemurrageDetentionAction $accrue
    ) {}

    /**
     * Tutup hitungan (gate-out untuk demurrage / pengembalian kontainer kosong untuk detention) dan akrual final.
     */
    public function execute(ContainerDwell $dwell, ?CarbonInterface $endedAt = null): ContainerDwell
    {
        return DB::transaction(function () use ($dwell, $endedAt) {
            $dwell = ContainerDwell::whereKey($dwell->id)->lockForUpdate()->firstOrFail();

            if (! $dwell->isOpen()) {
                throw DemurrageException::alreadyClosed();
            }

            $endedAt ??= now();
            if ($endedAt->lessThan($dwell->started_at)) {
                throw DemurrageException::invalidTime();
            }

            $dwell->update(['ended_at' => $endedAt, 'status' => ContainerDwell::STATUS_CLOSED]);
            $this->accrue->execute($dwell->fresh());

            return $dwell->fresh();
        });
    }
}
