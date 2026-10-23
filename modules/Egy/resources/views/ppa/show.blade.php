@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-white">Detail Kontrak PPA: {{ $contract->contract_code }}</h2>
                    <p class="mt-1 text-sm text-gray-400">Tarif: Rp {{ number_format($contract->tariff_per_kwh_minor, 0, ',', '.') }} / kWh | Kapasitas: {{ $contract->contracted_capacity_mw }} MW</p>
                </div>
                <a href="{{ route('egy.ppa.index') }}" class="text-xs text-gray-400 hover:text-white">&larr; Kembali</a>
            </div>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 bg-gray-700/50 p-6 rounded-lg">
                <h3 class="text-md font-medium text-white mb-4">Proses Tagihan Settlement Net Metering</h3>
                <form action="{{ route('egy.ppa.settle', $contract->id) }}" method="POST" class="flex gap-4 items-end">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-xs text-gray-300 mb-1">Total Energi Terkirim (kWh)</label>
                        <input type="number" name="delivered_kwh" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="10000">
                    </div>
                    <button type="submit" class="py-2 px-6 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded transition">
                        Eksekusi Settlement Finansial
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
