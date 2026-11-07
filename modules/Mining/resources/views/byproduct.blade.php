@extends('layouts.app')

@section('title', 'Produk Sampingan Smelter & Ekonomi Sirkular')

@section('content')
<div class="container-fluid px-2 px-md-4 py-3">
    <!-- Header Responsive -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h1 class="h3 text-gray-800 mb-1">Produk Sampingan Smelter & Ekonomi Sirkular</h1>
            <p class="text-muted small mb-0">Pemanfaatan Terak Nikel (Slag), Kepatuhan Manifest Lingkungan SIMPEL KLHK & Monetisasi Agregat Semen</p>
        </div>
    </div>

    <!-- Active Byproducts Table (Mobile responsive) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Pemanfaatan Produk Sampingan Smelter</h6>
            <span class="badge bg-secondary">KLHK SIMPEL Compliant</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Produk Sampingan</th>
                            <th>Tipe Material</th>
                            <th>Total Dihasilkan (Ton)</th>
                            <th>Terkirim (Ton)</th>
                            <th>Stok Tersisa (Ton)</th>
                            <th>Pendapatan (IDR)</th>
                            <th>Status Pemanfaatan</th>
                            <th class="text-end">Aksi Sirkular</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($byproducts as $byproduct)
                            <tr>
                                <td class="font-weight-bold text-primary">
                                    {{ $byproduct->byproduct_code }}
                                    <div class="text-muted small">{{ $byproduct->environmental_manifest_number }}</div>
                                </td>
                                <td><span class="badge bg-secondary">{{ $byproduct->byproduct_type }}</span></td>
                                <td>{{ number_format((float) $byproduct->generated_quantity_tons, 2) }} Ton</td>
                                <td>{{ number_format((float) $byproduct->dispatched_quantity_tons, 2) }} Ton</td>
                                <td>{{ number_format((float) $byproduct->stock_remaining_tons, 2) }} Ton</td>
                                <td class="font-weight-bold text-success">
                                    Rp {{ number_format((int) $byproduct->total_revenue_idr, 0, ',', '.') }}
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        {{ $byproduct->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('mining.fase121.byproduct.recycle') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="byproduct_id" value="{{ $byproduct->id }}">
                                        <input type="hidden" name="recycle_tons" value="15.0">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Daur Ulang Agregat">
                                            <i class="fas fa-recycle"></i> Daur Ulang
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Belum ada produk sampingan terak slag yang tercatat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
