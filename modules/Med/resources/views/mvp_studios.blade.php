@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col space-y-6">
        <div class="p-6 bg-gray-800 shadow rounded-lg border border-gray-700">
            <h1 class="text-2xl font-bold text-white">Studio Management & Discrete Slot Claims</h1>
            <p class="mt-2 text-sm text-gray-400">Pemesanan fasilitas studio dengan penguncian slot diskrit 30 menit.</p>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <div class="overflow-x-auto bg-gray-800 rounded-lg border border-gray-700 p-4">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="bg-gray-700 text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="p-3">Kode Studio</th>
                            <th class="p-3">Nama</th>
                            <th class="p-3">Tipe Fasilitas</th>
                            <th class="p-3">Kota</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($studios ?? [] as $studio)
                            <tr class="border-b border-gray-700">
                                <td class="p-3 font-mono">{{ $studio->studio_code }}</td>
                                <td class="p-3">{{ $studio->name }}</td>
                                <td class="p-3">{{ $studio->facility_type }}</td>
                                <td class="p-3">{{ $studio->location_city }}</td>
                                <td class="p-3">{{ $studio->status }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-4 text-center text-gray-500">Tidak ada studio aktif.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

