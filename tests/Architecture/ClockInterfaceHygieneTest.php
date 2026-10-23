<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\SimClockService;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\SimClockInterface;
use Tests\TestCase;

class ClockInterfaceHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_clock_interface_is_bound_and_resolvable(): void
    {
        $clock = app(ClockInterface::class);
        $this->assertInstanceOf(ClockInterface::class, $clock);
        $this->assertInstanceOf(SimClockService::class, $clock);

        $simClock = app(SimClockInterface::class);
        $this->assertInstanceOf(ClockInterface::class, $simClock);
        $this->assertSame($clock, $simClock);
    }

    public function test_clock_interface_falls_back_to_wall_clock_when_not_simulating(): void
    {
        /** @var ClockInterface $clock */
        $clock = app(ClockInterface::class);

        $now = $clock->now();
        $this->assertLessThanOrEqual(5, abs(Carbon::now()->timestamp - $now->timestamp));
    }

    public function test_clock_interface_returns_virtual_time_when_simulation_is_active(): void
    {
        /** @var SimClockInterface $simClock */
        $simClock = app(SimClockInterface::class);
        /** @var ClockInterface $clock */
        $clock = app(ClockInterface::class);

        $simStart = Carbon::parse('2026-06-01 10:00:00');
        $simClock->initRun('test-clock-run', $simStart, 123);

        $this->assertEquals('2026-06-01 10:00:00', $clock->now()->toDateTimeString());

        // Advance 2 days
        $simClock->advanceDays(2);
        $this->assertEquals('2026-06-03 10:00:00', $clock->now()->toDateTimeString());

        $simClock->reset();
    }
}
