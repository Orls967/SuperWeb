<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use Exception;
use Modules\AutoServe\Domain\Enums\EstimateStatus;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\Shared\Application\BaseAction;

/**
 * Mekanik mengirimkan estimasi ke customer untuk disetujui.
 */
class SendEstimateAction extends BaseAction
{
    public function execute(Estimate $estimate): Estimate
    {
        return $this->transaction(function () use ($estimate) {
            /** @var Estimate $estimate */
            $estimate = Estimate::query()->lockForUpdate()->findOrFail($estimate->id);

            if ($estimate->status !== EstimateStatus::Draft) {
                throw new Exception("Estimasi berstatus {$estimate->status->label()} tidak dapat dikirim ulang.");
            }

            if ($estimate->total <= 0) {
                throw new Exception('Estimasi dengan total nol tidak dapat dikirim ke customer.');
            }

            $estimate->transitionTo(EstimateStatus::Sent);

            // Jangan menimpa sent_at pada double-submit.
            if ($estimate->sent_at === null) {
                $estimate->update(['sent_at' => now()]);
            }

            return $estimate->fresh();
        });
    }
}
