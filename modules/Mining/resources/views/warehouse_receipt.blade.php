@extends('layouts.app')

@section('title', 'Sistem Resi Gudang (SRG) & Digital Collateral')

@section('content')
<div class="container-fluid px-2 px-md-4 py-3">
    <!-- Header Responsive -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h1 class="h3 text-gray-800 mb-1">Sistem Resi Gudang (SRG) & Digital Collateral</h1>
            <p class="text-muted small mb-0">Penatausahaan Resi Gudang Bappebti / ISWARE Beragun Pembiayaan Supply Chain Financing (SCF) Perbankan</p>
        </div>
    </div>

    <!-- Active Warehouse Receipts Table (Mobile responsive) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Resi Gudang Logam Tersertifikasi</h6>
            <span class="badge bg-success">ISWARE Bappebti Integrated</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nomor Resi Gudang</th>
                            <th>Pengelola Gudang</th>
                            <th>Komoditas</th>
                            <th>Berat Tersertifikasi (MT)</th>
                            <th>Plafond SCF Bank (IDR)</th>
                            <th>Status Agunan</th>
                            <th class="text-end">Aksi SRG</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($receipts as $receipt)
                            <tr>
                                <td class="font-weight-bold text-primary">
                                    {{ $receipt->receipt_number }}
                                    <div class="text-muted small">Rack: {{ $receipt->storage_location_rack }}</div>
                                </td>
                                <td>{{ $receipt->warehouse_operator_name }}</td>
                                <td><span class="badge bg-primary">{{ $receipt->metal_commodity }}</span></td>
                                <td>{{ number_format((float) $receipt->certified_weight_metric_tons, 2) }} MT</td>
                                <td class="font-weight-bold text-success">
                                    Rp {{ number_format((int) $receipt->max_financing_limit_idr, 0, ',', '.') }}
                                </td>
                                <td>
                                    <span class="badge {{ $receipt->statusEnum()->badgeClass() }}">
                                        {{ $receipt->statusEnum()->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <form method="POST" action="{{ route('mining.fase121.warehouse-receipt.register-bappebti') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="receipt_id" value="{{ $receipt->id }}">
                                            <button type="submit" class="btn btn-outline-primary" title="Registrasi Bappebti">
                                                <i class="fas fa-stamp"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('mining.fase121.warehouse-receipt.pledge-scf') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="receipt_id" value="{{ $receipt->id }}">
                                            <button type="submit" class="btn btn-outline-warning" title="Jaminkan SCF Bank">
                                                <i class="fas fa-university"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('mining.fase121.warehouse-receipt.release') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="receipt_id" value="{{ $receipt->id }}">
                                            <button type="submit" class="btn btn-outline-success" title="Lepas Agunan">
                                                <i class="fas fa-unlock"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Belum ada resi gudang logam yang diterbitkan.
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
