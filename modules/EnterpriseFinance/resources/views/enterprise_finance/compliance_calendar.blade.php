@extends('layouts.app')

@section('title', 'Kalender Kepatuhan Regulasi Korporat')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Kalender Batas Waktu Pelaporan Otoritas & Regulasi (54.6)</h5>
            <span class="badge bg-info text-dark">Regulatory Radar</span>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Agenda tenggat waktu kepatuhan hukum dan pelaporan berkala korporat ke Otoritas Jasa Keuangan (OJK), Direktorat Jenderal Pajak (DJP), Kementerian BUMN, dan Bursa Efek Indonesia (BEI).
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Kode Agenda</th>
                            <th scope="col">Agenda Pelaporan</th>
                            <th scope="col">Regulator / Otoritas</th>
                            <th scope="col">Jatuh Tempo</th>
                            <th scope="col">Penanggung Jawab (PIC)</th>
                            <th scope="col">Status Kepatuhan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deadlines ?? [] as $d)
                            <tr>
                                <td class="font-monospace fw-bold">{{ $d->item_code }}</td>
                                <td>{{ $d->title }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $d->regulatory_body }}</span></td>
                                <td class="font-monospace">{{ $d->due_date }}</td>
                                <td>{{ ucfirst($d->assigned_role) }}</td>
                                <td>
                                    <span class="badge {{ $d->status === 'completed' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ strtoupper($d->status ?? 'PENDING') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada agenda regulasi yang dijadwalkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
