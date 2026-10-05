<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Manufacturing\Domain\Models\HseIncident;
use Modules\Manufacturing\Domain\Models\WorkPermit;

/**
 * K3/HSE (40.6): insiden & near-miss dengan investigasi/tindakan,
 * izin kerja berisiko (hot work, confined space) via approval + masa berlaku.
 */
class HseService
{
    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ApprovalEngineInterface $approvals,
    ) {}

    public const PERMIT_TYPES = ['hot_work', 'confined_space'];

    /**
     * Laporkan insiden/near-miss.
     *
     * @param  array{work_center_id?: string, worker_id?: int, kind?: string, severity?: string,
     *   description?: string, occurred_at?: string, due_date?: string}  $data
     */
    public function report(array $data, User $reporter): HseIncident
    {
        $number = $this->numbering->nextNumber('MFG', 'HSE', false, 'HSE/{ENT}/');

        return HseIncident::create([
            'number' => $number,
            'work_center_id' => $data['work_center_id'] ?? null,
            'worker_id' => $data['worker_id'] ?? null,
            'kind' => $data['kind'] ?? 'near_miss',
            'severity' => $data['severity'] ?? 'low',
            'status' => 'reported',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? now()->addDays(14)->toDateString(),
            'occurred_at' => $data['occurred_at'] ?? now(),
            'reported_by_user_id' => $reporter->id,
        ]);
    }

    /** Catat hasil investigasi → status action. */
    public function investigate(HseIncident $incident, string $investigation, string $correctiveAction): HseIncident
    {
        return DB::transaction(function () use ($incident, $investigation, $correctiveAction) {
            /** @var HseIncident $locked */
            $locked = HseIncident::query()->lockForUpdate()->findOrFail($incident->getKey());
            if ($locked->status === 'closed') {
                return $locked;
            }

            $locked->update([
                'status' => 'action',
                'investigation' => $investigation,
                'corrective_action' => $correctiveAction,
            ]);

            return $locked;
        });
    }

    /** Tindakan korektif selesai → tutup insiden. */
    public function close(HseIncident $incident): HseIncident
    {
        return DB::transaction(function () use ($incident) {
            /** @var HseIncident $locked */
            $locked = HseIncident::query()->lockForUpdate()->findOrFail($incident->getKey());
            if ($locked->status === 'closed') {
                return $locked; // idempoten
            }
            if ($locked->status === 'reported') {
                throw new InvalidArgumentException('Insiden harus diinvestigasi sebelum ditutup.');
            }

            $locked->update(['status' => 'closed', 'closed_at' => now()]);

            return $locked;
        });
    }

    // ── Izin kerja berisiko ──────────────────────────────────────────────

    /**
     * Ajukan izin kerja berisiko; approval four-eyes menyusul.
     *
     * @param  array{permit_type: string, hazards?: string, controls?: string,
     *   valid_from?: string, valid_until: string}  $data
     */
    public function requestPermit(string $workCenterId, array $data, User $requester): WorkPermit
    {
        if (! in_array($data['permit_type'], self::PERMIT_TYPES, true)) {
            throw new InvalidArgumentException('Jenis izin harus hot_work atau confined_space.');
        }

        return DB::transaction(function () use ($workCenterId, $data, $requester) {
            $number = $this->numbering->nextNumber('MFG', 'PTW', false, 'PTW/{ENT}/');
            $validFrom = isset($data['valid_from']) ? now()->parse($data['valid_from']) : now();
            $validUntil = now()->parse($data['valid_until']);
            if ($validUntil->lessThanOrEqualTo($validFrom)) {
                throw new InvalidArgumentException('Masa berlaku izin harus setelah waktu mulai.');
            }

            $permit = WorkPermit::create([
                'number' => $number,
                'work_center_id' => $workCenterId,
                'permit_type' => $data['permit_type'],
                'status' => 'pending',
                'hazards' => $data['hazards'] ?? null,
                'controls' => $data['controls'] ?? null,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'requested_by_user_id' => $requester->id,
            ]);

            $approval = $this->approvals->submit(
                approvalType: 'MFG_WORK_PERMIT',
                title: "Izin kerja {$permit->permit_type} {$permit->number}",
                creator: $requester,
                approvable: $permit,
                steps: [['role' => 'admin']],
                slaHours: 24,
                metadata: ['permit_id' => $permit->id, 'type' => $permit->permit_type],
            );

            $permit->update(['approval_id' => (int) $approval->id]);

            return $permit;
        });
    }

    /** Setujui izin (empat mata) → status active, berlaku pada jendela waktu. */
    public function approvePermit(WorkPermit $permit, User $approver): WorkPermit
    {
        return DB::transaction(function () use ($permit, $approver) {
            /** @var WorkPermit $locked */
            $locked = WorkPermit::query()->lockForUpdate()->findOrFail($permit->getKey());
            if ($locked->status === 'active') {
                return $locked;
            }
            if ($locked->status !== 'pending' || $locked->approval_id === null) {
                throw new InvalidArgumentException("Izin {$locked->number} tidak dalam antrian approval ({$locked->status}).");
            }

            $this->approvals->approve((int) $locked->approval_id, $approver, 'Izin kerja disetujui');

            $locked->update(['status' => 'active', 'approved_at' => now()]);

            return $locked;
        });
    }

    /** Masa berlaku lewat → expired; WO terkait tidak bisa dijalankan tanpa perpanjangan. */
    public function expirePermits(): int
    {
        return WorkPermit::where('status', 'active')
            ->where('valid_until', '<', now())
            ->update(['status' => 'expired']);
    }

    /** Validasi izin aktif untuk pekerjaan berisiko (dipakai operator/UI). */
    public function assertPermitValid(WorkPermit $permit): void
    {
        if (! $permit->isActiveAt()) {
            throw new InvalidArgumentException(
                "Izin {$permit->number} tidak berlaku (status {$permit->status}, berlaku s/d {$permit->valid_until})."
            );
        }
    }
}
