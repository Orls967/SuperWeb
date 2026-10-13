<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Enums\DisposalMethod;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetDisposal;
use Modules\Asset\Domain\Models\AssetRevaluation;
use Modules\Core\Contracts\ApprovalEngineInterface;

/**
 * 31.3 Impairment & revaluasi, 31.4 Disposal (approval four-eyes via ApprovalEngine).
 *
 * - Revaluasi/impairment menaikkan nilai buku ke nilai wajar; selisih negatif
 *   dicatat sebagai beban, positif sebagai surplus (ekuitas) — jurnal dibuat
 *   oleh caller ledger setelah approval; di sini fokus alur & approval.
 * - Disposal menghitung laba/rugi = proceeds − book value dan menyetel status
 *   aset ke `disposed` hanya setelah approval.
 */
class RevaluationService
{
    public function __construct(
        private readonly ApprovalEngineInterface $approvals,
        private readonly AssetService $assets,
    ) {}

    /**
     * Ajukan revaluasi/impairment aset (41: approval sebelum posting).
     *
     * @return array{status: string, revaluation: AssetRevaluation, approval?: object}
     */
    public function request(Asset $asset, string $kind, int $newValueIdr, string $reason, User $creator): array
    {
        if (! in_array($kind, ['revaluation', 'impairment'], true)) {
            throw new InvalidArgumentException('Jenis harus revaluation atau impairment.');
        }

        if ($newValueIdr < 0) {
            throw new InvalidArgumentException('Nilai wajar tidak boleh negatif.');
        }

        $oldValue = (int) $asset->book_value_idr;

        return DB::transaction(function () use ($asset, $kind, $newValueIdr, $reason, $creator, $oldValue) {
            $approval = $this->approvals->submit(
                approvalType: $kind === 'impairment' ? 'ASSET_IMPAIRMENT' : 'ASSET_REVALUATION',
                title: ucfirst($kind)." Aset {$asset->asset_number} (".number_format($oldValue).' → '.number_format($newValueIdr).')',
                creator: $creator,
                approvable: $asset,
                amount: (float) abs($newValueIdr - $oldValue),
                steps: [['role' => 'asset_manager'], ['role' => 'admin']],
                slaHours: 72,
                metadata: ['asset_number' => $asset->asset_number, 'old_value' => $oldValue, 'new_value' => $newValueIdr],
            );

            $record = AssetRevaluation::create([
                'asset_id' => $asset->id,
                'kind' => $kind,
                'old_value_idr' => $oldValue,
                'new_value_idr' => $newValueIdr,
                'difference_idr' => $newValueIdr - $oldValue,
                'reason' => $reason,
                'approval_id' => $approval->id,
                'approval_status' => 'pending',
                'requested_by_user_id' => $creator->id,
            ]);

            return ['status' => 'pending', 'revaluation' => $record, 'approval' => $approval];
        });
    }

    /**
     * Terapkan revaluasi setelah disetujui: perbarui nilai buku + event rantai.
     */
    public function apply(AssetRevaluation $record): AssetRevaluation
    {
        return DB::transaction(function () use ($record) {
            /** @var AssetRevaluation $locked */
            $locked = AssetRevaluation::query()->lockForUpdate()->findOrFail($record->getKey());

            if ($locked->approval_status !== 'pending') {
                return $locked;
            }

            /** @var Asset $asset */
            $asset = Asset::query()->lockForUpdate()->findOrFail($locked->asset_id);

            $asset->book_value_idr = (int) $locked->new_value_idr;
            $asset->save();

            $locked->approval_status = 'approved';
            $locked->save();

            $this->assets->recordEvent($asset, AssetEventType::Revaluation, [
                'kind' => $locked->kind,
                'old_value_idr' => $locked->old_value_idr,
                'new_value_idr' => $locked->new_value_idr,
                'difference_idr' => $locked->difference_idr,
                'reason' => $locked->reason,
            ], 'approval');

            return $locked->fresh();
        });
    }

