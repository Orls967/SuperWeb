<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\ForecastOverride;
use Modules\Core\Contracts\ApprovalEngineInterface;
use RuntimeException;

/**
 * X53-4 Forecast Override Governance Service.
 *
 * Enforces strict validation, integer basis-point deviation calculation,
 * ApprovalEngine integration for large deviations (>= 2000 bps / 20%),
 * and full revert/audit trail support in `sct_forecast_overrides`.
 */
final class ForecastOverrideService
{
    public const LARGE_DEVIATION_THRESHOLD_BPS = 2000;

    public const MIN_REASON_LENGTH = 5;

    public const STATUS_APPLIED = 'applied';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVERTED = 'reverted';

    public function __construct(
        private readonly ?ApprovalEngineInterface $approvalEngine = null,
    ) {}

    public function recordOverride(
        DemandForecast $forecast,
        int|string|null $newQty,
        string $reason,
        ?User $actor = null,
        ?int $actorUserId = null,
    ): ForecastOverride {
        $parsedQty = $this->validateNewQty($newQty);
        $trimmedReason = $this->validateReason($reason);

        $resolvedActor = $actor ?? ($actorUserId !== null ? User::query()->find($actorUserId) : null);
        $resolvedActorId = $resolvedActor?->id ?? $actorUserId;

        return DB::transaction(function () use ($forecast, $parsedQty, $trimmedReason, $resolvedActor, $resolvedActorId): ForecastOverride {
            /** @var DemandForecast $lockedForecast */
            $lockedForecast = DemandForecast::query()->whereKey($forecast->id)->lockForUpdate()->firstOrFail();

            $baselineQty = (int) $lockedForecast->forecast_qty;
            $deltaQty = $parsedQty - $baselineQty;
            $deviationBps = $this->calculateDeviationBps($baselineQty, $parsedQty);
            $requiresApproval = $deviationBps >= self::LARGE_DEVIATION_THRESHOLD_BPS;

            $status = $requiresApproval ? self::STATUS_PENDING_APPROVAL : self::STATUS_APPLIED;

            $override = ForecastOverride::query()->create([
                'demand_forecast_id' => $lockedForecast->id,
                'previous_qty' => $baselineQty,
                'new_qty' => $parsedQty,
                'reason' => $trimmedReason,
                'overridden_by_user_id' => $resolvedActorId,
                'status' => $status,
                'deviation_bps' => $deviationBps,
                'approval_id' => null,
            ]);

            if ($requiresApproval) {
                if ($this->approvalEngine !== null && $resolvedActor !== null) {
                    $approval = $this->approvalEngine->submit(
                        approvalType: 'CONTROL_TOWER_FORECAST_OVERRIDE',
                        title: "Override Forecast {$lockedForecast->item_code} ({$lockedForecast->period}): {$baselineQty} -> {$parsedQty}",
                        creator: $resolvedActor,
                        approvable: $override,
                        amount: abs($deltaQty),
                        steps: [['role' => 'logistics_admin']],
                        slaHours: 48,
                        metadata: [
                            'demand_forecast_id' => $lockedForecast->id,
                            'forecast_override_id' => $override->id,
                            'item_code' => $lockedForecast->item_code,
                            'period' => $lockedForecast->period,
                            'baseline_qty' => $baselineQty,
                            'new_qty' => $parsedQty,
                            'delta_qty' => $deltaQty,
                            'deviation_bps' => $deviationBps,
                        ],
                    );

                    $override->update(['approval_id' => $approval->id]);
                }

                return $override->fresh();
            }

            $lockedForecast->update([
                'forecast_qty' => $parsedQty,
                'is_overridden' => true,
                'override_reason' => $trimmedReason,
            ]);

            return $override->fresh();
        });
    }

    public function approveOverride(ForecastOverride $override, User $approver, ?string $comments = null): ForecastOverride
    {
        return DB::transaction(function () use ($override, $approver, $comments): ForecastOverride {
            /** @var ForecastOverride $lockedOverride */
            $lockedOverride = ForecastOverride::query()->whereKey($override->id)->lockForUpdate()->firstOrFail();

            if ($lockedOverride->status !== self::STATUS_PENDING_APPROVAL) {
                throw new RuntimeException("INVALID_OVERRIDE_STATE: Override berstatus '{$lockedOverride->status}' dan tidak dapat disetujui.");
            }

            if ($lockedOverride->approval_id !== null && $this->approvalEngine !== null) {
                $this->approvalEngine->approve((int) $lockedOverride->approval_id, $approver, $comments);
            } elseif ($lockedOverride->overridden_by_user_id !== null && (int) $lockedOverride->overridden_by_user_id === $approver->id) {
                throw new RuntimeException('FOUR_EYES_VIOLATION: Penyetuju tidak boleh orang yang sama dengan pembuat override.');
            }

            /** @var DemandForecast $lockedForecast */
            $lockedForecast = DemandForecast::query()->whereKey($lockedOverride->demand_forecast_id)->lockForUpdate()->firstOrFail();
            $lockedForecast->update([
                'forecast_qty' => (int) $lockedOverride->new_qty,
                'is_overridden' => true,
                'override_reason' => $lockedOverride->reason,
            ]);

            $lockedOverride->update([
                'status' => self::STATUS_APPLIED,
            ]);

            return $lockedOverride->fresh();
        });
    }

