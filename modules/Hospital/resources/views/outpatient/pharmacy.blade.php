@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Antrean Resep Farmasi Rawat Jalan</h2>
            <p class="mt-1 text-sm text-gray-400">Verifikasi dan penyerahan obat menggunakan metode FEFO (First-Expired, First-Out).</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mt-4 p-4 bg-rose-900/50 border border-rose-500 rounded text-rose-200 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700 text-sm text-left text-gray-300">
                    <thead class="bg-gray-700/50 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-6 py-3">No. Resep</th>
                            <th class="px-6 py-3">Pasien</th>
                            <th class="px-6 py-3">Dokter Penulis</th>
                            <th class="px-6 py-3">Item Resep</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @forelse($prescriptions as $rx)
                            <tr class="hover:bg-gray-750">
                                <td class="px-6 py-4 font-mono font-medium text-white">{{ $rx->prescription_code }}</td>
                                <td class="px-6 py-4">{{ $rx->encounter?->patient?->name ?? 'Pasien' }}</td>
                                <td class="px-6 py-4">{{ $rx->doctor?->user?->name ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <ul class="list-disc list-inside space-y-1 text-xs">
                                        @foreach($rx->items as $item)
                                            <li>{{ $item->drug_name }} ({{ $item->drug_code }}) - Jml: {{ $item->quantity }} ({{ $item->dosage_instructions }})</li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 text-xs rounded-full bg-amber-900/60 text-amber-300 border border-amber-600">
                                        {{ $rx->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if($rx->status === 'PRESCRIBED')
                                        <form action="{{ route('hospital.outpatient.pharmacy.dispense', $rx) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded">
                                                Serahkan Obat (FEFO)
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    Tidak ada antrean resep yang perlu diserahkan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
