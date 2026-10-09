<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Domain\Models\SimRun;

class SimClockService implements SimClockInterface
{
    protected ?SimRun $activeRun = null;

    protected ?CarbonInterface $currentVirtualNow = null;

    public function now(): CarbonInterface
    {
        if ($this->currentVirtualNow !== null) {
            return $this->currentVirtualNow->copy();
        }

        if ($this->activeRun !== null) {
            return Carbon::parse($this->activeRun->virtual_now);
        }

        return Carbon::now();
    }

    public function isSimulating(): bool
    {
        return $this->activeRun !== null && $this->activeRun->status === 'running';
    }

    public function initRun(string $name, CarbonInterface $startTime, int $seed = 42): SimRun
    {
        return DB::transaction(function () use ($name, $startTime, $seed) {
            $run = SimRun::firstOrNew(['name' => $name]);
            $run->sim_start_at = $startTime;
            $run->virtual_now = $startTime;
            $run->seed = $seed;
            $run->status = 'running';
            $run->save();

            $this->activeRun = $run;
            $this->currentVirtualNow = $startTime->copy();

            Carbon::setTestNow($this->currentVirtualNow);

            return $run;
        });
    }

    public function advanceSeconds(int $seconds): CarbonInterface
    {
        if (! $this->activeRun) {
            throw new \RuntimeException('No active simulation run to advance.');
        }

        $this->currentVirtualNow = $this->now()->addSeconds($seconds);

        $this->activeRun->virtual_now = $this->currentVirtualNow;
        $this->activeRun->save();

        Carbon::setTestNow($this->currentVirtualNow);

        return $this->currentVirtualNow->copy();
    }

    public function advanceDays(int $days): CarbonInterface
    {
        return $this->advanceSeconds($days * 86400);
    }

    public function reset(): void
    {
        if ($this->activeRun) {
            $this->activeRun->status = 'idle';
            $this->activeRun->save();
        }

        $this->activeRun = null;
        $this->currentVirtualNow = null;
        Carbon::setTestNow(null);
    }

    public function activeRun(): ?SimRun
    {
        return $this->activeRun;
    }
}
