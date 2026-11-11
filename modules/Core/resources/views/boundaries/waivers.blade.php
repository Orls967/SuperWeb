<x-ds.shell title="Tata Kelola Waiver Batas Arsitektur">
    <div class="space-y-6">
        <!-- Header & Breadcrumbs -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Batas Arsitektur', 'href' => route('admin.boundaries.map')],
                    ['label' => 'Waiver', 'href' => route('admin.boundaries.waivers')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Tata Kelola Waiver Arsitektur (&le; 90 Hari)</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pengawasan dispensasi batas modular bertenggat waktu dengan rujukan anchor dokumen arsitektur resmi.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.boundaries.map') }}">
                    Peta Modul
                </x-ds.button>
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.boundaries.violations') }}">
                    Daftar Pelanggaran
                </x-ds.button>
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.boundaries.export.waivers') }}">
                    Ekspor CSV Waiver
                </x-ds.button>
            </div>
        </div>

        @if (session('success'))
            <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg text-emerald-800 dark:text-emerald-200 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <!-- Waivers List -->
        <x-ds.card title="Daftar Waiver Aktif">
            @if ($waivers->isEmpty())
                <x-ds.empty title="Tidak Ada Waiver Aktif" description="Saat ini tidak ada pelanggaran batas arsitektur yang berstatus waived." />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="px-4 py-3">ID / Aturan</th>
                                <th class="px-4 py-3">Modul & Tanda Tangan</th>
                                <th class="px-4 py-3">Anchor Keputusan</th>
                                <th class="px-4 py-3">Batas Kedaluwarsa</th>
                                <th class="px-4 py-3">Sisa Waktu</th>
                                <th class="px-4 py-3 text-right">Perpanjangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($waivers as $w)
                                @php
                                    $isExpired = $w->expires_at?->isPast() ?? false;
                                    $daysRemaining = $w->expires_at !== null ? max(0, (int) now()->diffInDays($w->expires_at, false)) : 0;
                                @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-900 dark:text-slate-100">
                                        #{{ $w->id }}
                                        <span class="ml-1 px-2 py-0.5 text-xs bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 rounded">{{ $w->rule_id }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                        <div class="font-semibold">{{ $w->client_module }} &rarr; {{ $w->owner_module }}</div>
                                        <div class="text-xs font-mono text-slate-500 truncate max-w-xs">{{ $w->signature }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-indigo-600 dark:text-indigo-400">
                                        {{ $w->adr_anchor ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                                        {{ $w->expires_at?->format('Y-m-d') ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($isExpired)
                                            <span class="px-2 py-0.5 text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200 rounded">
                                                Kedaluwarsa
                                            </span>
                                        @elseif ($daysRemaining <= 7)
                                            <span class="px-2 py-0.5 text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200 rounded">
                                                {{ $daysRemaining }} hari (Hampir Habis)
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200 rounded">
                                                {{ $daysRemaining }} hari tersisa
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('admin.boundaries.waivers.extend', $w->id) }}" class="inline-flex items-center gap-1">
                                            @csrf
                                            <input type="hidden" name="adr_anchor" value="{{ $w->adr_anchor ?? 'docs/DECISIONS.md#w8-d43' }}">
                                            <input type="hidden" name="days" value="30">
                                            <input type="hidden" name="reason" value="Perpanjangan dispensasi operasional 30 hari via persetujuan arsitek">
                                            <x-ds.button type="submit" variant="ghost" size="sm">
                                                +30 Hari
                                            </x-ds.button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $waivers->links() }}
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

