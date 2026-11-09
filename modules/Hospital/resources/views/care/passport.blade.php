<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Human Passport Kesehatan (Fase 87.2)
            </h2>
            <div class="flex items-center gap-2">
                @if($isChainValid)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        ✓ Rantai Hash Kriptografis Sah
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">
                        ⚠️ Rantai Hash Korup / Tidak Sah
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Kartu Identitas Paspor -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b pb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">{{ $patient->name }}</h3>
                    <div class="text-sm font-mono text-gray-500 mt-1">MRN: {{ $patient->mrn }}</div>
                    <div class="text-xs text-gray-400 mt-1">Golongan Darah: <strong class="text-gray-700">{{ $patient->blood_type }}</strong></div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-gray-400 uppercase font-semibold">Current Passport Hash</div>
                    <div class="font-mono text-xs bg-gray-100 p-2 rounded text-gray-700 break-all max-w-sm mt-1">
                        {{ $patient->passport_hash }}
                    </div>
                </div>
            </div>

            <!-- Bagian Klinis Terenkripsi -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <h4 class="font-bold text-gray-800 text-sm mb-2">Riwayat Alergi (Terenkripsi At-Rest)</h4>
                    @if(empty($patient->encrypted_allergies))
                        <p class="text-xs text-gray-500">Tidak ada alergi terdokumentasi.</p>
                    @else
                        <ul class="list-disc list-inside text-xs text-gray-700 space-y-1">
                            @foreach($patient->encrypted_allergies as $allergy)
                                <li>{{ $allergy }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <h4 class="font-bold text-gray-800 text-sm mb-2">Diagnosis Kronis (Terenkripsi At-Rest)</h4>
                    @if(empty($patient->encrypted_chronic_diagnoses))
                        <p class="text-xs text-gray-500">Tidak ada riwayat penyakit kronis.</p>
                    @else
                        <ul class="list-disc list-inside text-xs text-gray-700 space-y-1">
                            @foreach($patient->encrypted_chronic_diagnoses as $diag)
                                <li>{{ $diag }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <!-- Riwayat Rantai Peristiwa (CanonicalHashChain Events) -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h4 class="font-bold text-gray-800 text-md mb-4">Mata Rantai Peristiwa Paspor (CanonicalHashChain)</h4>
            @if($patient->passportEvents->isEmpty())
                <p class="text-sm text-gray-500 text-center py-6">Belum ada rekaman peristiwa paspor.</p>
            @else
                <div class="space-y-3">
                    @foreach($patient->passportEvents as $ev)
                        <div class="p-3 bg-gray-50 rounded border border-gray-200 text-xs flex justify-between items-center">
                            <div>
                                <span class="font-bold text-indigo-700">#{{ $ev->sequence_no }} [{{ $ev->event_type }}]</span>
                                <span class="text-gray-500 ml-2">{{ $ev->recorded_at->format('Y-m-d H:i:s') }} UTC</span>
                                <div class="text-gray-400 font-mono mt-0.5">Prev: {{ substr($ev->prev_hash, 0, 16) }}...</div>
                            </div>
                            <div class="text-right font-mono text-gray-600">
                                Hash: {{ substr($ev->current_hash, 0, 16) }}...
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

