<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">Klaim Kargo</h2>
            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30">Aturan 4 mata: pembuat &ne; pengaju &ne; penyetuju</span>
        </div>
    </x-slot>

    <div class="py-8 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('logistics.claims.store') }}" class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 grid grid-cols-1 md:grid-cols-6 gap-2">
            @csrf
            <input name="tracking_number" required placeholder="Nomor resi" value="{{ old('tracking_number') }}" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm">
            <select name="claim_type" required class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm">@foreach($types as $t)<option value="{{ $t }}">{{ ['damage' => 'Kerusakan', 'loss' => 'Kehilangan', 'delay' => 'Keterlambatan'][$t] }}</option>@endforeach</select>
            <input name="claimed_amount_idr" type="number" min="1" required placeholder="Nilai klaim (Rp)" value="{{ old('claimed_amount_idr') }}" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm">
            <input name="description" required maxlength="1000" placeholder="Kronologi / alasan" value="{{ old('description') }}" class="md:col-span-2 bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm">
            <button class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-sm font-bold">Buat Draft Klaim</button>
        </form>

        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-hidden">
            <div class="divide-y divide-slate-700/40">
                @forelse($claims as $c)
                    <div class="p-4 space-y-2 text-xs text-slate-300" data-claim="{{ $c->claim_number }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-amber-300 font-semibold">{{ $c->claim_number }}</span>
                            <span class="px-2 py-0.5 rounded border border-slate-600 bg-slate-700/50">{{ $c->status }}</span>
                            <span>{{ $c->claim_type }}{{ $c->insured ? ' (asuransi)' : '' }}</span>
                            <span class="font-mono">{{ $c->shipment?->tracking_number }}</span>
                            <span>Klaim Rp {{ number_format($c->claimed_amount_idr, 0, ',', '.') }} / batas Rp {{ number_format($c->cap_amount_idr, 0, ',', '.') }}</span>
                            @if($c->approved_amount_idr)<span class="text-emerald-300">Disetujui Rp {{ number_format($c->approved_amount_idr, 0, ',', '.') }}</span>@endif
                        </div>
                        <div class="text-slate-400">{{ $c->description }}</div>
                        <div class="text-slate-500">Pembuat: {{ $c->creator?->name }} &bull; Pengaju: {{ $c->submitter?->name ?? '—' }} &bull; Penyetuju: {{ $c->decider?->name ?? '—' }}</div>

                        @if($isStaff && $c->status === 'draft')
                            <form method="POST" action="{{ route('logistics.claims.submit', $c->id) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold">Ajukan</button></form>
                        @endif
                        @if($isApprover && $c->status === 'submitted')
                            <form method="POST" action="{{ route('logistics.claims.decide', $c->id) }}" class="flex flex-wrap gap-2">
                                @csrf
                                <input name="approved_amount_idr" type="number" min="1" placeholder="Nilai disetujui" class="w-36 bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                                <input name="notes" required maxlength="500" placeholder="Catatan keputusan" class="flex-1 min-w-[10rem] bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                                <button name="decision" value="approve" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold">Setujui</button>
                                <button name="decision" value="reject" class="px-3 py-1.5 rounded-lg bg-rose-700 hover:bg-rose-600 text-white font-bold">Tolak</button>
                            </form>
                        @endif
                        @if($isApprover && $c->status === 'approved')
                            <form method="POST" action="{{ route('logistics.claims.pay', $c->id) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold">Bayarkan ke Dompet Shipper</button></form>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">Belum ada klaim.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
