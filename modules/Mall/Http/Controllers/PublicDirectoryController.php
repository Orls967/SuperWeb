<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Mall\Domain\Models\Tenant;

class PublicDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->input('category');
        $floor = $request->input('floor');
        $search = $request->input('q');

        $tenants = Tenant::where('is_active', true)
            ->whereHas('activeLeases')
            ->with(['activeLeases.unit.zone'])
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($floor, fn ($q) => $q->whereHas('activeLeases.unit', fn ($u) => $u->where('floor', $floor)))
            ->when($search, fn ($q) => $q->where('brand_name', 'like', "%{$search}%"))
            ->orderBy('brand_name')
            ->paginate(18);

        return view('mall::directory.index', [
            'tenants' => $tenants,
            'selectedCategory' => $category,
            'selectedFloor' => $floor,
            'search' => $search,
            'categories' => TenantCategory::cases(),
            'floors' => ['LG', 'GF', 'L1', 'L2', 'L3'],
        ]);
    }
}
