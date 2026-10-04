<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Contract\Domain\Enums\AmendmentKind;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Models\Amendment;
use Modules\Contract\Domain\Models\Contract;

/**
 * Amandemen & addendum kontrak (29.4).
 *
 * Setiap perubahan nilai/jangka waktu:
 * 1. Menyimpan diff field lama→baru pada `ctr_amendments` (jejak audit).
 * 2. Menghasilkan versi hash-chain baru lewat `ContractService::appendVersion`
 *    sehingga riwayat kontrak tetap append-only dan dapat diverifikasi.
 * 3. Menghitung ulang jadwal termin yang belum dibayar (melalui
 *    `ContractFinanceService::buildSchedule(..., replaceUnpaid: true)`).
 *
 * Amandemen hanya boleh di atas kontrak aktif.
 */
class ContractAmendmentService
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly ContractFinanceService $financeService,
    ) {}

    /**
     * @param  array{total_value_idr?: int, end_date?: string, start_date?: string, reason?: string, kind?: AmendmentKind|string}  $changes
     */
    public function amend(Contract $contract, array $changes, ?string $author = null): Amendment
    {
        return DB::transaction(function () use ($contract, $changes, $author) {
            /** @var Contract $locked */
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->getKey());

            if ($locked->status !== ContractStatus::Active) {
                throw new InvalidArgumentException(
                    'Amandemen hanya dapat dilakukan pada kontrak berstatus Aktif.'
                );
            }

            $oldValue = (int) $locked->total_value_idr;
            $newValue = $changes['total_value_idr'] ?? $oldValue;
            $oldEnd = $locked->end_date;
            $newEnd = isset($changes['end_date']) ? Carbon::parse($changes['end_date']) : $oldEnd;

            if ((int) $newValue < 0) {
                throw new InvalidArgumentException('Nilai kontrak tidak boleh negatif.');
            }

            if ($oldValue === (int) $newValue && $oldEnd?->toDateString() === $newEnd?->toDateString()) {
                throw new InvalidArgumentException('Tidak ada perubahan nilai/jangka waktu untuk diamandemen.');
            }

            // Simpan diff field (jejak lengkap).
            $diff = [];
            if ($oldValue !== (int) $newValue) {
                $diff['total_value_idr'] = ['old' => $oldValue, 'new' => (int) $newValue];
            }
            if ($oldEnd?->toDateString() !== $newEnd?->toDateString()) {
                $diff['end_date'] = [
                    'old' => $oldEnd?->toDateString(),
                    'new' => $newEnd?->toDateString(),
                ];
            }

            // 1. Tulis versi hash-chain baru (append-only).
            $body = $locked->current_body ?? '';
            $amendmentNote = "\n\n## Amandemen ".now()->format('Y-m-d')."\n"
                .'Nilai: Rp '.number_format($oldValue).' → Rp '.number_format((int) $newValue)
                .(($oldEnd?->toDateString() !== $newEnd?->toDateString())
                    ? '; jangka waktu berakhir: '.$oldEnd?->toDateString().' → '.$newEnd?->toDateString()
                    : '')
                .(($changes['reason'] ?? null) !== null ? "\nAlasan: {$changes['reason']}" : '');

            $version = $this->contractService->appendVersion(
                contract: $locked,
                newBody: $body.$amendmentNote,
                changeType: 'amendment',
                metadata: ['amendment' => $diff],
                author: $author ?? 'Legal Officer',
            );

            // 2. Terapkan perubahan.
            $locked->total_value_idr = (int) $newValue;
            if ($newEnd !== null) {
                $locked->end_date = $newEnd;
            }
            if (isset($changes['start_date'])) {
                $locked->start_date = Carbon::parse($changes['start_date']);
            }
            $locked->save();

            // 3. Hitung ulang jadwal termin yang belum dibayar.
            $recalculated = false;
            if ($oldValue !== (int) $newValue || $oldEnd?->toDateString() !== $newEnd?->toDateString()) {
                $this->financeService->buildSchedule($locked, [], true);
                $recalculated = true;
            }

            $kind = isset($changes['kind'])
                ? ($changes['kind'] instanceof AmendmentKind ? $changes['kind'] : AmendmentKind::from((string) $changes['kind']))
                : AmendmentKind::Amendment;

            return Amendment::create([
                'contract_id' => $locked->id,
                'contract_version_id' => $version->id,
                'kind' => $kind,
                'changes' => $diff,
                'old_value_idr' => $oldValue,
                'new_value_idr' => (int) $newValue,
                'old_end_date' => $oldEnd,
                'new_end_date' => $newEnd,
                'effective_date' => now(),
                'schedule_recalculated' => $recalculated,
                'reason' => $changes['reason'] ?? null,
                'created_by_name' => $author,
            ]);
        });
    }
}
