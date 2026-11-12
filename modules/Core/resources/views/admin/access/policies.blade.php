<x-ds.shell title="Kebijakan Otorisasi ABAC">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Akses & Identitas', 'href' => route('admin.access.grants')],
                    ['label' => 'Kebijakan ABAC', 'href' => route('admin.access.policies')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Kebijakan Otorisasi ABAC (Attribute-Based)</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Aturan berbasis dimensi (entitas, wilayah, site, proyek, waktu) dengan strategi Deny-Overrides fail-closed.</p>
            </div>
        </div>

        <x-ds.card title="Daftar Aturan Kebijakan ABAC Terdaftar">
            @if ($policies->isEmpty())
                <x-ds.empty
                    title="Belum Ada Kebijakan ABAC"
                    description="Belum ada aturan kebijakan akses berbasis atribut yang dikonfigurasikan."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Kode Kebijakan</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Nama</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Target Resource</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Efek</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Dimensi Terikat</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($policies as $p)
                                <tr>
                                    <td class="px-4 py-2 font-mono font-medium">{{ $p->policy_code }}</td>
                                    <td class="px-4 py-2">{{ $p->name }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $p->target_resource }}</td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge :variant="$p->effect === 'ALLOW' ? 'success' : 'danger'">
                                            {{ $p->effect }}
                                        </x-ds.badge>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs">
                                        {{ json_encode($p->dimensions, JSON_UNESCAPED_SLASHES) }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge :variant="$p->is_active ? 'success' : 'neutral'">
                                            {{ $p->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </x-ds.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $policies->links() }}
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

