@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-gray-800">Penyelesaian Escrow Fair-Trade Petani Tambak</h1>
            <p class="text-muted">Jaminan pembayaran adil dan transparan terintegrasi Trade Finance (G7-K11).</p>
        </div>
        <div>
            <span class="badge badge-warning text-dark px-3 py-2">TradeFinance Integrated</span>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Kontrak Escrow Panen Petani</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Referensi Escrow</th>
                            <th>Lot Ekspor</th>
                            <th>Penerima (Petani/Koperasi)</th>
                            <th>Nilai Escrow</th>
                            <th>Provider</th>
                            <th>Status Pencairan</th>
                            <th>Waktu Terkunci</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($settlements as $escrow)
                            <tr>
                                <td class="font-weight-bold text-monospace">{{ $escrow->settlement_code }}</td>
                                <td>{{ $escrow->order->order_number ?? '-' }}</td>
                                <td>Petani ID #{{ $escrow->farmer_id }}</td>
                                <td class="font-weight-bold">
                                    USD {{ number_format($escrow->net_farmer_payout_minor_cents / 100, 2) }}
                                </td>
                                <td><span class="badge badge-light border">{{ $escrow->escrow_hold_reference }}</span></td>
                                <td>
                                    <span class="badge {{ $escrow->status === 'RELEASED' ? 'badge-success' : 'badge-warning' }}">
                                        {{ $escrow->status }}
                                    </span>
                                </td>
                                <td>{{ $escrow->created_at }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada alokasi escrow aktif.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($settlements->hasPages())
            <div class="card-footer bg-white">
                {{ $settlements->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
