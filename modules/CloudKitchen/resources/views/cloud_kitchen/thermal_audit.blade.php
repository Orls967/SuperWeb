@extends('layouts.app')

@section('title', 'HACCP Thermal Seal & Food Safety Audit')
@section('subtitle', 'Pemeriksaan Segel Keamanan Anti-Tamper & Pemantauan Rantai Dingin/Panas (Cold/Hot Chain)')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Uji Integritas Segel HACCP</h3>
            <form action="{{ route('cloud_kitchen.operations.audit-thermal') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Pilih Order Makanan</label>
                    <select name="order_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 font-mono text-xs">
                        @foreach($inTransitOrders as $io)
                            <option value="{{ $io->id }}">{{ $io->order_code }} ({{ $io->status }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Barcode Segel Keamanan</label>
                    <input type="text" name="seal_barcode" value="SEAL-HACCP-{{ rand(100000, 999999) }}" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 font-mono text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Suhu Terukur (°C)</label>
                    <input type="number" step="0.1" name="temperature_c" value="68.5" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 font-mono">
                </div>
                <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-sm">
                    Verifikasi Keamanan Makanan
                </button>
            </form>
        </div>

        <div class="md:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-3">Standar Kepatuhan HACCP AutoServe</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-slate-300">
                <div class="p-4 rounded-xl bg-slate-900/50 border border-slate-800">
                    <p class="font-bold text-amber-400 mb-1">Suhu Makanan Hangat (Hot Holding)</p>
                    <p class="text-slate-400">Wajib dijaga di atas 60°C selama proses pengiriman untuk mencegah perkembangbiakan bakteri patogen.</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/50 border border-slate-800">
                    <p class="font-bold text-cyan-400 mb-1">Segel Anti-Rusak (Tamper-Evident)</p>
                    <p class="text-slate-400">Setiap kotak makanan disegel dengan label berefek residu void. Jika dibuka paksa, barcode otomatis tidak valid.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
