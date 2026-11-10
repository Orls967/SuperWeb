<x-ds.shell title="Detail Sesi Simulasi">
    <div class="space-y-6">
        <!-- Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Simulasi Kernel', 'href' => route('admin.simulation.runs.index')],
                    ['label' => $run->name, 'href' => '#'],
                ]" />
                <div class="flex items-center gap-3 mt-1">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $run->name }}</h1>
                    <x-ds.badge-status :status="$run->status" size="lg" />
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $run->mode === 'sandbox' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                        {{ strtoupper($run->mode) }}
                    </span>
                </div>
            </div>

            <!-- Action buttons (Pause, Resume) with 2-step anti double-submit -->
            <div class="flex items-center gap-3">
                @if($run->status === 'running')
                    <form method="POST" action="{{ route('admin.simulation.runs.pause', $run->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menjeda sesi simulasi ini?');">
                        @csrf
                        <x-ds.button type="submit" variant="secondary" size="md">
                            Jeda (Pause)
                        </x-ds.button>
                    </form>
                @endif

                @if(in_array($run->status, ['paused', 'checkpointed', 'completed']))
                    <form method="POST" action="{{ route('admin.simulation.runs.resume', $run->id) }}" onsubmit="return confirm('Lanjutkan simulasi dari checkpoint terakhir?');">
                        @csrf
                        <x-ds.button type="submit" variant="primary" size="md">
                            Lanjutkan (Resume)
                        </x-ds.button>
                    </form>
                @endif

                <x-ds.button variant="secondary" size="md" href="{{ route('admin.simulation.export', ['type' => 'checkpoints', 'run_id' => $run->id]) }}">
                    Ekspor Checkpoints CSV
                </x-ds.button>
            </div>
        </div>

        <!-- Metrics Overview -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-ds.stat label="Hari Virtual" :value="$run->current_day . ' / ' . $run->total_days" hint="Progres hari tersimulasi" />
            <x-ds.stat label="Seed LCG (MINSTD)" :value="$run->seed" hint="Seed partisi deterministik" />
            <x-ds.stat label="Total Checkpoint" :value="$checkpoints->total()" hint="Snapshot tersimpan" />
            <x-ds.stat label="Kecepatan / Multiplier" :value="$run->speed_multiplier . 'x'" hint="Kompresi waktu virtual" />
        </div>

        <!-- Rantai Fingerprint Kriptografis -->
        <x-ds.card variant="default">
            <div class="p-5 space-y-3">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Rantai Fingerprint SHA-256 Terakhir</h3>
                <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700/60 font-mono text-sm text-slate-800 dark:text-slate-200 break-all select-all">
                    {{ $run->chain_hash ?? 'Belum ada rantai fingerprint yang terbentuk.' }}
                </div>
            </div>
        </x-ds.card>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Checkpoints Table -->
            <div class="lg:col-span-2 space-y-4">
                <x-ds.card variant="default">
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/80">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Milestone Checkpoints</h3>
                    </div>

                    @if($checkpoints->isEmpty())
                        <x-ds.empty title="Belum Ada Checkpoint" description="Checkpoint ditulis otomatis pada setiap milestone 50 hari atau akhir sesi." />
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs uppercase bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-5 py-3 text-right font-mono">Hari</th>
                                        <th class="px-5 py-3 font-mono">Chain Hash (SHA-256)</th>
                                        <th class="px-5 py-3">Waktu Ditulis</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                    @foreach($checkpoints as $cp)
                                        <tr>
                                            <td class="px-5 py-3 text-right font-mono tabular-nums font-semibold text-slate-900 dark:text-slate-100">
                                                Day {{ $cp->day_number }}
                                            </td>
                                            <td class="px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300 truncate max-w-sm" title="{{ $cp->chain_hash }}">
                                                {{ $cp->chain_hash }}
                                            </td>
                                            <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                                                {{ $cp->created_at?->diffForHumans() }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($checkpoints->hasPages())
                            <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">
                                {{ $checkpoints->links() }}
                            </div>
                        @endif
                    @endif
                </x-ds.card>
            </div>

            <!-- Timeline Riwayat State Machine -->
            <div class="space-y-4">
                <x-ds.card variant="default">
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/80">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Riwayat Transisi State Machine</h3>
                    </div>
                    <div class="p-5">
                        <x-ds.timeline :events="$transitions" />
                    </div>
                </x-ds.card>
            </div>
        </div>
    </div>
</x-ds.shell>

