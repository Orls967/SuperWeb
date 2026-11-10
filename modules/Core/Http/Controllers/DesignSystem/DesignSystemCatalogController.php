<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\DesignSystem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Domain\Models\DsComponent;
use Modules\Core\Domain\Models\DsToken;
use Modules\Shared\Domain\Algorithms\Ui\WcagContrastCalculator;

final class DesignSystemCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->query('category');
        $status = $request->query('status');
        $search = $request->query('search');

        $query = DsComponent::query();

        if ($category) {
            $query->where('category', $category);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        $components = $query->orderBy('name')->paginate(15)->withQueryString();
        $categories = DsComponent::select('category')->distinct()->pluck('category');

        $tokensCount = DsToken::count();
        $releasedCount = DsComponent::where('status', 'released')->count();
        $totalCount = DsComponent::count();

        return view('core::design-system.index', [
            'components' => $components,
            'categories' => $categories,
            'selectedCategory' => $category,
            'selectedStatus' => $status,
            'search' => $search,
            'tokensCount' => $tokensCount,
            'releasedCount' => $releasedCount,
            'totalCount' => $totalCount,
        ]);
    }

    public function show(string $name, WcagContrastCalculator $contrastCalculator): View
    {
        $component = DsComponent::where('name', $name)->firstOrFail();
        $history = $component->histories()->orderByDesc('created_at')->get();
        $a11yFindings = $component->a11yFindings()->orderByDesc('created_at')->get();

        // Hitung rasio kontras 3 tema untuk preview token warna utama
        $contrastMetrics = [
            'light' => $contrastCalculator->calculate('#0f172a', '#ffffff'),
            'dark' => $contrastCalculator->calculate('#f8fafc', '#0f172a'),
            'hc' => $contrastCalculator->calculate('#ffff00', '#000000'),
        ];

        return view('core::design-system.show', [
            'component' => $component,
            'history' => $history,
            'a11yFindings' => $a11yFindings,
            'contrastMetrics' => $contrastMetrics,
        ]);
    }
}

