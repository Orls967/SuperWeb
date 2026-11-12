<x-ds.shell title="Graf Klaster Identitas Multi-Pihak">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Akses & Identitas', 'href' => route('admin.access.grants')],
                    ['label' => 'Graf Identitas', 'href' => route('admin.access.identity')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Graf Klaster Identitas Multi-Pihak (C2)</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Penautan pelanggan & vendor lintas 30 lini bisnis berbasis pembobotan HMAC dengan proteksi persetujuan ganda.</p>
            </div>
        </div>

        <x-ds.card title="Daftar Tautan Identitas Multi-Pihak">
            @if ($links->isEmpty())
                <x-ds.empty
                    title="Belum Ada Tautan Identitas"
                    description="Belum ada profil entitas yang ditautkan di dalam graf identitas."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Kode Tautan</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Tipe Subjek</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Entitas Sumber -> Target</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Skor Kecocokan</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Persetujuan Ganda</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($links as $l)
                                <tr>
                                    <td class="px-4 py-2 font-mono font-medium">{{ $l->link_code }}</td>
                                    <td class="px-4 py-2 font-semibold text-xs">{{ $l->subject_type }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">#{{ $l->source_entity_id }} &rarr; #{{ $l->target_entity_id }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">
                                        <span class="font-bold">{{ $l->match_score }}</span> / 250
                                    </td>
                                    <td class="px-4 py-2 text-xs">
                                        Sumber: {{ $l->source_consent ? 'Ya' : 'Tidak' }} |
                                        Target: {{ $l->target_consent ? 'Ya' : 'Tidak' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge :variant="match($l->status) {
                                            'LINKED' => 'success',
                                            'BLOCKED_NO_CONSENT' => 'warning',
                                            default => 'danger'
                                        }">
                                            {{ $l->status }}
                                        </x-ds.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $links->links() }}
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

