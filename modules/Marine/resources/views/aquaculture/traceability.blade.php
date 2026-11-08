@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-gray-800">Ketelusuran Kriptografis Seafood (Merkle Tree)</h1>
            <p class="text-muted">Bukti rantai pasok digital dari benur, pakan, panen, hingga kontainer reefer ekspor.</p>
        </div>
        <div>
            <span class="badge badge-primary px-3 py-2">SHA-256 Merkle Provenance</span>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lot Ekspor Terverifikasi</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Nomor Lot Ekspor</th>
                            <th>Bentuk Produk</th>
                            <th>Berat Bersih</th>
                            <th>Mutu / SPS</th>
                            <th>Sertifikasi</th>
                            <th>Merkle Root Hash</th>
                            <th>Status Karantina</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exportLots as $lot)
                            <tr>
                                <td class="font-weight-bold text-monospace">{{ $lot->lot_tracking_number }}</td>
                                <td>{{ $lot->product_form }}</td>
                                <td>{{ number_format($lot->net_lot_weight_kg, 2) }} kg</td>
                                <td>
                                    <span class="badge badge-success">{{ $lot->commercial_grade }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-pill badge-primary">ASC CoC</span>
                                    <span class="badge badge-pill badge-info">BAP 4-Star</span>
                                </td>
                                <td>
                                    @php $node = $lot->traceabilityNodes->first(); @endphp
                                    @if($node && $node->merkle_root_hash)
                                        <code class="small text-truncate d-inline-block" style="max-width: 180px;">{{ $node->merkle_root_hash }}</code>
                                    @else
                                        <span class="text-muted small">Anchored</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-light border">{{ $lot->freeze_method }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada lot ekspor yang diproses.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($exportLots->hasPages())
            <div class="card-footer bg-white">
                {{ $exportLots->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
