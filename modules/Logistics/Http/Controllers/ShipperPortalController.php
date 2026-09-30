<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\BookPostpaidShipmentAction;
use Modules\Logistics\Application\Actions\BookShipmentAction;
use Modules\Logistics\Application\Actions\QuoteShipmentAction;
use Modules\Logistics\Application\Jobs\ProcessBulkShipmentUploadJob;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipperAccount;

class ShipperPortalController extends Controller
{
    public function __construct(
        private readonly QuoteShipmentAction $quoteAction,
        private readonly BookShipmentAction $bookPrepaidAction,
        private readonly BookPostpaidShipmentAction $bookPostpaidAction
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        Gate::authorize('viewAny', Shipment::class);

        $query = Shipment::with(['origin', 'destination', 'packages'])
            ->latest();

        // Shippers only see their own shipments
        if (! $user->isAdmin() && ! $user->isLogisticsAdmin() && ! $user->isDispatcher() && ! $user->isHubOperator()) {
            $query->where('shipper_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('consignee_name', 'like', "%{$search}%");
            });
        }

        $shipments = $query->paginate(15)->withQueryString();

        $account = ShipperAccount::where('shipper_id', $user->id)->first();

        return view('logistics::shipper.index', compact('shipments', 'account'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Shipment::class);

        $locations = Location::orderBy('name')->get();
        $account = ShipperAccount::where('shipper_id', $request->user()->id)->where('is_active', true)->first();

        return view('logistics::shipper.create', compact('locations', 'account'));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Shipment::class);

        $request->validate([
            'origin_location_id' => 'required|exists:lgx_locations,id',
            'destination_location_id' => 'required|exists:lgx_locations,id|different:origin_location_id',
            'service_level' => 'required|string',
            'consignee_name' => 'required|string|max:255',
            'consignee_phone' => 'required|string|max:32',
            'consignee_street' => 'required|string|max:500',
            'consignee_city' => 'required|string|max:100',
            'consignee_postal_code' => 'nullable|string|max:10',
            'payment_terms' => 'required|in:prepaid,postpaid',
            'packages' => 'required|array|min:1',
            'packages.*.weight_g' => 'required|integer|min:10',
            'packages.*.length_mm' => 'required|integer|min:10',
            'packages.*.width_mm' => 'required|integer|min:10',
            'packages.*.height_mm' => 'required|integer|min:10',
            'packages.*.description' => 'required|string|max:255',
            'pin' => 'required_if:payment_terms,prepaid|nullable|string',
        ]);

        $shipper = $request->user();
        $serviceLevel = ServiceLevel::from($request->string('service_level')->value());

        // 1. Get Quote
        $quote = $this->quoteAction->execute(
            shipper: $shipper,
            originLocationId: (int) $request->input('origin_location_id'),
            destinationLocationId: (int) $request->input('destination_location_id'),
            serviceLevel: $serviceLevel,
            packages: $request->input('packages', []),
            declaredValueIdr: (int) $request->input('declared_value_idr', 0),
            insured: (bool) $request->boolean('insured'),
            codAmountIdr: (int) $request->input('cod_amount_idr', 0)
        );

        $consigneeAddress = [
            'street' => $request->input('consignee_street'),
            'city' => $request->input('consignee_city'),
            'postal_code' => $request->input('consignee_postal_code'),
        ];

        // 2. Book
        if ($request->input('payment_terms') === 'postpaid') {
            $shipment = $this->bookPostpaidAction->execute(
                shipper: $shipper,
                quote: $quote,
                consigneeName: $request->string('consignee_name')->value(),
                consigneePhone: $request->string('consignee_phone')->value(),
                consigneeAddress: $consigneeAddress
            );
        } else {
            $shipment = $this->bookPrepaidAction->execute(
                shipper: $shipper,
                quote: $quote,
                consigneeName: $request->string('consignee_name')->value(),
                consigneePhone: $request->string('consignee_phone')->value(),
                consigneeAddress: $consigneeAddress,
                pin: (string) $request->input('pin')
            );
        }

        return redirect()->route('logistics.shipments.show', $shipment->id)
            ->with('success', "Pengiriman #{$shipment->tracking_number} berhasil dipesan.");
    }

    public function show(int $id): View
    {
        $shipment = Shipment::with(['origin', 'destination', 'packages', 'driver.user', 'shipper'])
            ->findOrFail($id);

        Gate::authorize('view', $shipment);

        return view('logistics::shipper.show', compact('shipment'));
    }

    public function label(int $id): View
    {
        $shipment = Shipment::with(['origin', 'destination', 'packages', 'shipper'])
            ->findOrFail($id);

        Gate::authorize('view', $shipment);

        $renderer = new ImageRenderer(
            new RendererStyle(160, 1),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($shipment->tracking_number);

        return view('logistics::shipper.label', compact('shipment', 'qrSvg'));
    }

    public function bulkUploadForm(): View
    {
        Gate::authorize('create', Shipment::class);

        return view('logistics::shipper.bulk');
    }

    public function processBulkUpload(Request $request)
    {
        Gate::authorize('create', Shipment::class);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $batchId = (string) Str::uuid();
        $storedPath = $file->storeAs('logistics/bulk_uploads', "{$batchId}.csv", 'local');

        // Execute synchronously or dispatch
        ProcessBulkShipmentUploadJob::dispatchSync($request->user(), $storedPath, $batchId);

        $summaryFile = storage_path("app/logistics/bulk_reports/{$batchId}_summary.json");
        $summary = file_exists($summaryFile) ? json_decode(file_get_contents($summaryFile), true) : null;

        $msg = 'Upload massal selesai diproses. Sukses: '.($summary['success_count'] ?? 0).' kargo.';
        if (! empty($summary['error_count'])) {
            $msg .= " Terdapat {$summary['error_count']} baris gagal.";
        }

        return redirect()->route('logistics.shipments.bulk')
            ->with('success', $msg)
            ->with('batch_id', $batchId);
    }

    public function downloadTemplate(): Response
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_upload_kargo_massal.csv"',
        ];

        $csv = "origin_code,destination_code,service_level,consignee_name,consignee_phone,consignee_address,weight_g,length_mm,width_mm,height_mm,description\n";
        $csv .= "HUB-BDJ,HUB-BJB,regular,Ahmad Yani,081234567890,Jl. Mistar Cokrokusumo No. 12,2500,200,150,100,Suku Cadang Motor\n";
        $csv .= "HUB-BDJ,HUB-MTP,express,Siti Aminah,081987654321,Jl. Sekumpul Raya No. 45,1200,150,100,80,Bahan Kain Sasirangan\n";

        return response($csv, 200, $headers);
    }

    public function downloadErrorReport(Request $request, string $batchId): Response
    {
        $summaryFile = storage_path("app/logistics/bulk_reports/{$batchId}_summary.json");
        if (file_exists($summaryFile)) {
            $summary = json_decode((string) file_get_contents($summaryFile), true);
            $user = $request->user();
            if ($summary && isset($summary['shipper_id']) && $user && ! $user->isAdmin() && ! $user->isLogisticsAdmin() && $summary['shipper_id'] !== $user->id) {
                abort(403, 'Akses tidak diizinkan.');
            }
        }

        $path = storage_path("app/logistics/bulk_reports/{$batchId}_errors.csv");
        if (! file_exists($path)) {
            abort(404, 'Laporan error tidak ditemukan.');
        }

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"laporan_error_upload_{$batchId}.csv\"",
        ]);
    }
}
