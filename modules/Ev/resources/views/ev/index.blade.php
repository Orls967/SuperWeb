@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Ekosistem EV & Jaringan Charging SPKLU</h2>
            <p class="mt-1 text-sm text-gray-400">Reservasi Slot Pengisian Daya, Dynamic Battery Passport & Settlement Multi-Tariff</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Stations & Chargers -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Stasiun & Titik Pengisian SPKLU</h3>
                    <div class="space-y-3">
                        @forelse($chargers as $charger)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $charger->charger_code }} ({{ $charger->type }})</div>
                                    <div class="text-xs text-gray-400">Stasiun: {{ $charger->station?->name }} • Kapasitas: {{ $charger->max_kw }} kW</div>
                                    <div class="text-xs text-indigo-400 mt-1">Status: {{ $charger->status }}</div>
                                </div>
                                <div>
                                    <form action="{{ route('ev.chargers.reserve', $charger) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="vehicle_id" value="1">
                                        <input type="hidden" name="duration_minutes" value="30">
                                        <button type="submit" class="py-1.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded transition">
                                            Reservasi Slot
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada charger SPKLU terdaftar.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Active Sessions -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Sesi Charging Terkini</h3>
                    <div class="space-y-2">
                        @forelse($sessions as $sess)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $sess->session_code }}</div>
                                    <div class="text-xs text-gray-400">Charger: {{ $sess->charger?->charger_code }} • Tarif: Rp {{ number_format($sess->tariff_per_kwh_idr) }}/kWh</div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full {{ $sess->status === 'completed' ? 'bg-emerald-900 text-emerald-200' : 'bg-blue-900 text-blue-200' }}">
                                        {{ $sess->status }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada riwayat sesi charging.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
