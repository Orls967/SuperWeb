<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Actions\EventSpine\VerifyLegacyListenerParityAction;

class SpineParityVerifyCommand extends Command
{
    protected $signature = 'spine:parity-verify';

    protected $description = 'Verify runtime parity and class existence of all legacy event listeners adapted to event spine';

    public function handle(VerifyLegacyListenerParityAction $action): int
    {
        $this->info('Verifying legacy listener parity against event spine adapters...');
        $res = $action->execute();

        $this->info("Total legacy listeners checked: {$res['total_checked']}");
        $this->info("Passed parity checks: {$res['passed_count']}");

        if (! empty($res['failed_listeners'])) {
            $this->error('The following listeners failed parity verification:');
            foreach ($res['failed_listeners'] as $f) {
                $this->error(" - {$f}");
            }

            return self::FAILURE;
        }

        $this->info('All legacy listeners passed 100% parity verification without modifying original classes.');

        return self::SUCCESS;
    }
}
