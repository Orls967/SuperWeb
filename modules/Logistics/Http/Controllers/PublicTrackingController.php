<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
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

        $shipment = Shipment::with(['origin', 'destination', 'packages', 'driver.user'])
            ->where('tracking_number', $cleanedTracking)
            ->first();

        if (! $shipment) {
            return view('logistics::tracking.not_found', [
                'trackingNumber' => $cleanedTracking,
            ]);
        }

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
