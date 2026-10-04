<?php

declare(strict_types=1);

namespace Modules\Asset\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Asset\Application\Services\AssetService;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetAssignment;
use Modules\Asset\Domain\Models\AssetCategory;
use Modules\Asset\Domain\Models\AssetLocation;

/**
 * UI aset (30.9): register, detail, opname, penugasan, asuransi.
 */
class AssetController extends Controller
{
    public function __construct(
        private readonly AssetService $service,
    ) {}

    public function index(Request $request): View
    {
        $query = Asset::query()->with(['category', 'location'])->orderByDesc('created_at');

        if ($search = $request->get('q')) {
            $query->where(fn ($q) => $q
                ->where('asset_number', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('asset_tag', 'like', "%{$search}%"));
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $this->service->ensureDefaultCategories();

        return view('asset::index', [
            'assets' => $query->paginate(20)->withQueryString(),
            'statuses' => AssetStatus::cases(),
            'categories' => AssetCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Asset $asset): View
    {
        $asset->load(['category', 'location', 'events', 'assignments.assignedTo', 'insurances', 'stocktakes']);

        return view('asset::show', [
            'asset' => $asset,
            'locations' => AssetLocation::orderBy('name')->get(),
            'openAssignments' => $asset->assignments()->where('status', 'out')->get(),
        ]);
    }

    public function create(): View
    {
        $this->service->ensureDefaultCategories();

        return view('asset::create', [
            'categories' => AssetCategory::where('is_active', true)->orderBy('name')->get(),
            'locations' => AssetLocation::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->ensureDefaultCategories();

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'category_id' => 'required|integer|exists:ast_categories,id',
            'description' => 'nullable|string|max:1000',
            'location_id' => 'nullable|integer|exists:ast_locations,id',
            'legal_entity_id' => 'nullable|uuid|exists:pty_legal_entities,id',
            'responsible_user_id' => 'nullable|integer|exists:users,id',
            'brand' => 'nullable|string|max:120',
            'serial_number' => 'nullable|string|max:120',
            'condition' => 'required|in:good,fair,poor,broken',
            'acquisition_cost_idr' => 'required|integer|min:0',
            'landed_cost_idr' => 'nullable|integer|min:0',
            'acquired_at' => 'nullable|date',
            'in_service_at' => 'nullable|date',
            'source_type' => 'nullable|in:direct,purchase,construction',
        ]);

        $asset = $this->service->register($data, $request->user());

        return redirect()
            ->route('asset.show', $asset)
            ->with('success', "Aset [{$asset->asset_number}] berhasil diregistrasi.");
    }

    public function move(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'location_id' => 'required|integer|exists:ast_locations,id',
        ]);

        $target = AssetLocation::findOrFail((int) $data['location_id']);

        if ($asset->location_id !== $target->id) {
            $result = $this->service->requestMove($asset, $target->id, $request->user());
        } else {
            $result = ['status' => 'pending'];
        }

        if ($result['status'] === 'pending') {
            return back()->with('success', 'Permintaan mutasi diajukan menunggu persetujuan admin.');
        }

        return back()->with('success', 'Aset berhasil dimutasi.');
    }

    public function recordMoveApproval(Asset $asset, AssetLocation $location): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $this->service->executeMove($asset, $location, auth()->user()?->name);

        return back()->with('success', 'Mutasi aset dieksekusi setelah persetujuan.');
    }

    public function scan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tag' => 'required|string|max:60',
            'result' => 'required|in:found,missing,unexpected',
            'cycle_id' => 'required|integer|min:1',
            'note' => 'nullable|string|max:500',
        ]);

        $asset = Asset::where('asset_tag', $data['tag'])->firstOrFail();

        $stocktake = $this->service->recordStocktake(
            $asset,
            (int) $data['cycle_id'],
            $data['result'],
            $request->user(),
            $data['note'] ?? null,
        );

        return back()->with('success', "Stok opname #{$stocktake->cycle_id}: {$stocktake->result}.");
    }

    public function checkOut(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to_user_id' => 'required|integer|exists:users,id',
            'purpose' => 'nullable|string|max:300',
            'condition_out' => 'nullable|string|max:500',
        ]);

        $to = User::findOrFail($data['assigned_to_user_id']);
        $this->service->checkOut($asset, $to, $request->user(), $data['purpose'] ?? null, $data['condition_out'] ?? null);

        return back()->with('success', 'Aset diserahkan (check-out).');
    }

    public function checkIn(Asset $asset, AssetAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->asset_id === $asset->id, 404);

        $this->service->checkIn($assignment, request('condition_in'));

        return back()->with('success', 'Aset diterima kembali (check-in).');
    }

    public function addInsurance(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'policy_number' => 'required|string|max:60|unique:ast_insurances,policy_number',
            'provider' => 'required|string|max:160',
            'coverage_amount_idr' => 'required|integer|min:1',
            'annual_premium_idr' => 'required|integer|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $this->service->addInsurance(
            asset: $asset,
            policyNumber: $data['policy_number'],
            provider: $data['provider'],
            coverageAmountIdr: (int) $data['coverage_amount_idr'],
            annualPremiumIdr: (int) $data['annual_premium_idr'],
            startDate: $data['start_date'],
            endDate: $data['end_date'],
        );

        return back()->with('success', 'Polis asuransi aset terdaftar.');
    }

    public function verifyChain(): RedirectResponse
    {
        $result = $this->service->verifyChain();

        if ($result['valid']) {
            return back()->with('success', "Rantai hash aset valid ({$result['checked']} event diperiksa).");
        }

        return back()->with('error', 'Rantai hash aset RUSAK: '.count($result['broken']).' ketidakcocokan ditemukan.');
    }
}
