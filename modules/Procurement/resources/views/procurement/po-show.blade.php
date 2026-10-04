@extends('layouts.app')
@section('title', 'PO '.$po->number)
@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-white">{{ $po->title }}</h1>
                <span class="text-xs font-mono text-indigo-300 bg-indigo-500/10 px-2 py-1 rounded">{{ $po->number }}</span>
                <span class="text-xs px-2 py-1 rounded {{ $po->status === 'closed' ? 'bg-emerald-500/15 text-emerald-300' : ($po->status === 'cancelled' ? 'bg-red-500/15 text-red-300' : 'bg-amber-500/15 text-amber-300') }}">{{ $po->status }}</span>
                <span class="text-xs px-2 py-1 rounded bg-slate-700/60 text-slate-300">v{{ $po->version }} · {{ $po->kind }}</span>
            </div>
            <p class="text-sm text-slate-400 mt-2">Pemasok: <span class="text-slate-200">{{ $po->supplier?->name }}</span> · Estimasi tiba: {{ $po->expected_date?->format('d M Y') ?? '—' }} · Total: <span class="text-white font-semibold">{{ number_format($po->total_amount) }} {{ $po->currency }}</span></p>
        </div>
        <a href="{{ route('procurement.dashboard') }}" class="text-sm text-indigo-400 hover:text-indigo-300">← Dashboard</a>
    </div>

    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm"><thead class="bg-slate-900/60 text-xs text-slate-400 uppercase"><tr><th class="px-4 py-3 text-left">Deskripsi</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Harga Unit</th><th class="px-4 py-3 text-right">Line Total</th><th class="px-4 py-3 text-right">Diterima</th></tr></thead><tbody class="divide-y divide-slate-700/60">
            @foreach($po->lines as $line)
                <tr><td class="px-4 py-3 text-slate-200">{{ $line->description }}</td><td class="px-4 py-3 text-right">{{ $line->qty }} {{ $line->unit }}</td><td class="px-4 py-3 text-right">{{ number_format($line->unit_price) }}</td><td class="px-4 py-3 text-right text-white">{{ number_format($line->line_total) }}</td><td class="px-4 py-3 text-right {{ $line->received_qty >= $line->qty ? 'text-emerald-400' : 'text-amber-400' }}">{{ $line->received_qty }}/{{ $line->qty }}</td></tr>
            @endforeach
        </tbody><tfoot class="bg-slate-900/60"><tr><td class="px-4 py-3 font-semibold text-slate-200" colspan="3">Total</td><td class="px-4 py-3 text-right font-bold text-emerald-400">{{ number_format($po->total_amount) }}</td><td class="px-4 py-3"></td></tr></tfoot></table>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- 33.4 Revisi & tutup/batal --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-white mb-3">✏️ Revisi PO (versi baru via approval)</h2>
            @if(! in_array($po->status, ['received', 'closed', 'cancelled']))
                <form method="POST" action="{{ route('procurement.pos.revise', $po) }}" class="space-y-2">
                    @csrf
                    <input name="title" placeholder="Judul baru (opsional)" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <input name="expected_date" type="date" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <input name="reason" required placeholder="Alasan revisi (wajib)" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm w-full">Ajukan Revisi</button>
                </form>
            @else
                <p class="text-xs text-slate-500">PO dalam status akhir — tidak dapat direvisi.</p>
            @endif
            <div class="flex gap-2 mt-3">
                @if($po->status !== 'closed' && $po->status !== 'cancelled')
                    <form method="POST" action="{{ route('procurement.pos.close', $po) }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="reason" value="Semua item diterima">
                        <button class="w-full px-3 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-xs">Tutup PO</button>
                    </form>
                    <form method="POST" action="{{ route('procurement.pos.cancel', $po) }}" class="flex-1" onsubmit="return confirm('Batalkan PO dan lepas encumbrance?')">
                        @csrf
                        <input type="hidden" name="reason" value="Dibatalkan pembeli">
                        <button class="w-full px-3 py-2 bg-red-600/80 hover:bg-red-500 text-white rounded-lg text-xs">Batalkan</button>
                    </form>
                @endif
            </div>
        </section>

        {{-- 33.5 PO Impor --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-white mb-3">🚢 Profil Impor (simulasi)</h2>
            <form method="POST" action="{{ route('procurement.pos.import-profile', $po) }}" class="grid grid-cols-2 gap-2">
                @csrf
                <select name="incoterm" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    @foreach(['EXW','FOB','CFR','CIF','DAP','DDP'] as $incoterm)<option value="{{ $incoterm }}">{{ $incoterm }}</option>@endforeach
                </select>
                <input name="currency" value="USD" maxlength="3" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="fx_rate" type="number" step="0.01" value="15800" placeholder="Kurs ke IDR" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="origin_port" placeholder="Pelabuhan asal" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="destination_port" placeholder="Pelabuhan tujuan" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="freight_estimate_idr" type="number" value="0" placeholder="Freight (IDR)" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="insurance_estimate_idr" type="number" value="0" placeholder="Asuransi (IDR)" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="duty_estimate_idr" type="number" value="0" placeholder="Bea (IDR)" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Simpan Profil</button>
            </form>
        </section>

        {{-- 33.7 Inbound Shipment --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-white mb-3">🚚 Shipment Masuk (via kontrak Logistics)</h2>
            <form method="POST" action="{{ route('procurement.pos.inbound', $po) }}" class="space-y-2">
                @csrf
                <input name="origin_code" required placeholder="Kode hub asal (mis. BJM-HUB)" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="street" required placeholder="Jalan tujuan (gudang/pabrik)" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="city" required placeholder="Kota tujuan" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="consignee_name" placeholder="Nama penerima (opsional)" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm w-full">Buat Shipment Masuk</button>
            </form>
            <p class="text-xs text-slate-500 mt-2">Idempoten per PO — replay tidak membuat resi ganda.</p>
        </section>
    </div>

    @if($po->versions->isNotEmpty())
    <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-white mb-3">📜 Riwayat Versi PO</h2>
        <div class="space-y-2">
            @foreach($po->versions as $ver)
                <div class="border-l-2 border-indigo-500 pl-3 py-1 text-xs">
                    <span class="font-mono text-indigo-300">v{{ $ver->version }}</span>
                    <span class="text-slate-300 ml-2">{{ $ver->change_summary }}</span>
                    <span class="text-slate-500 ml-2">· {{ $ver->created_at?->format('d M Y H:i') }} · {{ $ver->status }}</span>
                </div>
            @endforeach
        </div>
    </section>
    @endif
</div>
@endsection
