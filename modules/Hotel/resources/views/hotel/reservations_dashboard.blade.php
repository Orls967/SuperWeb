@extends('layouts.app')

@section('title', 'Manajemen Reservasi & Kamar')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Manajemen Reservasi & Ketersediaan Kamar</h1>

    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h2 class="text-lg font-semibold mb-4">Daftar Reservasi Aktif</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left">No. Reservasi</th>
                        <th class="px-4 py-2 text-left">Kamar</th>
                        <th class="px-4 py-2 text-left">Tipe</th>
                        <th class="px-4 py-2 text-left">Check-in</th>
                        <th class="px-4 py-2 text-left">Check-out</th>
                        <th class="px-4 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($reservations as $res)
                    <tr>
                        <td class="px-4 py-2 font-mono">{{ $res->reservation_number }}</td>
                        <td class="px-4 py-2">{{ $res->room->room_number ?? 'Belum Ditugaskan' }}</td>
                        <td class="px-4 py-2">{{ $res->roomType->name ?? '-' }}</td>
                        <td class="px-4 py-2">{{ $res->check_in_date }}</td>
                        <td class="px-4 py-2">{{ $res->check_out_date }}</td>
                        <td class="px-4 py-2"><span class="px-2 py-1 rounded text-xs font-semibold bg-gray-100">{{ $res->status }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
