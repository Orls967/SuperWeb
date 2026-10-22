@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 min-h-screen text-gray-100">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Header -->
        <div class="p-6 bg-gray-800/80 backdrop-blur rounded-2xl shadow-xl border border-gray-700">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                        <span class="p-2 bg-rose-500/20 text-rose-400 rounded-lg">🔬</span>
                        Katalog Lab LOINC & Pindai Kustodi 3-Hop (Fase 105)
                    </h1>
                    <p class="text-gray-400 text-sm mt-1">Standar 10.000 Parameter LOINC, Rantai Kustodi Spesimen Hash-Chain & Notifikasi Panic Nilai Kritis</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('hospital.laboratory.outsourcing.index') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">
                        Kliring Lab Mitra →
                    </a>
                    <a href="{{ route('hospital.imaging.pacs.index') }}" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-sm font-medium transition">
                        DICOM PACS →
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/60 border border-emerald-500/50 rounded-xl text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('panic_evaluation'))
                <div class="mt-4 p-4 bg-amber-900/50 border border-amber-500/50 rounded-xl text-amber-200 text-sm">
                    <div class="font-semibold text-white">Hasil Verifikasi Nilai Kritis Lab:</div>
                    <div class="mt-1">Status Panic: <span class="font-bold {{ (session('panic_evaluation')['is_panic'] ?? false) ? 'text-rose-400' : 'text-emerald-400' }}">{{ (session('panic_evaluation')['is_panic'] ?? false) ? '🚨 NILAI KRITIS / PANIC DETECTED' : 'NORMAL' }}</span></div>
                    <div class="mt-1">Protokol Notifikasi: <span class="font-bold uppercase text-white">{{ session('panic_evaluation')['protocol'] ?? 'NONE' }}</span></div>
                    <div class="mt-1 text-xs text-gray-300">{{ session('panic_evaluation')['message'] ?? '' }}</div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Pindai Rantai Kustodi 3-Hop Form -->
            <div class="p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <span>⛓️</span> Pindai Kustodi Spesimen (3-Hop Cryptographic Chain)
                </h2>
                <p class="text-xs text-gray-400">Pencatatan rantai perpindahan spesimen: Phlebotomy → Courier Cold-Chain → Central Lab Analyzer.</p>

                <form method="POST" action="{{ route('hospital.laboratory.specimens.custody') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-300 mb-1">Hop Sequence #</label>
                            <input type="number" name="hop_number" value="1" min="1" max="10" required class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-300 mb-1">Petugas Asal (From Handler)</label>
                            <input type="text" name="from_handler" value="Nurse Maya (Phlebotomy)" required class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Petugas Penerima (To Handler)</label>
                        <input type="text" name="to_handler" value="Courier Dodi (Cooler Box A-12)" required class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Catatan Kustodi / Suhu Sampel</label>
                        <input type="text" name="notes" value="Suhu terjaga 4.2°C, segel tamper-evident utuh" class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-sm font-semibold transition shadow-lg">
                        Rekam Hop Rantai Kustodi
                    </button>
                </form>
            </div>

            <!-- Evaluasi Nilai Kritis Form -->
            <div class="p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <span>⚡</span> Verifikasi & Hold Pathologist Nilai Kritis
                </h2>
                <p class="text-xs text-gray-400">Auto-hold hasil kritis lab dan trigger protokol eskalasi panggilan darurat pathologist.</p>

                <form method="POST" action="{{ route('hospital.laboratory.results.verify') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Kode Parameter Uji (Test Code)</label>
                        <input type="text" name="test_code" value="POTASSIUM" required class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                        <span class="text-[10px] text-gray-500">Contoh: POTASSIUM, GLUCOSE_FASTING, TROPONIN_I, HEMOGLOBIN</span>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Nilai Terukur (Numeric Value)</label>
                        <input type="number" step="0.01" name="numeric_value" value="6.9" required class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold transition shadow-lg">
                        Verifikasi Nilai Kritis Terhadap Panic Range
                    </button>
                </form>
            </div>
        </div>

        <!-- Tabel Katalog Parameter LOINC -->
        <div class="p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <span>📋</span> Katalog Parameter Uji Laboratorium (LOINC Standard)
                </h2>
                <span class="text-xs text-gray-400">{{ count($catalog) }} parameter ditampilkan</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="bg-gray-900/60 text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="p-3">Kode Test</th>
                            <th class="p-3">Nama Pemeriksaan Lab</th>
                            <th class="p-3">Kategori</th>
                            <th class="p-3">Rentang Rujukan</th>
                            <th class="p-3">Satuan</th>
                            <th class="p-3">Tarif IDR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @forelse($catalog as $item)
                            <tr class="hover:bg-gray-700/40">
                                <td class="p-3 font-mono font-medium text-rose-400">{{ $item->test_code }}</td>
                                <td class="p-3 font-medium text-white">{{ $item->test_name }}</td>
                                <td class="p-3 text-xs">{{ $item->category }}</td>
                                <td class="p-3 text-xs">{{ $item->reference_min ?? '0' }} - {{ $item->reference_max ?? '-' }}</td>
                                <td class="p-3 text-xs text-gray-400">{{ $item->unit }}</td>
                                <td class="p-3 font-mono text-emerald-400">Rp {{ number_format($item->price_idr ?? 0, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-6 text-center text-gray-500">Belum ada item katalog parameter lab tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
