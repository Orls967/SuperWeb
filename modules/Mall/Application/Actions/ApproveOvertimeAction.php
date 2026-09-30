<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Modules\Mall\Domain\Enums\OvertimeStatus;
use Modules\Mall\Domain\Models\OvertimeRequest;

class ApproveOvertimeAction
{
    public function execute(OvertimeRequest $request, int $approvedBy): OvertimeRequest
    {
        $request->update([
            'status' => OvertimeStatus::APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $request;
    }
}
