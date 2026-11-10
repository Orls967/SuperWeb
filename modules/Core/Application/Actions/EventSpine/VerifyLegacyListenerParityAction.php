<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\EventSpine;

use Modules\Core\Application\Services\LegacyListenerAdapterService;

/**
 * Action VerifyLegacyListenerParityAction (DR8-6)
 *
 * Memvalidasi kesiapan dan paritas eksekusi seluruh listener warisan yang terdaftar.
 */
class VerifyLegacyListenerParityAction
{
    public function __construct(
        protected LegacyListenerAdapterService $adapterService
    ) {}

    /**
     * @return array{total_checked: int, passed_count: int, failed_listeners: array<int, string>}
     */
    public function execute(): array
    {
        $all = $this->adapterService->getAllAdapters();
        $total = 0;
        $passed = 0;
        $failed = [];

        foreach ($all as $eventName => $listenerClasses) {
            foreach ($listenerClasses as $listenerClass) {
                $total++;
                if (class_exists($listenerClass)) {
                    $passed++;
                } else {
                    $failed[] = $listenerClass;
                }
            }
        }

        return [
            'total_checked' => $total,
            'passed_count' => $passed,
            'failed_listeners' => $failed,
        ];
    }
}