    public function rejectOverride(ForecastOverride $override, User $approver, string $reason): ForecastOverride
    {
        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new InvalidArgumentException('REJECT_REASON_REQUIRED: Alasan penolakan override wajib diisi.');
        }

        return DB::transaction(function () use ($override, $approver, $trimmedReason): ForecastOverride {
            /** @var ForecastOverride $lockedOverride */
            $lockedOverride = ForecastOverride::query()->whereKey($override->id)->lockForUpdate()->firstOrFail();

            if ($lockedOverride->status !== self::STATUS_PENDING_APPROVAL) {
                throw new RuntimeException("INVALID_OVERRIDE_STATE: Override berstatus '{$lockedOverride->status}' dan tidak dapat ditolak.");
            }

            if ($lockedOverride->approval_id !== null && $this->approvalEngine !== null) {
                $this->approvalEngine->reject((int) $lockedOverride->approval_id, $approver, $trimmedReason);
            } elseif ($lockedOverride->overridden_by_user_id !== null && (int) $lockedOverride->overridden_by_user_id === $approver->id) {
                throw new RuntimeException('FOUR_EYES_VIOLATION: Penolak tidak boleh orang yang sama dengan pembuat override.');
            }

            $lockedOverride->update([
                'status' => self::STATUS_REJECTED,
            ]);

            return $lockedOverride->fresh();
        });
    }

    public function revertOverride(ForecastOverride $override, ?User $actor = null, ?int $actorUserId = null): ForecastOverride
    {
        $resolvedActorId = $actor?->id ?? $actorUserId;

        return DB::transaction(function () use ($override, $resolvedActorId): ForecastOverride {
            /** @var ForecastOverride $lockedOverride */
            $lockedOverride = ForecastOverride::query()->whereKey($override->id)->lockForUpdate()->firstOrFail();

            if ($lockedOverride->status !== self::STATUS_APPLIED) {
                throw new RuntimeException("INVALID_OVERRIDE_REVERT: Hanya override berstatus 'applied' yang dapat di-revert (status saat ini: '{$lockedOverride->status}').");
            }

            /** @var DemandForecast $lockedForecast */
            $lockedForecast = DemandForecast::query()->whereKey($lockedOverride->demand_forecast_id)->lockForUpdate()->firstOrFail();

            $previousApplied = ForecastOverride::query()
                ->where('demand_forecast_id', $lockedForecast->id)
                ->where('id', '<', $lockedOverride->id)
                ->where('status', self::STATUS_APPLIED)
                ->latest('id')
                ->first();

            $lockedForecast->update([
                'forecast_qty' => (int) $lockedOverride->previous_qty,
                'is_overridden' => $previousApplied !== null,
                'override_reason' => $previousApplied?->reason,
            ]);

            $lockedOverride->update([
                'status' => self::STATUS_REVERTED,
                'reverted_at' => now(),
                'reverted_by_user_id' => $resolvedActorId,
            ]);

            return $lockedOverride->fresh();
        });
    }

    private function validateNewQty(int|string|null $newQty): int
    {
        if (is_int($newQty)) {
            if ($newQty < 0) {
                throw new InvalidArgumentException('NEGATIVE_FORECAST_QTY: Kuantitas override tidak boleh negatif.');
            }

            return $newQty;
        }

        if ($newQty === null || trim($newQty) === '' || ! preg_match('/^-?\d+$/', trim($newQty))) {
            throw new InvalidArgumentException('INVALID_FORECAST_QTY: Kuantitas override wajib berupa integer non-negatif.');
        }

        $parsed = (int) trim($newQty);
        if ($parsed < 0) {
            throw new InvalidArgumentException('NEGATIVE_FORECAST_QTY: Kuantitas override tidak boleh negatif.');
        }

        return $parsed;
    }

    private function validateReason(string $reason): string
    {
        $trimmed = trim($reason);
        if ($trimmed === '' || mb_strlen($trimmed) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('OVERRIDE_REASON_REQUIRED: Alasan override wajib diisi minimal 5 karakter.');
        }

        return $trimmed;
    }

    private function calculateDeviationBps(int $baselineQty, int $newQty): int
    {
        $absDelta = abs($newQty - $baselineQty);
        if ($baselineQty <= 0) {
            return $newQty > 0 ? 10_000 : 0;
        }

        return intdiv($absDelta * 10_000, $baselineQty);
    }
}
