@extends('layouts.app')

@section('title', 'Dashboard Night Audit')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Night Audit & Penutupan Harian</h1>
        <form method="POST" action="{{ route('hotel.night-audit.run') }}">
            @csrf
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded shadow">
                Jalankan Night Audit Sekarang
            </button>
        </form>
    </div>

    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h2 class="text-lg font-semibold mb-4">Status Tanggal Bisnis Hotel</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <span class="text-gray-500 text-sm">Tanggal Bisnis Aktif</span>
                <p class="text-2xl font-bold font-mono">{{ $record->current_business_date }}</p>
            </div>
            <div>
                <span class="text-gray-500 text-sm">Status Audit</span>
                <p class="text-xl font-semibold uppercase">{{ $record->status }}</p>
            </div>
            <div>
                <span class="text-gray-500 text-sm">Checkpoint Terakhir</span>
                <p class="text-lg font-mono">{{ $record->last_checkpoint ?? 'Belum Dimulai' }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
