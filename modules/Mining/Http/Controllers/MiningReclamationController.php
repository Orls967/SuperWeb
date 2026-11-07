<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Mining\Application\Actions\ScheduleMineReclamationAction;
use Modules\Mining\Application\Actions\UnwindReclamationProvisionAction;
use Modules\Mining\Domain\Models\MiningEnvironmentalReading;
use Modules\Mining\Domain\Models\MiningReclamationSchedule;

class MiningReclamationController extends Controller
{
    public function index()
    {
        $schedules = MiningReclamationSchedule::latest()->take(10)->get();
        $readings = MiningEnvironmentalReading::latest()->take(10)->get();

        return view('mining::reclamation', [
            'schedules' => $schedules,
            'readings' => $readings,
        ]);
    }

    /**
     * Jadwalkan paket reklamasi & revegetasi lahan bekas tambang (D94-6).
     */
    public function schedule(Request $request, ScheduleMineReclamationAction $action)
    {
        $validated = $request->validate([
            'site_id' => 'required|uuid',
            'area_name' => 'required|string|max:100',
            'target_hectares' => 'required|numeric|min:0.1',
            'planned_year' => 'required|integer|min:2025',
            'planned_quarter' => 'required|integer|between:1,4',
            'estimated_cost_idr' => 'required|integer|min:1000000',
        ]);

        $schedule = $action->execute($validated);

        return redirect()->back()->with('success', "Jadwal reklamasi area {$schedule->area_name} berhasil dibuat.");
    }

    /**
     * Eksekusi unwinding beban provisi diskonto reklamasi ke buku besar (D94-6, D94-9).
     */
    public function unwind(Request $request, UnwindReclamationProvisionAction $action)
    {
        $validated = $request->validate([
            'site_id' => 'required|uuid',
            'year' => 'required|integer|min:2025',
            'quarter' => 'required|integer|between:1,4',
            'discount_rate_bps' => 'nullable|integer|min:100',
        ]);

        $result = $action->execute($validated);

        return redirect()->back()->with('success', "Unwinding provisi reklamasi periode {$result['period']} selesai diposting: Rp ".number_format($result['unwind_expense_idr']));
    }
}
