<x-ds.shell title="Bandingkan Sesi Simulasi (Diff)">
    <div class="space-y-6">
        <!-- Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Simulasi Kernel', 'href' => route('admin.simulation.runs.index')],
                    ['label' => 'Diff', 'href' => route('admin.simulation.diff')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Komparasi Dua Sesi Simulasi</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Verifikasi determinisme rantai fingerprint hash dan deteksi titik divergensi antar run.</p>
            </div>
            <div>
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.simulation.runs.index') }}">
                    Kembali ke Daftar Sesi
                </x-ds.button>
            </div>
        </div>

        <!-- Pemilih Sesi A dan B -->
        <x-ds.card variant="default">
            <form method="GET" action="{{ route('admin.simulation.diff') }}" class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="a" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Sesi A (Baseline)</label>
                    <select id="a" name="a" class="w-full text-sm rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- Pilih Sesi A --</option>
                        @foreach ($allRuns as $item)
                            <option value="{{ $item->name }}" @selected(($runA && $runA->id === $item->id) || request('a') === $item->name)>
                                {{ $item->name }} (Seed: {{ $item->seed }}, {{ $item->mode }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="b" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Sesi B (Komparasi)</label>
                    <select id="b" name="b" class="w-full text-sm rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- Pilih Sesi B --</option>
                        @foreach ($allRuns as $item)
                            <option value="{{ $item->name }}" @selected(($runB && $runB->id === $item->id) || request('b') === $item->name)>
                                {{ $item->name }} (Seed: {{ $item->seed }}, {{ $item->mode }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-ds.button variant="primary" size="md" type="submit" class="w-full">
                        Bandingkan Sekarang
                    </x-ds.button>
                </div>
            </form>
        </x-ds.card>

        @if ($runA && $runB && $diffResult)
            <!-- Hasil Komparasi -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-ds.card variant="default" class="p-4">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Status Komparasi</span>
                    <div class="mt-2 flex items-center gap-2">
                        @if ($diffResult['diverged'])
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                DIVERGEN
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                IDENTIK (DETERMINISTIK)
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-2">{{ $diffResult['reason'] }}</p>
                </x-ds.card>

                <x-ds.card variant="default" class="p-4">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Titik Divergensi</span>
                    <div class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100">
                        @if ($diffResult['first_divergence_day'])
                            Hari ke-{{ $diffResult['first_divergence_day'] }}
                        @else
                            Nihil (Sama Sempurna)
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Hari pertama rantai hash tidak cocok</p>
                </x-ds.card>

                <x-ds.card variant="default" class="p-4">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Perbandingan Seed</span>
                    <div class="mt-2 text-sm font-mono text-slate-700 dark:text-slate-300">
                        <div>A: {{ $runA->seed }}</div>
                        <div>B: {{ $runB->seed }}</div>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Seed wajib identik untuk ekspektasi deterministik</p>
                </x-ds.card>
            </div>

            <!-- Tabel Detail Perbedaan Handler -->
            <x-ds.card variant="default">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Rincian Output Handler</h3>
                    <span class="text-xs text-slate-500">{{ count($diffResult['handler_diffs']) }} Handler Diperiksa</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-4 py-3">Nama Handler</th>
                                <th class="px-4 py-3">Hasil Run A</th>
                                <th class="px-4 py-3">Hasil Run B</th>
                                <th class="px-4 py-3">Kesesuaian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @forelse ($diffResult['handler_diffs'] as $handlerName => $hDiff)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $handlerName }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ json_encode($hDiff['a']) }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ json_encode($hDiff['b']) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($hDiff['identical'])
                                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold text-xs">Cocok</span>
                                        @else
                                            <span class="text-red-600 dark:text-red-400 font-semibold text-xs">Beda</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-slate-400">
                                        Tidak ada handler diff tersimpan atau seed langsung berbeda sejak hari pertama.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        @else
            <x-ds.card variant="default" class="p-8 text-center">
                <p class="text-slate-500 dark:text-slate-400">Pilih Sesi A dan Sesi B di atas untuk membandingkan determinisme dan rantai hash simulasi.</p>
            </x-ds.card>
        @endif
    </div>
</x-ds.shell>

