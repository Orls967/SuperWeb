@extends('layouts.app')

@section('title', 'Portal Tamu Hotel')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Portal Tamu Hotel</h1>

    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold mb-4">Daftar Reservasi Anda</h2>
        @if($reservations->isEmpty())
            <p class="text-gray-500">Anda belum memiliki riwayat reservasi.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">No. Reservasi</th>
                            <th class="px-4 py-2 text-left">Properti</th>
                            <th class="px-4 py-2 text-left">Check-in</th>
                            <th class="px-4 py-2 text-left">Check-out</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-left">Saldo Folio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($reservations as $res)
                        <tr>
                            <td class="px-4 py-2 font-mono">{{ $res->reservation_number }}</td>
                            <td class="px-4 py-2">{{ $res->property->name ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $res->check_in_date }}</td>
                            <td class="px-4 py-2">{{ $res->check_out_date }}</td>
                            <td class="px-4 py-2"><span class="px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-800">{{ $res->status }}</span></td>
                            <td class="px-4 py-2 font-mono">IDR {{ number_format($res->folio->outstanding_balance ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
