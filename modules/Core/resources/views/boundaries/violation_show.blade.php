<x-ds.shell title="Detail Pelanggaran Batas #{{ $violation->id }}">
    <div class="space-y-6">
        <!-- Header & Breadcrumbs -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Batas Arsitektur', 'href' => route('admin.boundaries.map')],
                    ['label' => 'Pelanggaran', 'href' => route('admin.boundaries.violations')],
                    ['label' => '#' . $violation->id, 'href' => '#'],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Detail Pelanggaran #{{ $violation->id }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Aturan {{ $violation->rule_id }} &bull; {{ $violation->client_module }} &rarr; {{ $violation->owner_module }}</p>
            </div>
            <div>
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.boundaries.violations') }}">
                    Kembali ke Daftar
                </x-ds.button>
            </div>
        </div>

        @include('core::boundaries._states')

        <!-- Detail Attributes -->
        <x-ds.card title="Fakta Pelanggaran">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="block text-xs font-semibold text-slate-500 uppercase">Aturan Arsitektur</span>
                    <span class="font-mono text-base font-bold">{{ $violation->rule_id }}</span> ({{ $violation->category?->value ?? (string) $violation->category }})
                </div>
                <div>
                    <span class="block text-xs font-semibold text-slate-500 uppercase">Status State Machine</span>
                    <x-ds.badge-status :status="$violation->status?->value ?? (string) $violation->status" />
                </div>
                <div>
                    <span class="block text-xs font-semibold text-slate-500 uppercase">Modul Pemanggil (Client)</span>
                    <span class="font-semibold">{{ $violation->client_module }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-slate-500 uppercase">Modul Pemilik (Owner)</span>
                    <span class="font-semibold">{{ $violation->owner_module }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="block text-xs font-semibold text-slate-500 uppercase">Lokasi Berkas</span>
                    <span class="font-mono text-xs bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded block mt-1">{{ $violation->file_path }}:{{ $violation->line_number }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="block text-xs font-semibold text-slate-500 uppercase">Tanda Tangan Pelanggaran</span>
                    <span class="font-mono text-xs bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded block mt-1">{{ $violation->signature }}</span>
                </div>
                @if ($violation->contract_name)
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase">Nama Kontrak Pengganti</span>
                        <span class="font-mono text-xs">{{ $violation->contract_name }}</span>
                    </div>
                @endif
                @if ($violation->adr_anchor)
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase">Rujukan ADR (DECISIONS.md)</span>
                        <span class="font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ $violation->adr_anchor }}</span>
                    </div>
                @endif
                @if ($violation->expires_at)
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase">Batas Kedaluwarsa Waiver</span>
                        <span class="font-semibold">{{ $violation->expires_at->toFormattedDateString() }}</span>
                        <span class="text-xs text-slate-500">({{ $violation->expires_at->diffForHumans() }})</span>
                    </div>
                @endif
            </div>
        </x-ds.card>

        <!-- Timeline State Machine -->
        <x-ds.card title="Linimasa Transisi State Machine (Append-Only Facts)">
            @if ($transitions->isEmpty())
                <x-ds.empty title="Belum Ada Transisi" description="Belum ada riwayat perubahan status yang tercatat untuk pelanggaran ini." />
            @else
                <div class="space-y-4">
                    @foreach ($transitions as $t)
                        <div class="flex items-start gap-4 p-3 border-l-2 border-indigo-500 bg-slate-50 dark:bg-slate-800/40 rounded-r-lg text-sm">
                            <div class="min-w-[120px] text-xs font-mono text-slate-500">
                                {{ $t->occurred_at?->format('Y-m-d H:i:s') }}
                            </div>
                            <div class="flex-1">
                                <div class="font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                    <span class="px-2 py-0.5 text-xs bg-slate-200 dark:bg-slate-700 rounded">{{ $t->from_status?->value ?? (string) $t->from_status }}</span>
                                    <span>&rarr;</span>
                                    <span class="px-2 py-0.5 text-xs bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 rounded font-bold">{{ $t->to_status?->value ?? (string) $t->to_status }}</span>
                                    <span class="text-xs font-normal text-slate-400">oleh {{ $t->actor }}</span>
                                </div>
                                <div class="text-xs text-slate-600 dark:text-slate-300 mt-1">
                                    {{ $t->reason }}
                                </div>
                                @if ($t->commit_sha)
                                    <div class="text-xs font-mono text-slate-400 mt-1">
                                        Commit: {{ $t->commit_sha }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>
