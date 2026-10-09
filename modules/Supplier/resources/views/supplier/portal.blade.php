@extends('layouts.app')

@section('title', 'Portal Pemasok')
@section('subtitle', $supplier->name.' · '.$supplier->code)

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">🏭 Portal Pemasok</h1>
            <p class="text-sm text-slate-400 mt-1">{{ $supplier->name }} · status {{ $supplier->status->label() }} · termin {{ $supplier->payment_terms_days }} hari</p>
        </div>
        <a href="{{ route('supplier.index') }}" class="text-sm text-indigo-400 hover:text-indigo-300">← Direktori (internal)</a>
    </div>

    @if(session('success'))<div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- PO terkait --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700"><h2 class="font-semibold text-white">📦 Purchase Order Masuk</h2></div>
            <table class="w-full text-sm">
                <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase"><tr><th class="px-4 py-2 text-left">Nomor</th><th class="px-4 py-2 text-left">Status</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2 text-left">Dijadwalkan</th></tr></thead>
                <tbody class="divide-y divide-slate-700/60">
                    @forelse($purchaseOrders as $po)
                        <tr><td class="px-4 py-2 font-mono text-xs text-indigo-300">{{ $po->number }}</td><td class="px-4 py-2 text-slate-300">{{ $po->status }}</td><td class="px-4 py-2 text-right">{{ number_format($po->grand_total) }}</td><td class="px-4 py-2 text-slate-500">{{ $po->expected_at }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500 text-sm">Belum ada PO.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- ASN --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="font-semibold text-white mb-3">🚚 Buat ASN (Advance Ship Notice)</h2>
            <form method="POST" action="{{ route('supplier.portal.asn.store') }}" class="space-y-2">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <input type="date" name="ship_date" required value="{{ now()->toDateString() }}" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <input type="date" name="expected_arrival" required value="{{ now()->addDays(3)->toDateString() }}" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                </div>
                <input name="tracking_ref" placeholder="Referensi tracking (opsional)" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <textarea name="lines_json" rows="4" required placeholder='[{"sku":"PNS-0001","qty":20,"batch":"B001"}] JSON baris kirim' class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm font-mono text-xs"></textarea>
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Simpan ASN</button>
            </form>

            <div class="mt-4 space-y-2">
                @forelse($asns as $asn)
                    <div class="flex items-center justify-between border-t border-slate-700/60 pt-2">
                        <div>
                            <span class="font-mono text-xs text-indigo-300">{{ $asn->asn_number }}</span>
                            <span class="ml-2 text-xs uppercase {{ $asn->status === 'shipped' ? 'text-emerald-400' : 'text-amber-400' }}">{{ $asn->status }}</span>
                            <p class="text-xs text-slate-500">berangkat {{ $asn->ship_date?->format('d M Y') }} · tiba {{ $asn->expected_arrival?->format('d M Y') }} · {{ count($asn->lines ?? []) }} baris</p>
                        </div>
                        @if($asn->status === 'draft')
                            <form method="POST" action="{{ route('supplier.portal.asn.ship', $asn) }}">
                                @csrf
                                <button class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-xs">Kirim</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada ASN.</p>
                @endforelse
            </div>
        </section>

        {{-- Dokumen / COA --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 lg:col-span-2">
            <h2 class="font-semibold text-white mb-3">📄 Unggah Sertifikat / COA / Dokumen</h2>
            <form method="POST" action="{{ route('supplier.portal.documents.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                @csrf
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Berkas (pdf/jpg/png, maks 5 MB)</label>
                    <input type="file" name="file" required class="w-full text-xs text-slate-400 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white file:text-xs">
                </div>
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Jenis</label>
                    <select name="kind" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                        <option value="coa">COA (sertifikat analisis)</option>
                        <option value="certificate">Sertifikat</option>
                        <option value="spec">Spesifikasi</option>
                        <option value="invoice">Invoice</option>
                        <option value="other">Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Label</label>
                    <input name="label" required placeholder="Mis. COA Batch B001" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                </div>
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Unggah</button>
            </form>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-2">
                @forelse($supplier->documents as $doc)
                    <div class="bg-slate-900/60 border border-slate-700 rounded-lg p-3">
                        <p class="text-xs uppercase text-slate-400">{{ $doc->kind }}</p>
                        <p class="text-sm text-slate-200">{{ $doc->label }}</p>
                        <p class="text-[10px] text-slate-500 mt-1">Dokumen #{{ $doc->document_id }} · {{ $doc->created_at?->format('d M Y H:i') }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada dokumen.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
