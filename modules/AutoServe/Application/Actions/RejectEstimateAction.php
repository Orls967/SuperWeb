<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use App\Models\User;
use Exception;
use Modules\AutoServe\Domain\Enums\EstimateStatus;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\Shared\Application\BaseAction;

/**
 * Customer menolak estimasi: tidak ada dana yang ditahan dan mekanik dapat
 * menyusun estimasi baru.
 */
class RejectEstimateAction extends BaseAction
{
    public function execute(Estimate $estimate, User $customer, string $reason = ''): Estimate
    {
        return $this->transaction(function () use ($estimate, $customer, $reason) {
            /** @var Estimate $estimate */
            $estimate = Estimate::query()->lockForUpdate()->findOrFail($estimate->id);
            $estimate->loadMissing('booking');

            if ($estimate->booking === null) {
                throw new Exception('Estimasi tidak terhubung dengan booking manapun.');
            }

            if ((int) $estimate->booking->customer_id !== (int) $customer->id && ! $customer->isAdmin()) {
                throw new Exception('Hanya pemilik booking yang dapat menolak estimasi ini.');
            }

            if ($estimate->status !== EstimateStatus::Sent) {
                throw new Exception("Estimasi berstatus {$estimate->status->label()} tidak dapat ditolak.");
            }

            $estimate->transitionTo(EstimateStatus::Rejected);
            $estimate->update([
                'rejected_at' => now(),
                'rejection_reason' => $reason !== '' ? $reason : 'Ditolak customer tanpa keterangan',
            ]);

            return $estimate->fresh();
        });
    }
}
