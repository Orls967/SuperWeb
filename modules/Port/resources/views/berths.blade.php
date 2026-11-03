@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2>Manajemen Tambatan (Berth Allocation)</h2>
    <div class="berth-list my-4">
        @foreach($berths as $berth)
            <div class="card p-3 mb-2">
                <h4>{{ $berth->code }} - {{ $berth->name }}</h4>
                <p>Panjang: {{ $berth->length_meters }}m | Draft Max: {{ $berth->max_draft_meters }}m | Status: {{ $berth->status }}</p>
                <h5>Jadwal Sandar:</h5>
                <ul>
                    @forelse($berth->vesselCalls as $call)
                        <li>{{ $call->vessel_name }} ({{ $call->call_number }}) - Status: {{ $call->status }}</li>
                    @empty
                        <li class="text-muted">Tidak ada kapal yang sedang sandar/terjadwal.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>
</div>
@endsection
