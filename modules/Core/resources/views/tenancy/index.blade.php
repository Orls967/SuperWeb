<x-ds.shell title="Pusat Multi-tenancy & Cakupan Isolasi">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Multi-tenancy', 'href' => route('admin.tenancy.index')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Multi-tenancy & Cakupan Isolasi Model</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pengawasan model terisolasi via TenantScopeRegistry dan pencatatan jejak bypass.</p>
            </div>
        </div>

        @include('core::api._states')

        <x-ds.card title="Daftar Model Berlingkup Tenant (TenantScopeRegistry)">
            @if (empty($scopedModels))
                <x-ds.empty
                    title="Belum Ada Model Terdaftar"
                    description="Belum ada modul yang mendaftarkan TenantScopedModelProvider."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Modul Pemilik</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Kelas Model Eloquent</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Status Scope</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($scopedModels as $modelClass => $meta)
                                <tr>
                                    <td class="px-4 py-2 font-semibold text-xs">{{ $meta['module'] }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $modelClass }}</td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge variant="success">TenantIsolationScope Aktif</x-ds.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