    /** Tolak revaluasi. */
    public function reject(AssetRevaluation $record): AssetRevaluation
    {
        $record->approval_status = 'rejected';
        $record->save();

        return $record;
    }

    /**
     * Ajukan disposal (31.4): jual/hapus/hibah/hilang + laba-rugi pelepasan.
     *
     * @return array{status: string, disposal: AssetDisposal, approval?: object}
     */
    public function requestDisposal(Asset $asset, DisposalMethod $method, int $proceedsIdr, ?string $reason, User $creator): array
    {
        if ($proceedsIdr < 0) {
            throw new InvalidArgumentException('Hasil pelepasan tidak boleh negatif.');
        }

        $bookValue = (int) $asset->book_value_idr;
        $gainLoss = $proceedsIdr - $bookValue;

        return DB::transaction(function () use ($asset, $method, $proceedsIdr, $reason, $creator, $bookValue, $gainLoss) {
            $approval = $this->approvals->submit(
                approvalType: 'ASSET_DISPOSAL',
                title: "Disposal Aset {$asset->asset_number} ({$method->label()}) — laba/rugi ".number_format($gainLoss),
                creator: $creator,
                approvable: $asset,
                amount: (float) $proceedsIdr,
                steps: [['role' => 'asset_manager'], ['role' => 'admin']],
                slaHours: 72,
                metadata: [
                    'asset_number' => $asset->asset_number,
                    'method' => $method->value,
                    'proceeds_idr' => $proceedsIdr,
                    'book_value_idr' => $bookValue,
                    'gain_loss_idr' => $gainLoss,
                ],
            );

            $disposal = AssetDisposal::create([
                'asset_id' => $asset->id,
                'method' => $method->value,
                'proceeds_idr' => $proceedsIdr,
                'book_value_at_disposal_idr' => $bookValue,
                'gain_loss_idr' => $gainLoss,
                'reason' => $reason,
                'approval_id' => $approval->id,
                'approval_status' => 'pending',
                'requested_by_user_id' => $creator->id,
            ]);

            return ['status' => 'pending', 'disposal' => $disposal, 'approval' => $approval];
        });
    }

    /**
     * Eksekusi disposal setelah approval: status aset → disposed + event rantai.
     *
     * @param  array{source_type?: string, source_id?: int}  $link
     */
    public function finalizeDisposal(AssetDisposal $disposal, array $link = []): AssetDisposal
    {
        return DB::transaction(function () use ($disposal, $link) {
            /** @var AssetDisposal $locked */
            $locked = AssetDisposal::query()->lockForUpdate()->findOrFail($disposal->getKey());

            if ($locked->approval_status !== 'pending') {
                return $locked;
            }

            /** @var Asset $asset */
            $asset = Asset::query()->lockForUpdate()->findOrFail($locked->asset_id);

            $asset->status = AssetStatus::Disposed;
            $asset->save();

            $locked->approval_status = 'approved';
            $locked->disposed_at = now();
            if (isset($link['source_type'])) {
                $locked->source_type = $link['source_type'];
                $locked->source_id = $link['source_id'] ?? null;
            }
            $locked->save();

            $this->assets->recordEvent($asset, AssetEventType::Disposal, [
                'method' => $locked->method,
                'proceeds_idr' => $locked->proceeds_idr,
                'book_value_idr' => $locked->book_value_at_disposal_idr,
                'gain_loss_idr' => $locked->gain_loss_idr,
                'reason' => $locked->reason,
                'source_type' => $locked->source_type,
                'source_id' => $locked->source_id,
            ], 'approval');

            return $locked->fresh();
        });
    }

    /** Tolak disposal. */
    public function rejectDisposal(AssetDisposal $disposal): AssetDisposal
    {
        $disposal->approval_status = 'rejected';
        $disposal->save();

        return $disposal;
    }
}
