@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('syariah.micro.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali ke Mikro</a>
                <span class="text-slate-300">/</span>
                <span class="font-mono text-sm text-emerald-700">Kalkulator Simulasi</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">Kalkulator Komparasi Akad Pembiayaan Mikro Syariah</h1>
            <p class="text-sm text-slate-600 mt-1">Simulasi interaktif perbandingan angsuran: Mudharabah Bertingkat (Bagi Hasil Riil) vs Murabahah Mikro (Margin Tetap) vs Qardh al-Hasan (Sosial/Kebajikan).</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-sm font-bold text-emerald-800 uppercase tracking-wider mb-2">Mudharabah Bertingkat</div>
            <div class="text-xs text-slate-500 mb-4">Bagi hasil riil berkeadilan sesuai nisbah omzet/laba nasabah. Kerugian non-kelalaian ditanggung modal shohibul maal.</div>
            <div class="p-3 bg-emerald-50 rounded border border-emerald-100">
                <div class="text-xs text-emerald-600">Fitur Utama</div>
                <div class="text-sm font-semibold text-emerald-900 mt-1">Nisbah Fleksibel &amp; Berkah</div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-sm font-bold text-blue-800 uppercase tracking-wider mb-2">Murabahah Mikro</div>
            <div class="text-xs text-slate-500 mb-4">Jual beli barang modal kerja dengan margin laba disepakati di muka. Angsuran tetap setiap bulan.</div>
            <div class="p-3 bg-blue-50 rounded border border-blue-100">
                <div class="text-xs text-blue-600">Fitur Utama</div>
                <div class="text-sm font-semibold text-blue-900 mt-1">Angsuran Pasti &amp; Transparan</div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-sm font-bold text-purple-800 uppercase tracking-wider mb-2">Qardh al-Hasan</div>
            <div class="text-xs text-slate-500 mb-4">Pembiayaan kebajikan murni untuk mustahik prasejahtera tanpa tambahan margin atau bunga riba.</div>
            <div class="p-3 bg-purple-50 rounded border border-purple-100">
                <div class="text-xs text-purple-600">Fitur Utama</div>
                <div class="text-sm font-semibold text-purple-900 mt-1">Bebas Biaya &amp; Pemberdayaan</div>
            </div>
        </div>
    </div>
</div>
@endsection

