@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">B2B Fleet Leasing & SLA Management</h2>
            <p class="mt-1 text-sm text-gray-400">Kontrak Sewa Unit B2B, Amortisasi Bulanan Otomatis & Kompensasi Downtime SLA</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Contracts -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Kontrak Leasing Aktif</h3>
                    <div class="space-y-3">
                        @forelse($contracts as $c)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $c->contract_number }}</div>
                                    <div class="text-xs text-gray-400">Durasi: {{ $c->duration_months }} bulan • Unit: {{ $c->total_units }} armada</div>
                                    <div class="text-xs text-indigo-400 mt-1">Sewa: Rp {{ number_format($c->monthly_rental_idr) }}/bln • Teramortisasi: Rp {{ number_format($c->accumulated_amortized_idr) }}</div>
                                </div>
                                <div>
                                    <form action="{{ route('fleet.contracts.amortize', $c) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="month_index" value="1">
                                        <button type="submit" class="py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition">
                                            Amortisasi Bulan 1
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada kontrak leasing aktif.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Assigned Units -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Unit Armada Terpasang</h3>
                    <div class="space-y-2">
                        @forelse($units as $u)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">Kendaraan #{{ $u->vehicle_id }}</div>
                                    <div class="text-xs text-gray-400">Kontrak: {{ $u->contract?->contract_number }} • Max KM/Tahun: {{ number_format($u->max_km_per_year) }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full bg-emerald-900 text-emerald-200">
                                        {{ $u->status }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada unit armada ditugaskan.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
