@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="aviationCargoApp()">
    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-orange-500/20 text-orange-400 border border-orange-500/30">
                        Air Cargo & Dangerous Goods Acceptance
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Fase 177 • Algoritma A5 Certified • Integrasi Logistics</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Penerimaan Kargo Udara & Audit DG</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Validasi Dangerous Goods Kelas 1-9 IATA DGR, penimbangan berat kotor, dan penerbitan Air Waybill (AWB).
                </p>
            </div>
            <div>
                <a href="{{ route('aviation.ops.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors">
                    &larr; Ke Pusat Kontrol OCC
                </a>
            </div>
        </div>

        {{-- Form Penerimaan Kargo --}}
        <div class="bg-slate-800/60 border border-slate-700/80 rounded-2xl p-5 shadow-xl">
            <h2 class="text-base sm:text-lg font-bold text-white mb-3">Terima Konsinyasi Kargo Baru</h2>
            <form action="{{ route('aviation.cargo.receive') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Penerbangan</label>
                    <select name="flight_id" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">
                        @foreach($flights as $flight)
                            <option value="{{ $flight->id }}">{{ $flight->flight_number }} ({{ $flight->origin->iata_code ?? '-' }} &rarr; {{ $flight->destination->iata_code ?? '-' }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Berat Kotor (kg)</label>
                    <input type="number" name="gross_weight_kg" min="1" value="250" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Volume (CBM)</label>
                    <input type="number" step="0.01" name="volume_cbm" min="0.1" value="1.50" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Kelas DG (Dangerous Goods)</label>
                    <select name="dg_class" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">
                        <option value="">Non-DG (Kargo Umum)</option>
                        <option value="9">Kelas 9 - Lithium Battery</option>
                        <option value="3">Kelas 3 - Cairan Mudah Terbakar</option>
                        <option value="1">Kelas 1 - Bahan Peledak (Ditolak)</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs sm:text-sm font-semibold transition-colors shadow-lg shadow-orange-600/30">
                        Proses Penerimaan
                    </button>
                </div>
            </form>
        </div>

        {{-- Consignments Table --}}
        <div class="bg-slate-800/50 border border-slate-700/80 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base sm:text-lg font-bold text-white">Daftar Konsinyasi Kargo Aktif</h2>
                <span class="text-xs text-slate-400">Total: {{ $consignments->count() }} Konsinyasi</span>
            </div>

            @if($consignments->isEmpty())
                <div class="text-center py-12 border border-dashed border-slate-700 rounded-xl">
                    <span class="text-4xl block mb-2">📦</span>
                    <h3 class="text-sm font-semibold text-white">Belum Ada Kargo Diterima</h3>
                    <p class="text-xs text-slate-400 mt-1">Gunakan formulir di atas untuk menerima paket kargo pertama.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="bg-slate-900/60 text-slate-400 border-b border-slate-700/60">
                            <tr>
                                <th class="p-3">Nomor AWB</th>
                                <th class="p-3">Penerbangan</th>
                                <th class="p-3">Berat Kotor</th>
                                <th class="p-3">Volume</th>
                                <th class="p-3">DG Status</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($consignments as $c)
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="p-3 font-mono font-bold text-orange-300">{{ $c->awb_number }}</td>
                                    <td class="p-3 font-mono">{{ $c->flight->flight_number ?? '-' }}</td>
                                    <td class="p-3 font-mono">{{ number_format($c->gross_weight_kg) }} kg</td>
                                    <td class="p-3 font-mono">{{ number_format($c->volume_cbm, 2) }} m³</td>
                                    <td class="p-3">
                                        @if($c->is_dangerous_goods)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                                DG KLAS {{ $c->dg_class }}
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-700 text-slate-300">
                                                NON-DG
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                            {{ strtoupper($c->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function aviationCargoApp() {
    return {};
}
</script>
@endsection

