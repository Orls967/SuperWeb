<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Diagnostics;

use Modules\Hospital\Application\Services\InterBranchLabClearingService;
use Modules\Hospital\Domain\Models\InterBranchClearing;
use RuntimeException;

class SettleInterBranchClearingAction
{
    public function __construct(
        protected InterBranchLabClearingService $clearingService
    ) {}

    public function execute(int $clearingId): InterBranchClearing
    {
        /** @var InterBranchClearing|null $clearing */
        $clearing = InterBranchClearing::find($clearingId);

        if (! $clearing) {
            throw new RuntimeException("Kewajiban kliring ID [{$clearingId}] tidak ditemukan.");
        }

        return $this->clearingService->settleClearing($clearing);
    }
}
