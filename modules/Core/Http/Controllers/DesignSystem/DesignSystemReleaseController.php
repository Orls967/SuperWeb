<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\DesignSystem;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Actions\DesignSystem\ComponentReleaseAction;
use Modules\Core\Domain\Models\DsComponent;
use Modules\Core\Domain\Models\DsComponentHistory;
use Modules\Core\Http\Requests\DesignSystem\StoreComponentProposalRequest;
use Modules\Core\Http\Requests\DesignSystem\TransitionComponentRequest;

final class DesignSystemReleaseController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $query = DsComponent::with('histories');

        if ($status) {
            $query->where('status', $status);
        }

        $components = $query->orderBy('name')->paginate(15)->withQueryString();
        $recentHistories = DsComponentHistory::with('component')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $stats = [
            'total' => DsComponent::count(),
            'draft' => DsComponent::where('status', 'draft')->count(),
            'in_review' => DsComponent::where('status', 'in_review')->count(),
            'approved' => DsComponent::where('status', 'approved')->count(),
            'released' => DsComponent::where('status', 'released')->count(),
            'deprecated' => DsComponent::where('status', 'deprecated')->count(),
        ];

        return view('core::design-system.releases.index', [
            'components' => $components,
            'recentHistories' => $recentHistories,
            'stats' => $stats,
            'selectedStatus' => $status,
        ]);
    }

    public function create(): View
    {
        return view('core::design-system.releases.create');
    }

    public function store(StoreComponentProposalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId = (int) ($request->user()?->id ?? 1);

        $component = DsComponent::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'category' => $validated['category'],
            'status' => 'draft',
            'current_version' => '0.1.0-draft',
            'wcag_level' => $validated['wcag_level'],
            'spec_doc_url' => $validated['spec_doc_url'] ?? 'docs/components/'.$validated['name'].'.md',
            'notes' => $validated['notes'] ?? null,
            'props_schema' => $validated['props_schema'] ?? [],
            'slots_schema' => $validated['slots_schema'] ?? [],
            'events_schema' => $validated['events_schema'] ?? [],
            'owner_id' => $userId,
            'idempotency_key' => $validated['idempotency_key'] ?? ('proposal:'.$validated['name']),
        ]);

        DsComponentHistory::create([
            'component_id' => $component->id,
            'from_status' => 'none',
            'to_status' => 'draft',
            'actor_id' => $userId,
            'reason' => 'Usulan awal komponen baru',
            'transitioned_at' => now(),
        ]);

        return redirect()->route('admin.design-system.releases.index')
            ->with('status', sprintf('Proposal komponen "%s" berhasil diajukan dengan status Draft.', $component->display_name));
    }

    public function transition(
        TransitionComponentRequest $request,
        string $name,
        ComponentReleaseAction $releaseAction
    ): RedirectResponse {
        $validated = $request->validated();
        $component = DsComponent::where('name', $name)->firstOrFail();
        $actorId = (int) ($request->user()?->id ?? 1);

        $context = [
            'reason' => $validated['reason'] ?? '',
            'new_version' => $validated['new_version'] ?? null,
            'breaking_changes' => (bool) ($validated['breaking_changes'] ?? false),
            'a11y_checklist_passed' => (bool) ($validated['a11y_checklist_passed'] ?? true),
            'idempotency_key' => $validated['idempotency_key'] ?? ('trans:'.$name.':'.$validated['to_status']),
        ];

        $releaseAction->execute($component, $validated['to_status'], $actorId, $context);

        return redirect()->route('admin.design-system.releases.index')
            ->with('status', sprintf('Status komponen "%s" berhasil diperbarui menjadi %s.', $component->name, strtoupper($validated['to_status'])));
    }
}
