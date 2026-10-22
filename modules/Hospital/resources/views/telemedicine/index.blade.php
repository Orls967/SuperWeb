@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 min-h-screen text-gray-100">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Header -->
        <div class="p-6 bg-gray-800/80 backdrop-blur rounded-2xl shadow-xl border border-gray-700">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                        <span class="p-2 bg-emerald-500/20 text-emerald-400 rounded-lg">🩺</span>
                        Telemedicine & Digital E-Prescription Suite (Fase 104)
                    </h1>
                    <p class="text-gray-400 text-sm mt-1">Konsultasi Jarak Jauh Terenkripsi, Triase Multi-Tingkat & Evaluasi Alergi Human Passport</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 bg-emerald-950 text-emerald-300 border border-emerald-700/50 rounded-full text-xs font-semibold">
                        SLA Emergency < 3 Min
                    </span>
                    <a href="{{ route('hospital.epharmacy.subscriptions.index') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">
                        Langganan Obat Kronis →
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/60 border border-emerald-500/50 rounded-xl text-emerald-200 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('evaluation'))
                <div class="mt-4 p-4 bg-purple-900/50 border border-purple-500/50 rounded-xl text-purple-200 text-sm">
                    <div class="font-semibold text-white">Hasil Evaluasi Keselamatan E-Resep:</div>
                    <div class="mt-1">Tingkat Risiko: <span class="font-bold uppercase text-amber-300">{{ session('evaluation')['risk_level'] ?? 'UNKNOWN' }}</span></div>
                    <div class="mt-1">Status Keamanan: <span class="font-bold {{ (session('evaluation')['is_safe'] ?? false) ? 'text-emerald-400' : 'text-rose-400' }}">{{ (session('evaluation')['is_safe'] ?? false) ? 'AMAN' : 'KONTRAINDIKASI / BAHAYA' }}</span></div>
                    @if(!empty(session('evaluation')['reasons']))
                        <ul class="list-disc list-inside mt-2 text-xs text-rose-300 space-y-1">
                            @foreach(session('evaluation')['reasons'] as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Evaluasi Alergi Form -->
            <div class="p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <span>🛡️</span> Evaluasi Interaksi & Alergi Obat
                </h2>
                <p class="text-xs text-gray-400">Verifikasi otomatis resep terhadap Human Passport (Alergi Silang & Klirens Ginjal CrCl).</p>

                <form method="POST" action="{{ route('hospital.telemedicine.prescriptions.evaluate') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Kode Obat (Drug Code)</label>
                        <input type="text" name="drug_code" value="AMOXICILLIN" required class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                        <span class="text-[10px] text-gray-500">Contoh: AMOXICILLIN, METFORMIN, IBUPROFEN</span>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Dosis (mg)</label>
                        <input type="number" name="dosage_mg" value="500" min="1" required class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">CrCl Ginjal (mL/min, opsional)</label>
                        <input type="number" name="crcl" value="25" min="0" class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold transition shadow-lg">
                        Jalankan Evaluasi Paspor
                    </button>
                </form>
            </div>

            <!-- Antrean Teleconsult -->
            <div class="lg:col-span-2 p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span>📞</span> Antrean Sesi Telekonsultasi Aktif
                    </h2>
                    <span class="text-xs text-gray-400">{{ count($teleConsults) }} sesi tercatat</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-900/60 text-xs text-gray-400 uppercase">
                            <tr>
                                <th class="p-3">Kode Sesi</th>
                                <th class="p-3">Pasien</th>
                                <th class="p-3">Kanal</th>
                                <th class="p-3">Triase</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($teleConsults as $consult)
                                <tr class="hover:bg-gray-700/40">
                                    <td class="p-3 font-mono font-medium text-emerald-400">{{ $consult->consult_code }}</td>
                                    <td class="p-3">{{ $consult->patient->name ?? 'Pasien #' . $consult->patient_id }}</td>
                                    <td class="p-3 text-xs">{{ $consult->channel_type ?? 'WebRTC' }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-[11px] font-semibold rounded {{ $consult->triage_category === 'RED' ? 'bg-rose-900 text-rose-200' : 'bg-blue-900 text-blue-200' }}">
                                            {{ $consult->triage_category ?? 'GREEN' }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-gray-700 text-gray-300">
                                            {{ $consult->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-gray-500">Belum ada sesi telekonsultasi tercatat saat ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- E-Resep Terkini -->
        <div class="p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <span>💊</span> E-Resep Digital Terkini
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($prescriptions as $rx)
                    <div class="p-4 bg-gray-900/60 rounded-xl border border-gray-700 space-y-2">
                        <div class="flex justify-between items-start">
                            <span class="font-mono text-xs font-semibold text-indigo-400">{{ $rx->prescription_code ?? 'RX-'.$rx->id }}</span>
                            <span class="px-2 py-0.5 text-[10px] rounded bg-emerald-950 text-emerald-300 border border-emerald-800">
                                {{ $rx->status ?? 'ACTIVE' }}
                            </span>
                        </div>
                        <div class="text-sm font-medium text-white">{{ $rx->drug_name ?? 'Obat Resep' }}</div>
                        <div class="text-xs text-gray-400">Dosis: {{ $rx->dosage ?? '1x1' }} • {{ $rx->instructions ?? 'Sesuai petunjuk dokter' }}</div>
                    </div>
                @empty
                    <div class="col-span-full p-6 text-center text-gray-500 bg-gray-900/30 rounded-xl">
                        Belum ada e-resep digital yang diterbitkan.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
