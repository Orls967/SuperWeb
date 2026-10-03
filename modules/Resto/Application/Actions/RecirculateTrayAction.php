<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Exceptions\InvalidTrayOperationException;
use Modules\Resto\Domain\Models\DisplayTray;

class RecirculateTrayAction
{
    public function __construct(
        private readonly DiscardTrayAction $discardAction
    ) {}

    /**
     * @throws InvalidTrayOperationException
     */
    public function handle(DisplayTray $tray, ?int $userId = null): DisplayTray
    {
        $rejected = DB::transaction(function () use ($tray, $userId): bool {
            // Take the row lock, then re-read onto the caller's model so the eligibility
            // check runs on fresh values: two concurrent calls cannot both push the tray
            // past MAX_RECIRCULATION.
            DisplayTray::where('id', $tray->id)->lockForUpdate()->firstOrFail();
            $tray->refresh();

            if ($tray->canRecirculate()) {
                $tray->recirculation_count++;
                $tray->status = TrayStatus::ON_DISPLAY;
                $tray->save();

                return false;
            }

            // Discard the tray and record waste. Done inside the transaction but *without*
            // throwing here: the throw must not roll the discard back.
            $this->discardAction->handle(
                tray: $tray,
                reason: sprintf(
                    'Batas resirkulasi (%d/%d) atau batas pajang etalase (%d jam) terlampaui',
                    $tray->recirculation_count,
                    DisplayTray::MAX_RECIRCULATION,
                    DisplayTray::MAX_DISPLAY_HOURS
                ),
                userId: $userId
            );

            return true;
        });

        if ($rejected) {
            throw new InvalidTrayOperationException(
                sprintf(
                    'Piring tidak dapat dikembalikan ke etalase: batas resirkulasi (maksimal %d kali) atau batas waktu etalase (%d jam) telah terlampaui. Piring dialihkan ke waste.',
                    DisplayTray::MAX_RECIRCULATION,
                    DisplayTray::MAX_DISPLAY_HOURS
                )
            );
        }

        return $tray;
    }
}
