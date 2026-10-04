<?php

declare(strict_types=1);

namespace Modules\Contract\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Enums\MilestoneStatus;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractMilestone;
use Modules\Core\Application\Services\NotificationService;
use Modules\Core\Contracts\OutboxBusInterface;

class RemindContractObligationsCommand extends Command
{
    protected $signature = 'ctr:remind {--days=30 : Alert milestones and contract expirations within this many days}';

    protected $description = 'Send automatic alerts for expiring contracts, notice periods, and upcoming obligations (in-app + outbox)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $this->info("Memeriksa pengingat kontrak dan obligasi jatuh tempo dalam {$days} hari...");

        $inApp = 0;
        $outbox = 0;

        // 1. Kontrak aktif akan berakhir — reminder + event notice/expiration.
        $expiring = Contract::query()
            ->where('status', ContractStatus::Active->value)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', now()->addDays($days)->toDateString())
            ->get();

        foreach ($expiring as $contract) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($contract->end_date->startOfDay(), false);
            $this->warn("  [KONTRAK KEDALUWARSA] {$contract->contract_number} ({$contract->title}) berakhir pada {$contract->end_date->format('Y-m-d')} ({$daysLeft} hari lagi)");

            $inApp += $this->notifyAssignees(
                $contract,
                'contract_expiring',
                'Kontrak akan berakhir',
                "{$contract->contract_number} — {$contract->title} berakhir dalam {$daysLeft} hari."
            );

            $outbox += $this->recordOutbox(
                'contract.expiring',
                ['contract_number' => $contract->contract_number, 'end_date' => $contract->end_date->toDateString(), 'days_left' => $daysLeft],
                "contract_expiring:{$contract->id}:{$daysLeft}"
            );
        }

        // 2. Notice period (end_date - notice_period_days <= horizon) — wajib terekam
        //    meski masa berakhirnya masih jauh.
        $noticeDue = Contract::query()
            ->where('status', ContractStatus::Active->value)
            ->whereNotNull('end_date')
            ->whereNotNull('notice_period_days')
            ->whereDate('end_date', '<=', now()->addDays($days + 365)->toDateString())
            ->get()
            ->filter(fn (Contract $c) => $c->end_date->copy()->subDays($c->notice_period_days)->lte(now()->addDays($days)))
            ->values();

        foreach ($noticeDue as $contract) {
            $noticeDate = $contract->end_date->copy()->subDays($contract->notice_period_days);
            $this->line("  [NOTICE PERIOD] {$contract->contract_number}: jendela notice mulai {$noticeDate->format('Y-m-d')} (auto-renew: ".($contract->auto_renew ? 'ya' : 'tidak').')');

            $inApp += $this->notifyAssignees(
                $contract,
                'contract_notice_period',
                'Notice period kontrak dimulai',
                "{$contract->contract_number} — notice {$contract->notice_period_days} hari, berakhir {$contract->end_date->format('Y-m-d')}."
            );

            $outbox += $this->recordOutbox(
                'contract.notice_period',
                ['contract_number' => $contract->contract_number, 'notice_date' => $noticeDate->toDateString(), 'end_date' => $contract->end_date->toDateString()],
                "contract_notice:{$contract->id}:{$noticeDate->toDateString()}"
            );
        }

        // 3. Milestone / obligasi mendekat jatuh tempo.
        $milestones = ContractMilestone::query()
            ->with('contract')
            ->where('status', MilestoneStatus::Pending->value)
            ->where('reminder_sent', false)
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('due_date')
            ->get();

        foreach ($milestones as $ms) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($ms->due_date->startOfDay(), false);
            $this->line("  [OBLIGASI / MILESTONE] {$ms->contract?->contract_number}: {$ms->title} jatuh tempo {$ms->due_date->format('Y-m-d')} ({$daysLeft} hari)");

            $inApp += $this->notifyAssignees(
                $ms->contract,
                'contract_milestone_upcoming',
                'Obligasi kontrak mendekat jatuh tempo',
                "{$ms->contract?->contract_number} — {$ms->title} jatuh tempo {$ms->due_date->format('Y-m-d')}."
            );

            $outbox += $this->recordOutbox(
                'contract.milestone_upcoming',
                ['contract_number' => $ms->contract?->contract_number, 'milestone' => $ms->title, 'due_date' => $ms->due_date->toDateString(), 'days_left' => $daysLeft],
                "milestone_reminder:{$ms->id}:{$ms->due_date->toDateString()}"
            );

            $ms->update(['reminder_sent' => true]);
        }

        $this->info("Pengingat selesai: {$inApp} notifikasi in-app, {$outbox} event outbox.");

        return self::SUCCESS;
    }

    /**
     * Kirim notifikasi in-app ke pembuat & penanggung jawab kontrak.
     * Idempoten: key `ctr:remind` sama per kontrak+jenis tidak diulang di observasi kita
     * (listener/notification menahan duplikat lewat outbox idempotency).
     *
     * @return int jumlah notifikasi terkirim
     */
    private function notifyAssignees(?Contract $contract, string $type, string $title, string $body): int
    {
        if ($contract === null) {
            return 0;
        }

        $service = app(NotificationService::class);
        $sent = 0;

        $userIds = collect([$contract->created_by])
            ->filter()
            ->merge(User::query()->get()->filter(
                fn (User $user): bool => $user->hasAnyRbacRole(['contract_manager', 'legal'])
            )->pluck('id'))
            ->unique();

        foreach ($userIds as $userId) {
            $service->send(
                userId: (int) $userId,
                type: $type,
                title: $title,
                body: $body,
                icon: 'document-text',
                actionUrl: route('contract.show', $contract),
                actionLabel: 'Lihat kontrak',
                meta: ['contract_id' => $contract->id, 'contract_number' => $contract->contract_number],
            );
            $sent++;
        }

        return $sent;
    }

    /**
     * Rekam event ke outbox transaksional (diproses dispatcher idempoten).
     * Idempotency key deterministik supaya retry cron tidak menggandakan event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordOutbox(string $eventType, array $payload, string $idempotencyKey): int
    {
        if (! app()->bound(OutboxBusInterface::class)) {
            return 0;
        }

        try {
            app(OutboxBusInterface::class)->record(
                eventType: $eventType,
                payload: $payload,
                idempotencyKey: $idempotencyKey,
                headers: ['source' => 'contract'],
            );

            return 1;
        } catch (\Throwable) {
            return 0;
        }
    }
}
