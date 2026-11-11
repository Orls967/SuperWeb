<x-ds.shell title="Peta Batas Arsitektur Modul">
    <div class="space-y-6">
        <!-- Header & Breadcrumbs -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Batas Arsitektur', 'href' => route('admin.boundaries.map')],
                    ['label' => 'Peta Modul', 'href' => route('admin.boundaries.map')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Peta Dependensi Modul & Kepemilikan Data</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Analisis statis Directed Acyclic Graph (DAG), deteksi siklus Tarjan SCC, dan skor cakupan kontrak resmi.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.boundaries.violations') }}">
                    Daftar Pelanggaran
                </x-ds.button>
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.boundaries.waivers') }}">
                    Tata Kelola Waiver
                </x-ds.button>
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.boundaries.export.coverage') }}">
                    Ekspor CSV Skor
                </x-ds.button>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <x-ds.stat label="Total Modul" :value="$stats['total_modules']" hint="Modul terdaftar" />
            <x-ds.stat label="Busur Dependensi" :value="$stats['total_dependencies']" hint="Koneksi lintas modul" />
            <x-ds.stat label="Cakupan Kontrak" :value="sprintf('%.1f%%', $stats['coverage_permille'] / 10)" hint="{{ $stats['coverage_permille'] }} permil" />
            <x-ds.stat label="Siklus SCC" :value="$stats['cycles_count']" :hint="$stats['cycles_count'] === 0 ? 'DAG Bersih' : 'Siklus Terdeteksi'" />
        </div>

        <!-- Violation Summary Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <x-ds.stat label="Total Pelanggaran" :value="$stats['total_violations']" hint="Fakta tercatat" />
            <x-ds.stat label="Pelanggaran Terbuka" :value="$stats['open_violations']" hint="Dalam penanganan" />
            <x-ds.stat label="Dispensasi (Waiver)" :value="$stats['waived_violations']" hint="Bertenggat <= 90 hari" />
            <x-ds.stat label="Selesai (Closed)" :value="$stats['closed_violations']" hint="Termigrasi bersih" />
        </div>

        <!-- Component Graph / SCC Status -->
        <x-ds.card title="Status Siklus Ketergantungan (Tarjan SCC)">
            @if ($scc['hasCycles'])
                <div class="p-4 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-lg text-rose-800 dark:text-rose-200 text-sm mb-4">
                    <p class="font-bold">Peringatan Arsitektur: Terdeteksi {{ count($scc['cycles']) }} siklus dependensi terlarang!</p>
                    <ul class="list-disc list-inside mt-2 space-y-1">
                        @foreach ($scc['cycles'] as $cycle)
                            <li>{{ implode(' &rarr; ', $cycle) }} &rarr; {{ $cycle[0] }}</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg text-emerald-800 dark:text-emerald-200 text-sm mb-4 flex items-center justify-between">
                    <div>
                        <span class="font-bold">Topologi Terverifikasi Asiklik:</span> Graf dependensi antar modul merupakan Directed Acyclic Graph (DAG) yang valid tanpa siklus terhubung kuat.
                    </div>
                    <span class="px-2.5 py-1 text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-800 dark:text-emerald-100 rounded-full">
                        0 Siklus Terlarang
                    </span>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <th class="px-4 py-3">Nama Modul</th>
                            <th class="px-4 py-3">Dependensi Keluar</th>
                            <th class="px-4 py-3">Skor Cakupan</th>
                            <th class="px-4 py-3">Persentase</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($modules as $module)
                            @php
                                $deps = $dependencyGraph[$module] ?? [];
                                $score = $perModuleScores[$module] ?? 1000;
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                                    {{ $module }}
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                    @if ($deps !== [])
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($deps as $target)
                                                <span class="px-2 py-0.5 text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded">
                                                    {{ $target }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">Tidak ada dependensi luar</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-slate-700 dark:text-slate-300">
                                    {{ $score }} permil
                                </td>
                                <td class="px-4 py-3 font-semibold {{ $score >= 900 ? 'text-emerald-600 dark:text-emerald-400' : ($score >= 700 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }}">
                                    {{ sprintf('%.1f%%', $score / 10) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ds.card>
    </div>
</x-ds.shell>

