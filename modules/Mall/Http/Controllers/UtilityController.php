<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mall\Application\Actions\RecordUtilityReadingAction;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\UtilityType;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\UtilityReading;
use Modules\Mall\Domain\Models\UtilityTariff;

class UtilityController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->query('month', date('Y-m'));
        $propertyId = $request->filled('property_id') ? (int) $request->query('property_id') : null;

        $properties = Property::where('is_active', true)->get();
        if ($propertyId === null && $properties->isNotEmpty()) {
            $propertyId = $properties->first()->id;
        }

        $leases = Lease::with(['tenant', 'unit.zone'])
            ->where('status', LeaseStatus::ACTIVE)
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->orderBy('unit_id', 'asc')
            ->get();

        $existingReadings = UtilityReading::where('period_month', $period)
            ->whereIn('lease_id', $leases->pluck('id'))
            ->get()
            ->groupBy(['lease_id', fn ($item) => $item->utility_type->value]);

        $tariffs = UtilityTariff::where('property_id', $propertyId)
            ->orderBy('utility_type', 'asc')
            ->orderBy('tier_number', 'asc')
            ->get();

        return view('mall::utilities.index', [
            'period' => $period,
            'propertyId' => $propertyId,
            'properties' => $properties,
            'leases' => $leases,
            'existingReadings' => $existingReadings,
            'tariffs' => $tariffs,
        ]);
    }

    public function storeBatch(Request $request, RecordUtilityReadingAction $action): RedirectResponse
    {
        $period = $request->input('period_month', date('Y-m'));
        $readingsData = $request->input('readings', []);

        $recordedCount = 0;

        foreach ($readingsData as $leaseId => $types) {
            $lease = Lease::find((int) $leaseId);
            if (! $lease) {
                continue;
            }

            foreach ([UtilityType::ELECTRICITY, UtilityType::WATER] as $type) {
                $typeKey = $type->value;
                if (! isset($types[$typeKey]) || ! isset($types[$typeKey]['current_meter']) || $types[$typeKey]['current_meter'] === '') {
                    continue;
                }

                $currentMeter = (float) $types[$typeKey]['current_meter'];
                $previousMeter = isset($types[$typeKey]['previous_meter']) && $types[$typeKey]['previous_meter'] !== ''
                    ? (float) $types[$typeKey]['previous_meter']
                    : null;

                $action->execute(
                    lease: $lease,
                    periodMonth: $period,
                    type: $type,
                    currentMeter: $currentMeter,
                    previousMeter: $previousMeter,
                    recordedBy: auth()->id()
                );

                $recordedCount++;
            }
        }

        return redirect()->route('mall.utilities.index', [
            'month' => $period,
            'property_id' => $request->input('property_id'),
        ])->with('success', "Berhasil mencatat {$recordedCount} data pembacaan meteran utilitas untuk periode {$period}.");
    }
}
