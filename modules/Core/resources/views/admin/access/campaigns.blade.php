<x-ds.shell title="Kampanye Re-Sertifikasi Akses">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Akses & Identitas', 'href' => route('admin.access.grants')],
                    ['label' => 'Re-Sertifikasi', 'href' => route('admin.access.campaigns')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Kampanye Re-Sertifikasi Akses Berkala</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pencegahan privilege creep melalui audit akses berkala dengan auto-revocation saat batas tenggat berakhir.</p>
            </div>
        </div>

        <x-ds.card title="Daftar Kampanye Re-Sertifikasi">
            @if ($campaigns->isEmpty())
                <x-ds.empty
                    title="Belum Ada Kampanye"
                    description="Belum ada kampanye re-sertifikasi akses yang dijadwalkan atau berlangsung."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Kode Kampanye</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Judul</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Cakupan</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Total Item Audit</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Tenggat Waktu</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($campaigns as $camp)
                                <tr>
                                    <td class="px-4 py-2 font-mono font-medium">{{ $camp->campaign_code }}</td>
                                    <td class="px-4 py-2">{{ $camp->title }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $camp->scope ?? 'Global' }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $camp->items_count }} item</td>
                                    <td class="px-4 py-2 text-xs text-slate-500">{{ $camp->deadline->format('d M Y H:i') }}</td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge :variant="$camp->status === 'COMPLETED' ? 'success' : 'warning'">
                                            {{ $camp->status }}
                                        </x-ds.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $campaigns->links() }}
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

