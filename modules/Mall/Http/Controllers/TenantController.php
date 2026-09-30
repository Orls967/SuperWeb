<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Mall\Domain\Models\Tenant;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->input('category');
        $search = $request->input('q');

        $tenants = Tenant::with(['activeLeases.unit'])
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('brand_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('pic_name', 'like', "%{$search}%");
            }))
            ->latest('id')
            ->paginate(15);

        return view('mall::tenants.index', [
            'tenants' => $tenants,
            'selectedCategory' => $category,
            'search' => $search,
            'categories' => TenantCategory::cases(),
        ]);
    }

    public function create(): View
    {
        $users = User::all();

        return view('mall::tenants.create', [
            'users' => $users,
            'categories' => TenantCategory::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:150'],
            'company_name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'in:fnb,fashion,automotive,entertainment,electronics,services,other'],
            'pic_name' => ['required', 'string', 'max:100'],
            'pic_phone' => ['required', 'string', 'max:30'],
            'pic_email' => ['required', 'email', 'max:100'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'brand_name' => $validated['brand_name'],
            'company_name' => $validated['company_name'],
            'category' => TenantCategory::from($validated['category']),
            'pic_name' => $validated['pic_name'],
            'pic_phone' => $validated['pic_phone'],
            'pic_email' => $validated['pic_email'],
            'npwp' => $validated['npwp'] ?? null,
            'user_id' => $validated['user_id'] ? (int) $validated['user_id'] : null,
            'is_active' => true,
        ]);

        return redirect()->route('mall.tenants.index')
            ->with('success', "Tenant mitra {$tenant->brand_name} berhasil didaftarkan.");
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['leases.unit', 'leases.property', 'user']);

        return view('mall::tenants.show', [
            'tenant' => $tenant,
        ]);
    }
}
