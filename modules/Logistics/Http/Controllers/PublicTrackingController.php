<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Services\PiiMasker;

class PublicTrackingController extends Controller
{
    public function __construct(
        private readonly PiiMasker $piiMasker
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $trackingNumber = trim((string) ($request->get('q') ?? $request->get('tracking_number')));

        if (! empty($trackingNumber)) {
            return redirect()->route('track.show', ['tracking_number' => strtoupper($trackingNumber)]);
        }

        return view('logistics::tracking.index');
    }

    public function track(Request $request, string $tracking_number): View
    {
        $cleanedTracking = strtoupper(trim($tracking_number));

        $shipment = Shipment::leftJoin('lgx_locations as origin_loc', 'origin_loc.id', '=', 'lgx_shipments.origin_location_id')
            ->leftJoin('lgx_locations as dest_loc', 'dest_loc.id', '=', 'lgx_shipments.destination_location_id')
            ->select([
                'lgx_shipments.*',
                'origin_loc.name as _origin_name',
                'origin_loc.city as _origin_city',
                'dest_loc.name as _dest_name',
                'dest_loc.city as _dest_city',
            ])
            ->with([
                'trackingEvents' => fn ($q) => $q->with('location')->orderByDesc('sequence'),
            ])
            ->withCount('packages')
            ->where('lgx_shipments.tracking_number', $cleanedTracking)
            ->first();

        if (! $shipment) {
            return view('logistics::tracking.not_found', [
                'trackingNumber' => $cleanedTracking,
            ]);
        }

        $origin = new Location([
            'name' => $shipment->_origin_name,
            'city' => $shipment->_origin_city,
        ]);
        $origin->exists = true;

        $dest = new Location([
            'name' => $shipment->_dest_name,
            'city' => $shipment->_dest_city,
        ]);
        $dest->exists = true;

        $shipment->setRelation('origin', $origin);
        $shipment->setRelation('destination', $dest);
        $shipment->setRelation('packages', collect(range(1, max(1, (int) $shipment->packages_count))));

        $maskedConsignee = [
            'name' => $this->piiMasker->maskName($shipment->consignee_name),
            'phone' => $this->piiMasker->maskPhone($shipment->consignee_phone),
            'address' => $this->piiMasker->maskAddress($shipment->consignee_address),
        ];

        $events = $shipment->getTimelineEvents();

        return view('logistics::tracking.show', [
            'shipment' => $shipment,
            'maskedConsignee' => $maskedConsignee,
            'events' => $events,
            'trackingNumber' => $cleanedTracking,
        ]);
    }
}
