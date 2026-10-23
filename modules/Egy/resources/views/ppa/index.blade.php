@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Power Purchase Agreements (PPA)</h2>
            <p class="mt-1 text-sm text-gray-400">Kontrak Perjanjian Jual Beli Listrik Terbarukan & Penyelesaian Net Metering</p>

            <div class="mt-6 space-y-3">
                @forelse($contracts as $contract)
                    <div class="p-4 bg-gray-700/50 rounded-lg border border-gray-600 flex justify-between items-center">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-white font-medium">{{ $contract->contract_code }}</span>
                                <span class="text-xs px-2 py-0.5 rounded bg-indigo-900 text-indigo-200">{{ $contract->status }}</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-1">Offtaker: {{ $contract->offtaker_party_id }} • Generator: {{ $contract->generator_party_id }}</div>
                            <div class="text-xs text-emerald-400 mt-1">Kapasitas: {{ $contract->contracted_capacity_mw }} MW • Tarif: Rp {{ number_format($contract->tariff_per_kwh_minor, 0, ',', '.') }} / kWh</div>
                        </div>
                        <a href="{{ route('egy.ppa.show', $contract->id) }}" class="py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition">
                            Kelola & Settlement &rarr;
                        </a>
                    </div>
                @empty
                    <div class="text-gray-400 text-sm">Belum ada kontrak PPA terdaftar.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
