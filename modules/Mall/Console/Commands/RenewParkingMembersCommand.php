<?php

declare(strict_types=1);

namespace Modules\Mall\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mall\Application\Actions\RenewParkingMembershipAction;

class RenewParkingMembersCommand extends Command
{
    protected $signature = 'mall:renew-parking-members';

    protected $description
        = 'Kirim pengingat H-3 dan proses perpanjangan otomatis langganan parkir bulanan yang jatuh tempo';

    public function handle(RenewParkingMembershipAction $action): int
    {
        $reminders = $action->sendExpiryReminders();
        $this->line("  Pengingat H-3 terkirim: {$reminders} keanggotaan");

        $result = $action->processDue();

        $this->table(
            ['Hasil Proses Jatuh Tempo', 'Jumlah'],
            [
                ['Diperpanjang otomatis', $result['renewed']],
                ['Kedaluwarsa (saldo kurang / auto-renew mati)', $result['expired']],
            ]
        );

        $this->info('✓ Proses perpanjangan langganan parkir selesai.');

        return self::SUCCESS;
    }
}
