<?php

declare(strict_types=1);

namespace Modules\Venue\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Contracts\ClockInterface;
use Modules\Venue\Application\Actions\CloseEntertainmentEventAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;

class CloseEventsCommand extends Command
{
    protected $signature = 'ven:close-events';

    protected $description = 'Menutup semua event venue yang jadwalnya telah lewat secara otomatis dan mengakui pendapatan (V-7)';

    public function handle(CloseEntertainmentEventAction $action, ClockInterface $clock): int
    {
        $pastEvents = EntertainmentEvent::where('doors_open_at', '<', $clock->now())
            ->where('status', '!=', 'CLOSED')
            ->get();

        $this->info("Found {$pastEvents->count()} past events to close.");

        foreach ($pastEvents as $event) {
            $action->handle(
                eventId: $event->id,
                platformFeeBps: 500,
                idempotencyKey: "ven:auto-close:{$event->id}"
            );

            $this->line("Event {$event->event_code} successfully closed.");
        }

        $this->info('All eligible events closed.');

        return self::SUCCESS;
    }
}
