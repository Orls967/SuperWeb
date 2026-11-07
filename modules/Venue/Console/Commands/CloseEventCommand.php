<?php

declare(strict_types=1);

namespace Modules\Venue\Console\Commands;

use Illuminate\Console\Command;
use Modules\Venue\Application\Actions\CloseEntertainmentEventAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;

class CloseEventCommand extends Command
{
    protected $signature = 'venue:close-event {event : ID atau Kode Event yang akan ditutup}';

    protected $description = 'Menutup event venue secara idempoten dan mengakui pendapatan dari akun unearned ke revenue (Fase 89)';

    public function handle(CloseEntertainmentEventAction $action): int
    {
        $eventArg = $this->argument('event');

        $event = EntertainmentEvent::where('id', $eventArg)
            ->orWhere('event_code', $eventArg)
            ->first();

        if (! $event) {
            $this->error("Event '{$eventArg}' tidak ditemukan.");

            return self::FAILURE;
        }

        $this->info("Memproses penutupan event: {$event->event_code} - {$event->title}...");

        $action->execute($event);

        $this->info("Event {$event->event_code} berhasil ditutup. Pengakuan pendapatan selesai.");

        return self::SUCCESS;
    }
}

