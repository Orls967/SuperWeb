<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">Exception &amp; SLA</h2>
            <div class="flex gap-2 text-xs">
                <span class="px-3 py-1 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30">{{ $exceptions->count() }} terbuka</span>
                <span class="px-3 py-1 rounded-full bg-orange-500/20 text-orange-300 border border-orange-500/30">{{ $breached->count() }} melewati SLA</span>
                <span class="px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">{{ $atRisk->count() }} berisiko</span>
            </div>
        </div>
    </x-slot>

    <div class="py-8 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- Lapor manual --}}
        <form method="POST" action="{{ route('logistics.exceptions.store') }}" class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 grid grid-cols-1 md:grid-cols-5 gap-2">
            @csrf
            <input name="tracking_number" required placeholder="Nomor resi" value="{{ old('tracking_number') }}" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm">
            <select name="type" required class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm">
                @foreach($manualTypes as $t)
                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                @endforeach
            </select>
            <input name="description" required maxlength="500" placeholder="Deskripsi kendala" value="{{ old('description') }}" class="md:col-span-2 bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm">
            <button class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-sm font-bold">Laporkan</button>
        </form>

        {{-- Exception terbuka --}}
        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl shadow-xl overflow-hidden" id="open-exceptions">
            <div class="p-5 border-b border-slate-700/50 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-base font-bold text-slate-100">Exception Terbuka</h3>
                <form method="GET" class="flex gap-2 text-xs">
                    <select name="type" onchange="this.form.submit()" class="bg-slate-900 border-slate-700 text-slate-200 rounded-lg text-xs">
                        <option value="">Semua tipe</option>
                        @foreach($types as $t)
                            <option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->label() }}</option>
                        @endforeach
                    </select>
                    <select name="severity" onchange="this.form.submit()" class="bg-slate-900 border-slate-700 text-slate-200 rounded-lg text-xs">
                        <option value="">Semua tingkat</option>
                        @foreach($severities as $sv)
                            <option value="{{ $sv->value }}" @selected(($filters['severity'] ?? '') === $sv->value)>{{ $sv->label() }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="divide-y divide-slate-700/40">
                @forelse($exceptions as $ex)
                    <div class="p-4 space-y-2 text-xs text-slate-300" data-exception="{{ $ex->id }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 rounded border {{ $ex->severity->badgeClass() }}">{{ $ex->severity->label() }}</span>
                            <span class="font-semibold text-slate-100">{{ $ex->type->label() }}</span>
                            <span class="font-mono text-amber-300">{{ $ex->shipment?->tracking_number }}</span>
                            <span class="text-slate-500">{{ $ex->detected_at->format('d/m H:i') }}{{ $ex->location ? ' • '.$ex->location->name : '' }}</span>
                        </div>
                        <div>{{ $ex->description }}</div>
                        @if($canResolve)
                            <form method="POST" action="{{ route('logistics.exceptions.resolve', $ex->id) }}" class="grid grid-cols-1 md:grid-cols-4 gap-2">
                                @csrf
                                <input name="notes" required maxlength="500" placeholder="Catatan penyelesaian" class="md:col-span-2 bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                                @if($ex->shipment?->status === \Modules\Logistics\Domain\Enums\ShipmentStatus::Exception)
                                    <select name="resume_to" required class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                                        <option value="">Lanjutkan resi ke...</option>
                                        @foreach($resumeStatuses as $st)
                                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <button class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold">Selesaikan</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">Tidak ada exception terbuka.</div>
                @endforelse
            </div>
        </section>

        {{-- SLA --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach([['Melewati SLA', $breached, 'text-orange-300'], ['Berisiko Terlambat (≤ '.config('logistics.sla_at_risk_hours').' jam)', $atRisk, 'text-amber-300']] as [$title, $rows, $color])
                <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl shadow-xl overflow-hidden">
                    <div class="p-5 border-b border-slate-700/50"><h3 class="text-base font-bold {{ $color }}">{{ $title }}</h3></div>
                    <ul class="divide-y divide-slate-700/40 text-xs text-slate-300">
                        @forelse($rows as $s)
                            <li class="px-5 py-3 flex flex-wrap justify-between gap-2">
                                <span><span class="font-mono text-amber-300">{{ $s->tracking_number }}</span> &bull; {{ $s->origin?->city }} &rarr; {{ $s->destination?->city }} &bull; {{ $s->status->label() }}</span>
                                <span class="text-slate-400">jatuh tempo {{ $sla->dueAt($s)?->format('d/m H:i') }}</span>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-slate-400">Tidak ada.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
