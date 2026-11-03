@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h1>Terminal Operasi Pelabuhan</h1>
    <p class="text-muted">Manajemen tambatan, yard container, dan penagihan terminal terintegrasi.</p>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 my-4">
        <div class="card p-4">
            <h3>Tambatan Aktif</h3>
            <p class="text-2xl font-bold">{{ $berthsCount }}</p>
        </div>
        <div class="card p-4">
            <h3>Kunjungan Kapal</h3>
            <p class="text-2xl font-bold">{{ $activeCallsCount }}</p>
        </div>
        <div class="card p-4">
            <h3>Kontainer di Lapangan</h3>
            <p class="text-2xl font-bold">{{ $yardOccupancy }} TEU</p>
        </div>
        <div class="card p-4">
            <h3>Total Tagihan Terbit</h3>
            <p class="text-2xl font-bold">Rp {{ number_format($totalBills, 0, ',', '.') }}</p>
        </div>
    </div>
</div>
@endsection
