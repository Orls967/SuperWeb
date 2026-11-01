@extends('layouts.app')

@section('title', 'Vehicle-to-Grid (V2G) Discharge Feed-In')
@section('subtitle', 'Injeksi Balik Energi Baterai ke Microgrid Saat Permintaan Puncak & Akumulasi Kredit Pelanggan')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Injeksi Balik Daya V2G</h3>
            <form action="{{ route('ev.smart-grid.discharge-v2g') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Stasiun Tujuan Injeksi</label>
                    <select name="station_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                        @foreach($stations as $st)
                            <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->station_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Vehicle ID Sumber</label>
                    <input type="number" name="vehicle_id" placeholder="misal: 101" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Energi Di-discharge (Wh)</label>
                    <input type="number" name="discharged_wh" value="15000" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Feed-In Tariff (IDR Integer / kWh)</label>
                    <input type="number" name="rate_idr" value="2800" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-bold rounded-xl text-sm">
                    Injeksi Daya ke Grid & Klaim Kredit
                </button>
            </form>
        </div>

        <div class="md:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-3">Keuntungan Ekonomi V2G untuk Pengemudi & Pemilik Armada</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-slate-300">
                <div class="p-4 rounded-xl bg-slate-900/50 border border-slate-800">
                    <p class="font-bold text-emerald-400 mb-1">Arbitrase Energi Cerdas</p>
                    <p class="text-slate-400">Isi daya di malam hari saat tarif Rp 1.650/kWh, lalu jual kembali ke grid saat jam sibuk di harga Rp 2.800/kWh, menghasilkan margin bersih Rp 1.150/kWh.</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/50 border border-slate-800">
                    <p class="font-bold text-cyan-400 mb-1">Stabilisasi Trafo Lokal</p>
                    <p class="text-slate-400">Membantu mengurangi kelebihan beban transformator gardu induk saat peak hour tanpa investasi genset diesel tambahan.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
