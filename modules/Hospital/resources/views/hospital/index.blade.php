@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Hospital" />

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Hospital, EMR & Clinical Trial Operations</h2>
            <p class="mt-1 text-sm text-gray-400">Manajemen Ranjang Pasien, Rekam Medis (EMR) & Uji Klinis Terintegrasi Ledger</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Beds -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Kapasitas Tempat Tidur (Beds)</h3>
                    <div class="space-y-2">
                        @forelse($beds as $bed)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                                <div>
                                    <div class="text-white font-medium">{{ $bed->bed_code }} ({{ $bed->ward_name }})</div>
                                    <div class="text-xs text-gray-400">Kelas: {{ $bed->bed_class }} • Ruang {{ $bed->room_number }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full {{ $bed->status === 'AVAILABLE' ? 'bg-emerald-900 text-emerald-200' : 'bg-amber-900 text-amber-200' }}">
                                        {{ $bed->status }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada data bed.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Patients -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Pasien Terdaftar</h3>
                    <div class="space-y-2">
                        @forelse($patients as $patient)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                                <div>
                                    <div class="text-white font-medium">{{ $patient->name }}</div>
                                    <div class="text-xs text-gray-400">MRN: {{ $patient->mrn }} • Gol: {{ $patient->blood_type }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada pasien terdaftar.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Clinical Trials -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Uji Klinis & Milestone Billing</h3>
                    <div class="space-y-3">
                        @forelse($trials as $trial)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="text-white font-medium">{{ $trial->title }}</div>
                                <div class="text-xs text-gray-400 mb-2">Kode: {{ $trial->trial_code }} • Sponsor: {{ $trial->sponsor_name }} • Subjek: {{ $trial->subjects->count() }}/{{ $trial->target_subjects }}</div>
                                
                                <form action="{{ route('hospital.trials.bill', $trial) }}" method="POST" class="space-y-2 pt-2 border-t border-gray-700">
                                    @csrf
                                    <input type="hidden" name="milestone_name" value="Phase Completion 1">
                                    <input type="hidden" name="amount_idr" value="100000000">
                                    <button type="submit" class="w-full py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition">
                                        Tagih Milestone Sponsor (Rp 100.000.000)
                                    </button>
                                </form>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada uji klinis aktif.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
