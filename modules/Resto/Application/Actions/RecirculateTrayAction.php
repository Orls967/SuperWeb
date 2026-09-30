<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

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
        if (! $tray->canRecirculate()) {
            // Discard the tray and record waste
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

            throw new InvalidTrayOperationException(
                sprintf(
                    'Piring tidak dapat dikembalikan ke etalase: batas resirkulasi (maksimal %d kali) atau batas waktu etalase (%d jam) telah terlampaui. Piring dialihkan ke waste.',
                    DisplayTray::MAX_RECIRCULATION,
                    DisplayTray::MAX_DISPLAY_HOURS
                )
            );
        }

        $tray->recirculation_count++;
        $tray->status = TrayStatus::ON_DISPLAY;
        $tray->save();

        return $tray;
    }
}
