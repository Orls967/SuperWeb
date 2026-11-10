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
use Modules\Core\Domain\Algorithms\Simulation\SubscriberTopologicalOrder;

class SimTickRegistry
{
    /**
     * @var array<string, SimTickHandlerInterface>
     */
    protected array $handlers = [];

    /**
     * @var list<array{from: string, to: string}>
     */
    protected array $dependencies = [];

    public function __construct(
        protected SubscriberTopologicalOrder $topoOrder = new SubscriberTopologicalOrder,
    ) {
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

        // Standar dependensi logis default
        $this->addDependency('depresiasi', 'tagihan');
        $this->addDependency('cicilan', 'tagihan');
    }

    /**
     * Memuat tick handlers domain aditif yang ditag dalam container service provider.
     */
    public function loadTaggedHandlers(): void
    {
        if (function_exists('app') && app()->has('events')) {
            try {
                foreach (app()->tagged('sim.tick_handler') as $taggedHandler) {
                    if ($taggedHandler instanceof SimTickHandlerInterface) {
                        $this->register($taggedHandler);
                    }
                }
            } catch (\Throwable) {
                // Ignore if container is not ready
            }
        }
    }

    public function register(SimTickHandlerInterface $handler): void
    {
        $this->handlers[$handler->name()] = $handler;

        if (method_exists($handler, 'dependencies')) {
            /** @var list<string> $deps */
            $deps = (array) $handler->dependencies();
            foreach ($deps as $dep) {
                $this->addDependency($dep, $handler->name());
            }
        }
    }

    public function addDependency(string $beforeHandler, string $afterHandler): void
    {
        $this->dependencies[] = [
            'from' => $beforeHandler,
            'to' => $afterHandler,
        ];
    }

    /**
     * @return array<string, SimTickHandlerInterface>
     */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * @return list<array{from: string, to: string}>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Menyelesaikan urutan eksekusi seluruh tick handler berdasarkan algoritma Kahn A2.
     *
     * @return list<SimTickHandlerInterface>
     */
    public function resolveExecutionOrder(): array
    {
        $nodeNames = array_keys($this->handlers);
        if (empty($nodeNames)) {
            return [];
        }

        // Filter dependensi yang hanya melibatkan simpul terdaftar
        $validEdges = [];
        foreach ($this->dependencies as $edge) {
            if (isset($this->handlers[$edge['from']]) && isset($this->handlers[$edge['to']])) {
                $validEdges[] = $edge;
            }
        }

        $orderedNames = $this->topoOrder->sort($nodeNames, $validEdges);

        $orderedHandlers = [];
        foreach ($orderedNames as $name) {
            if (isset($this->handlers[$name])) {
                $orderedHandlers[] = $this->handlers[$name];
            }
        }

        return $orderedHandlers;
    }

    /**
     * Mendapatkan daftar handler berurutan topologis dengan memuat tagged handlers bila tersedia.
     *
     * @return list<SimTickHandlerInterface>
     */
    public function getTopologicallyOrderedHandlers(): array
    {
        $this->loadTaggedHandlers();

        return $this->resolveExecutionOrder();
    }

    /**
     * Execute all handlers eligible for the current virtual day in topological order.
     *
     * @param  (callable(string, array<string, mixed>): int)|null  $commandRunner
     * @return array<string, mixed>
     */
    public function executeDay(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array
    {
        $this->loadTaggedHandlers();
        $orderedHandlers = $this->resolveExecutionOrder();
        $results = [];

        foreach ($orderedHandlers as $handler) {
            $name = $handler->name();
            if ($handler->shouldRun($virtualNow, $dayNumber)) {
                $results[$name] = $handler->handle($virtualNow, $dayNumber, $commandRunner);
            }
        }

        return $results;
    }
}
