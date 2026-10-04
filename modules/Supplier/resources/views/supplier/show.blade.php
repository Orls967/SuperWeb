@extends('layouts.app')
@section('title', $supplier->name)
@section('subtitle', $supplier->code.' · '.$supplier->status->label())

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-white">{{ $supplier->name }}</h1>
                <span class="text-xs font-mono text-indigo-300 bg-indigo-500/10 px-2 py-1 rounded">{{ $supplier->code }}</span>
                <span class="text-xs px-2 py-1 rounded {{ $supplier->status->value === 'preferred' ? 'bg-emerald-500/15 text-emerald-300' : ($supplier->status->value === 'disqualified' ? 'bg-red-500/15 text-red-300' : 'bg-amber-500/15 text-amber-300') }}">{{ $supplier->status->label() }}</span>
            </div>
            <p class="text-sm text-slate-400 mt-2">{{ $supplier->kind }} · {{ $supplier->lead_time_days }} hari lead time · termin {{ $supplier->payment_terms_days }} hari · {{ $supplier->rating }}★ · party: {{ $supplier->party?->name ?? '—' }}</p>
            @if($supplier->capabilities)
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach($supplier->capabilities as $cap)
                        <span class="text-xs bg-slate-700/60 text-slate-300 px-2 py-1 rounded">{{ $cap }}</span>
                    @endforeach
                </div>
            @endif
        </div>
        <a href="{{ route('supplier.index') }}" class="text-sm text-indigo-400 hover:text-indigo-300">← Direktori</a>
    </div>

    @if(session('success'))<div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- 32.2 Status onboarding --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="font-semibold text-white mb-3">🧭 Transisi Status (riwayat wajib)</h2>
            <form method="POST" action="{{ route('supplier.transition', $supplier) }}" class="space-y-2">
                @csrf
                <select name="status" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}" @disabled($supplier->status === $st)>{{ $st->label() }}</option>
                    @endforeach
                </select>
                <input name="reason" required placeholder="Alasan (wajib)" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Terapkan</button>
            </form>

            <div class="mt-4 space-y-1 text-xs text-slate-400">
                @forelse($supplier->qualifications as $q)
                    <div class="flex justify-between border-t border-slate-700/60 pt-1">
                        <span class="uppercase">{{ $q->type }}</span>
                        <span>skor {{ $q->total_score }} · {{ $q->result }} · {{ $q->approval_status }}</span>
                    </div>
                @empty
                    <p>Belum ada kualifikasi.</p>
                @endforelse
            </div>

            @if($supplier->qualifications()->where('approval_status', 'pending')->exists() && auth()->user()?->isAdmin())
                <form method="POST" action="{{ route('supplier.qualifications.approve', $supplier) }}" class="mt-3">
                    @csrf
                    <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm">✅ Setujui Kualifikasi Terakhir</button>
                </form>
            @endif
        </section>

        {{-- 32.2 Audit lokasi / kuesioner --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="font-semibold text-white mb-3">📝 Ajukan Kualifikasi / Audit Lokasi</h2>
            <form method="POST" action="{{ route('supplier.qualifications.store', $supplier) }}" class="grid grid-cols-2 gap-2">
                @csrf
                <select name="type" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <option value="questionnaire">Kuesioner</option>
                    <option value="site_audit">Audit Lokasi</option>
                </select>
                <input name="total_score" type="number" min="0" max="100" required placeholder="Skor 0–100" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="notes" placeholder="Catatan" class="col-span-2 px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <button class="col-span-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Ajukan (→ Approval)</button>
            </form>
        </section>

        {{-- 32.1 Sertifikasi --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="font-semibold text-white mb-3">🏅 Sertifikasi (masa berlaku)</h2>
            <form method="POST" action="{{ route('supplier.certifications.store', $supplier) }}" class="grid grid-cols-2 gap-2 mb-3">
                @csrf
                <select name="type" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    @foreach(['iso9001' => 'ISO 9001', 'iso22000' => 'ISO 22000', 'sni' => 'SNI', 'halal' => 'Halal', 'bpom' => 'BPOM', 'gmp' => 'GMP', 'haccp' => 'HACCP', 'other' => 'Lainnya'] as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
                <input name="number" placeholder="Nomor sertifikat" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="issuer" placeholder="Penerbit" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="expires_at" type="date" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <button class="col-span-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Daftarkan</button>
            </form>
            <div class="space-y-1 text-xs">
                @forelse($supplier->certifications as $c)
                    <div class="flex justify-between border-t border-slate-700/60 pt-1">
                        <span class="text-slate-300 uppercase">{{ $c->type }} {{ $c->number }}</span>
                        <span class="{{ $c->isExpired() ? 'text-red-400' : ($c->expires_at?->diffInDays(now()) < 60 ? 'text-amber-400' : 'text-slate-500') }}">{{ $c->expires_at?->format('d M Y') ?? '—' }}</span>
                    </div>
                @empty
                    <p class="text-slate-500">Belum ada sertifikasi.</p>
                @endforelse
            </div>
        </section>

        {{-- 32.6 Skor periodik --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="font-semibold text-white mb-3">📈 Skor Periodik (OTD · kualitas · harga · respons)</h2>
            <form method="POST" action="{{ route('supplier.scorecards.store', $supplier) }}" class="grid grid-cols-3 gap-2">
                @csrf
                <input name="period" required placeholder="YYYY-MM" value="{{ now()->format('Y-m') }}" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="otd_percent" type="number" min="0" max="100" placeholder="OTD %" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="reject_percent" type="number" step="0.1" min="0" max="100" placeholder="Reject %" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="price_index" type="number" step="0.01" min="0" max="10" placeholder="Indeks harga (1=normal)" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="response_days" type="number" step="0.5" min="0" placeholder="Rata-rata respons (hari)" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Simpan</button>
            </form>
            <div class="mt-3 space-y-1 text-xs">
                @forelse($supplier->scorecards as $sc)
                    <div class="flex justify-between border-t border-slate-700/60 pt-1">
                        <span class="text-slate-300">{{ $sc->period }}</span>
                        <span class="{{ $sc->overall_score < 70 ? 'text-red-400' : ($sc->overall_score < 85 ? 'text-amber-400' : 'text-emerald-400') }}">overall {{ $sc->overall_score }} · {{ $sc->action }}</span>
                    </div>
                @empty
                    <p class="text-slate-500">Belum ada skor.</p>
                @endforelse
            </div>
        </section>

        {{-- 32.3/32.4 Katalog & harga --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 lg:col-span-2">
            <h2 class="font-semibold text-white mb-3">💰 Katalog & Harga Bertingkat (MOQ, periode, kontrak)</h2>
            <form method="POST" action="{{ route('supplier.items.store', $supplier) }}" class="grid grid-cols-4 gap-2 mb-4">
                @csrf
                <input name="supplier_sku" required placeholder="SKU pemasok" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="name" required placeholder="Nama item" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="internal_product_id" type="number" placeholder="ID produk/bahan internal" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <select name="currency" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm"><option>IDR</option></select>
                <input name="unit_price" type="number" step="0.0001" required placeholder="Harga satuan" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="min_qty" type="number" min="1" placeholder="Qty min" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="valid_from" type="date" required value="{{ now()->toDateString() }}" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Tambah</button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase">
                        <tr><th class="px-3 py-2 text-left">SKU</th><th class="px-3 py-2 text-left">Nama</th><th class="px-3 py-2 text-right">MOQ</th><th class="px-3 py-2">Harga bertingkat</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/60">
                        @forelse($supplier->items as $item)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs text-indigo-300">{{ $item->supplier_sku }}</td>
                                <td class="px-3 py-2 text-slate-200">{{ $item->name }}</td>
                                <td class="px-3 py-2 text-right text-slate-300">{{ $item->moq }}</td>
                                <td class="px-3 py-2 text-xs text-slate-400">
                                    @forelse($item->priceTiers as $tier)
                                        <span class="bg-slate-700/60 px-2 py-0.5 rounded mr-1">≥{{ $tier->min_qty }}: {{ $tier->currency }} {{ $tier->unit_price }} ({{ $tier->valid_from->format('d M y') }}–{{ $tier->valid_to?->format('d M y') ?? '∞' }})</span>
                                    @empty
                                        <span class="text-slate-500">Belum ada harga.</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500 text-sm">Belum ada item katalog.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 32.7 Risiko --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 lg:col-span-2">
            <h2 class="font-semibold text-white mb-3">⚠️ Flag Risiko Terbuka</h2>
            <div class="space-y-2">
                @forelse($supplier->riskFlags as $flag)
                    <div class="border-l-4 pl-3 py-1 {{ $flag->severity === 'critical' || $flag->severity === 'high' ? 'border-red-500' : ($flag->severity === 'medium' ? 'border-amber-500' : 'border-slate-500') }}">
                        <p class="text-xs uppercase text-slate-400">{{ $flag->type }} · {{ $flag->severity }}</p>
                        <p class="text-sm text-slate-200">{{ $flag->message }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Tidak ada flag risiko terbuka.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
