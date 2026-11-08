@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-gray-800">Manajemen Budidaya Akuakultur & Tambak</h1>
            <p class="text-muted">Pemantauan siklus benur SPF, biomassa, kualitas air IoT, dan estimasi FCR.</p>
        </div>
        <div>
            <span class="badge badge-success px-3 py-2">Fase 171 — ASC/MSC Compliant</span>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Siklus Batch Tambak Aktif</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Batch Code</th>
                            <th>Pond / Tambak</th>
                            <th>Spesies</th>
                            <th>Hatchery Asal</th>
                            <th>Tgl Tebar</th>
                            <th>Populasi</th>
                            <th>Estimasi Biomassa</th>
                            <th>Status Siklus</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $batch)
                            <tr>
                                <td class="font-weight-bold text-monospace">{{ $batch->batch_code }}</td>
                                <td>Pond #{{ $batch->pond_id }}</td>
                                <td><em>{{ $batch->species_scientific_name }}</em></td>
                                <td>{{ $batch->hatchery->name ?? '-' }}</td>
                                <td>{{ $batch->stocking_date }}</td>
                                <td>{{ number_format($batch->initial_stock_count) }} benur</td>
                                <td>{{ number_format($batch->target_harvest_weight_grams, 1) }} g (target)</td>
                                <td>
                                    <span class="badge badge-info">{{ $batch->stage }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Belum ada siklus batch tambak yang tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($batches->hasPages())
            <div class="card-footer bg-white">
                {{ $batches->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
