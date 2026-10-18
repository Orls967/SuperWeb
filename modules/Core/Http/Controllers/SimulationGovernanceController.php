<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Services\SimTickRegistry;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Domain\Models\SimEventSpine;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimSeederCheckpoint;
use Modules\Core\Domain\Models\SimTwinState;

class SimulationGovernanceController extends Controller
{
    public function index(SimClockInterface $simClock): View
    {
        $virtualNow = $simClock->now();
        $isSimulating = $simClock->isSimulating();
        $activeRun = $simClock->activeRun() ?? SimRun::where('status', 'running')->latest()->first();

        $recentRuns = SimRun::latest()->take(10)->get();
        $spineEvents = SimEventSpine::latest()->take(15)->get();
        $twinStates = SimTwinState::latest()->take(15)->get();
        $checkpoints = SimSeederCheckpoint::latest()->take(10)->get();

        $stats = [
            'total_runs' => SimRun::count(),
            'total_spine_events' => SimEventSpine::count(),
            'total_twin_states' => SimTwinState::count(),
            'total_checkpoints' => SimSeederCheckpoint::count(),
        ];

        return view('core::simulation.index', compact(
            'virtualNow',
            'isSimulating',
            'activeRun',
            'recentRuns',
            'spineEvents',
            'twinStates',
            'checkpoints',
            'stats'
        ));
    }

    public function advance(
        Request $request,
        SimClockInterface $simClock,
        SimTickRegistry $tickRegistry
    ): RedirectResponse {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'run_name' => ['nullable', 'string', 'max:64'],
        ]);

        $days = (int) $validated['days'];
        $runName = $validated['run_name'] ?? 'web_sim_'.now()->format('Ymd_His');

        if (! $simClock->activeRun()) {
            $simClock->initRun($runName, Carbon::now());
        }

        $activeRun = $simClock->activeRun();
        $executedReports = [];

        for ($i = 1; $i <= $days; $i++) {
            $newNow = $simClock->advanceDays(1);
            $dayNumber = ($activeRun ? $activeRun->last_completed_day : 0) + 1;
            $report = $tickRegistry->executeDay($newNow, $dayNumber);
            $executedReports[] = $report;

            if ($activeRun) {
                $activeRun->last_completed_day = $dayNumber;
                $activeRun->virtual_now = $newNow;
                $activeRun->last_state_hash = hash('sha256', "day_{$dayNumber}|{$newNow->toIso8601String()}");
                $activeRun->save();
            }
        }

        return redirect()->route('admin.simulation.index')
            ->with('success', "Simulasi berhasil dimajukan sebanyak {$days} hari ke {$simClock->now()->toIso8601String()}. Handler harian dieksekusi secara deterministik.");
    }

    public function publishSpine(
        Request $request,
        EventSpineInterface $eventSpine
    ): RedirectResponse {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:128'],
            'event_name' => ['required', 'string', 'max:128'],
            'payload' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $payload = [];
        if (! empty($validated['payload'])) {
            $decoded = json_decode($validated['payload'], true);
            $payload = is_array($decoded) ? $decoded : ['raw' => $validated['payload']];
        }

        $idempotencyKey = $validated['idempotency_key'] ?? ('spine_web_'.hash('sha256', $validated['topic'].'|'.$validated['event_name'].'|'.json_encode($payload)));

        $event = $eventSpine->publish(
            $validated['topic'],
            $validated['event_name'],
            $payload,
            $idempotencyKey
        );

        return redirect()->route('admin.simulation.index')
            ->with('success', "Event '{$event->event_name}' berhasil diterbitkan ke topik '{$event->topic}' (Offset ID: {$event->id}, Key: {$event->idempotency_key}).");
    }

    public function recordTwin(
        Request $request,
        DigitalTwinInterface $twinService
    ): RedirectResponse {
        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'max:64'],
            'entity_id' => ['required', 'string', 'max:64'],
            'state' => ['required', 'string'],
            'is_sandbox' => ['nullable', 'boolean'],
        ]);

        $state = json_decode($validated['state'], true);
        if (! is_array($state)) {
            $state = ['raw_state' => $validated['state']];
        }

        $isSandbox = (bool) ($validated['is_sandbox'] ?? false);

        $twin = $twinService->recordState(
            $validated['entity_type'],
            $validated['entity_id'],
            $state,
            $isSandbox
        );

        return redirect()->route('admin.simulation.index')
            ->with('success', "State Digital Twin untuk '{$twin->entity_type}:{$twin->entity_id}' berhasil dicatat (Hash: ".substr($twin->state_hash, 0, 16).'...). Hash chain valid.');
    }

    public function scaleProvision(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seeder_name' => ['required', 'string', 'max:100'],
            'stage' => ['required', 'string', 'max:100'],
            'batch_number' => ['nullable', 'integer', 'min:1'],
            'completed' => ['nullable', 'boolean'],
        ]);

        $checkpoint = SimSeederCheckpoint::updateOrCreate(
            [
                'seeder_name' => $validated['seeder_name'],
                'stage' => $validated['stage'],
            ],
            [
                'batch_number' => $validated['batch_number'] ?? 1,
                'completed' => (bool) ($validated['completed'] ?? true),
                'metrics' => [
                    'provisioned_by' => 'admin_governance_ui',
                    'timestamp' => now()->toIso8601String(),
                ],
            ]
        );

        return redirect()->route('admin.simulation.index')
            ->with('success', "Checkpoint seeder '{$checkpoint->seeder_name}' stage '{$checkpoint->stage}' berhasil disimpan (Batch: {$checkpoint->batch_number}, Completed: ".($checkpoint->completed ? 'Yes' : 'No').').');
    }
}
