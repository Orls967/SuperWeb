@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Customer Insurance Hub</h1>
            <p class="text-muted small mb-0">Portofolio proteksi terpadu seluruh transaksi dan lini bisnis</p>
        </div>
        <span class="badge bg-primary fs-6 px-3 py-2">ID Nasabah: {{ $portfolio['customer_party_id'] }}</span>
    </div>

    <!-- Ringkasan Portofolio & Diskon Bundling -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 p-3">
                <div class="text-muted small">Total Polis Aktif</div>
                <div class="h3 mb-0">{{ $portfolio['active_policy_count'] }}</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 p-3">
                <div class="text-muted small">Total Nilai Pertanggungan</div>
                <div class="h3 mb-0 text-success">Rp {{ number_format($portfolio['total_sum_insured_idr']) }}</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 p-3">
                <div class="text-muted small">Diskon Bundling Multi-Lini</div>
                <div class="h3 mb-0 text-primary">{{ $portfolio['bundling_discount_bps'] / 100 }}%</div>
                <div class="text-muted small mt-1">
                    @if($portfolio['bundling_discount_bps'] > 0)
                        Diskon otomatis aktif untuk transaksi lini berikutnya!
                    @else
                        Miliki 2+ polis untuk dapatkan diskon bundling.
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Seluruh Polis Terpadu -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="card-title h6 mb-0">Daftar Polis Terkonsolidasi</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tipe</th>
                        <th>Nomor Polis</th>
                        <th>Produk</th>
                        <th class="text-end">Sum Insured</th>
                        <th>Konteks Lini</th>
                        <th>Masa Berlaku</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($portfolio['policies'] as $pol)
                        <tr>
                            <td>
                                @if($pol['type'] === 'EMBEDDED')
                                    <span class="badge bg-info text-dark">Embedded</span>
                                @else
                                    <span class="badge bg-secondary">Core Lini</span>
                                @endif
                            </td>
                            <td><code>{{ $pol['policy_number'] }}</code></td>
                            <td>{{ $pol['product_name'] }}</td>
                            <td class="text-end">Rp {{ number_format($pol['sum_insured_idr']) }}</td>
                            <td><small class="text-muted">{{ $pol['context'] ?? '-' }}</small></td>
                            <td><small class="text-muted">{{ substr($pol['starts_at'], 0, 10) }} s.d. {{ substr($pol['expires_at'], 0, 10) }}</small></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Belum ada polis aktif untuk nasabah ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

