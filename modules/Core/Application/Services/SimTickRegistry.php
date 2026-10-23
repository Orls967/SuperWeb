<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Carbon\CarbonInterface;
use Modules\Core\Application\TickHandlers\BillingTickHandler;
use Modules\Core\Application\TickHandlers\DemurrageTickHandler;
use Modules\Core\Application\TickHandlers\DepreciationTickHandler;
use Modules\Core\Application\TickHandlers\FranchiseRoyaltyTickHandler;
use Modules\Core\Application\TickHandlers\InstallmentTickHandler;
use Modules\Core\Application\TickHandlers\LoyaltyExpiryTickHandler;
use Modules\Core\Application\TickHandlers\MrpTickHandler;
use Modules\Core\Contracts\SimTickHandlerInterface;

class SimTickRegistry
{
    /**
     * @var array<string, SimTickHandlerInterface>
     */
    protected array $handlers = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    protected function registerDefaults(): void
    {
        $this->register(new DepreciationTickHandler);
        $this->register(new InstallmentTickHandler);
        $this->register(new BillingTickHandler);
        $this->register(new LoyaltyExpiryTickHandler);
        $this->register(new MrpTickHandler);
        $this->register(new DemurrageTickHandler);
        $this->register(new FranchiseRoyaltyTickHandler);
    }

    public function register(SimTickHandlerInterface $handler): void
    {
        $this->handlers[$handler->name()] = $handler;
    }

    /**
     * @return array<string, SimTickHandlerInterface>
     */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * Execute all handlers eligible for the current virtual day.
     *
     * @param  (callable(string, array<string, mixed>): int)|null  $commandRunner
     * @return array<string, mixed>
     */
    public function executeDay(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array
    {
        $results = [];
        foreach ($this->handlers as $name => $handler) {
            if ($handler->shouldRun($virtualNow, $dayNumber)) {
                $results[$name] = $handler->handle($virtualNow, $dayNumber, $commandRunner);
            }
        }

        return $results;
    }
}
