@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-gray-800">Blue Carbon & Restorasi Mangrove Pesisir</h1>
            <p class="text-muted">Proyek silvofishery dan sertifikasi kredit karbon pesisir berbasis Verra VM0033.</p>
        </div>
        <div>
            <span class="badge badge-info px-3 py-2">Total Terbit: {{ number_format($totalCreditsIssued, 2) }} tCO2e</span>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Proyek Restorasi Mangrove Aktif</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Kode Proyek</th>
                            <th>Nama Proyek</th>
                            <th>Lokasi Pesisir</th>
                            <th>Luas (Ha)</th>
                            <th>Spesies Dominan</th>
                            <th>Pohon Hidup</th>
                            <th>Standar Verifikasi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projects as $proj)
                            <tr>
                                <td class="font-weight-bold text-monospace">{{ $proj->project_code }}</td>
                                <td>{{ $proj->project_name }}</td>
                                <td>{{ $proj->coastal_regency }}</td>
                                <td>{{ number_format($proj->restoration_area_hectares, 1) }} ha</td>
                                <td><em>{{ $proj->dominant_mangrove_species }}</em></td>
                                <td>{{ number_format($proj->trees_planted_count) }} pohon</td>
                                <td><span class="badge badge-light border">{{ $proj->verra_registry_id ?? 'VERRA_VM0033' }}</span></td>
                                <td><span class="badge badge-success">{{ $proj->status }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Belum ada proyek restorasi mangrove.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($projects->hasPages())
            <div class="card-footer bg-white">
                {{ $projects->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
