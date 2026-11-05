@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-4">Detail Petak: {{ $plot->plot_code }}</h1>
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <p><strong>Nama Konsesi:</strong> {{ $plot->concession_name }}</p>
        <p><strong>Luas:</strong> {{ $plot->area_hectares }} Ha</p>
        <p><strong>Kuota Tahunan:</strong> {{ $plot->annual_quota_m3 }} m3</p>
        <p><strong>Realisasi Tebang:</strong> {{ $plot->harvested_volume_m3 }} m3</p>
    </div>
</div>
@endsection
