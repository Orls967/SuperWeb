@extends('layouts.app')

@section('title', 'Metals Trading Desk & LME Hedging')

@section('content')
<div class="container-fluid px-2 px-md-4 py-3">
    <!-- Header Responsive -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h1 class="h3 text-gray-800 mb-1">Metals Trading Desk & LME Hedging</h1>
            <p class="text-muted small mb-0">Kontrak Penjualan Fisik, Lindung Nilai Bursa Logam London (LME) & Mark-to-Market Real-Time</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex gap-2">
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newContractModal">
                <i class="fas fa-plus"></i> Kontrak Baru
            </button>
        </div>
    </div>

    <!-- Active Contracts & Positions Table (Mobile responsive) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Kontrak Penjualan & Posisi Hedging</h6>
            <span class="badge bg-info text-dark">LME Real-Time Feed</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nomor Kontrak</th>
                            <th>Pihak Lawan (Buyer)</th>
                            <th>Komoditas</th>
                            <th>Kuantitas (MT)</th>
                            <th>Harga Patokan ($ / Ton)</th>
                            <th>Status Kontrak</th>
                            <th class="text-end">Aksi Desk</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contracts as $contract)
                            <tr>
                                <td class="font-weight-bold text-primary">{{ $contract->contract_number }}</td>
                                <td>{{ $contract->counterparty_name }}</td>
                                <td><span class="badge bg-secondary">{{ $contract->metal_symbol }}</span></td>
                                <td>{{ number_format((float) $contract->contract_quantity_metric_tons, 1) }} MT</td>
                                <td>$ {{ number_format((int) $contract->fixed_price_usd_cents_per_ton / 100, 2) }}</td>
                                <td>
                                    <span class="badge {{ $contract->statusEnum()->badgeClass() }}">
                                        {{ $contract->statusEnum()->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <form method="POST" action="{{ route('mining.fase121.trading.lock-price') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="contract_id" value="{{ $contract->id }}">
                                            <button type="submit" class="btn btn-outline-primary" title="Kunci Harga LME">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('mining.fase121.trading.mtm') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="contract_id" value="{{ $contract->id }}">
                                            <button type="submit" class="btn btn-outline-info" title="Valuasi MTM">
                                                <i class="fas fa-chart-line"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('mining.fase121.trading.settle') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="contract_id" value="{{ $contract->id }}">
                                            <button type="submit" class="btn btn-outline-success" title="Settle Kontrak">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Belum ada kontrak perdagangan logam yang tercatat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Kontrak -->
<div class="modal fade" id="newContractModal" tabindex="-1" aria-labelledby="newContractLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('mining.fase121.trading.contracts.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="newContractLabel">Buat Kontrak Perdagangan Logam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Pihak Lawan (Offtaker / Counterparty)</label>
                        <input type="text" name="counterparty_name" class="form-control" value="Glencore International AG" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Simbol Logam</label>
                            <select name="metal_symbol" class="form-select">
                                <option value="LME-NI">Nikel (LME-NI)</option>
                                <option value="LME-CU">Tembaga (LME-CU)</option>
                                <option value="LME-CO">Kobalt (LME-CO)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tipe Kontrak</label>
                            <select name="contract_type" class="form-select">
                                <option value="PHYSICAL_OFFTAKE">Penjualan Fisik</option>
                                <option value="HEDGE_FORWARD">Lindung Nilai Forward</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Kuantitas (MT)</label>
                            <input type="number" step="0.1" name="contract_quantity_metric_tons" class="form-control" value="500.0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Harga Tetap ($ Cents / Ton)</label>
                            <input type="number" name="fixed_price_usd_cents_per_ton" class="form-control" value="1650000" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Kontrak</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
