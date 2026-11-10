@extends('layouts.app')

@section('title', 'Optimalisasi Modal Kerja & Siklus Konversi Kas (CCC)')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Manajemen Likuiditas Modal Kerja Grup & Cash Conversion Cycle (54.5)</h5>
            <span class="badge bg-success">Treasury Liquidity</span>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Pemantauan efisiensi siklus perputaran modal kerja: Days Sales Outstanding (DSO), Days Inventory Outstanding (DIO), dan Days Payables Outstanding (DPO). Formula: CCC = DSO + DIO - DPO.
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center bg-light">
                        <span class="text-muted small">Target DSO</span>
                        <h4 class="fw-bold mt-2 text-primary">&le; 45 Hari</h4>
                        <p class="text-xs text-muted mb-0">Kecepatan penagihan piutang pelanggan.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center bg-light">
                        <span class="text-muted small">Target DIO</span>
                        <h4 class="fw-bold mt-2 text-warning">&le; 30 Hari</h4>
                        <p class="text-xs text-muted mb-0">Perputaran dan efisiensi persediaan gudang.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center bg-light">
                        <span class="text-muted small">Target DPO</span>
                        <h4 class="fw-bold mt-2 text-info">&ge; 60 Hari</h4>
                        <p class="text-xs text-muted mb-0">Optimalisasi termin pembayaran ke pemasok.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center bg-light">
                        <span class="text-muted small">Target Efisiensi CCC</span>
                        <h4 class="fw-bold mt-2 text-success">&le; 15 Hari</h4>
                        <p class="text-xs text-muted mb-0">Siklus konversi kas likuid ke kas kembali.</p>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Periode</th>
                            <th scope="col">Entitas Anak</th>
                            <th scope="col">Pendapatan (IDR)</th>
                            <th scope="col">HPP / COGS (IDR)</th>
                            <th scope="col">DSO</th>
                            <th scope="col">DIO</th>
                            <th scope="col">DPO</th>
                            <th scope="col">CCC</th>
                            <th scope="col">Tier Efisiensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($metrics ?? [] as $m)
                            <tr>
                                <td class="font-monospace">{{ $m->period }}</td>
                                <td class="fw-bold">{{ $m->entity_code }}</td>
                                <td class="font-monospace">Rp {{ number_format($m->revenue_minor) }}</td>
                                <td class="font-monospace">Rp {{ number_format($m->cogs_minor) }}</td>
                                <td class="font-monospace">{{ $m->dso_days }} hari</td>
                                <td class="font-monospace">{{ $m->dio_days }} hari</td>
                                <td class="font-monospace">{{ $m->dpo_days }} hari</td>
                                <td class="font-monospace fw-bold {{ $m->ccc_days <= 30 ? 'text-success' : 'text-danger' }}">
                                    {{ $m->ccc_days }} hari
                                </td>
                                <td>
                                    <span class="badge {{ $m->efficiency_tier === 'EXCELLENT' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $m->efficiency_tier }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Belum ada kalkulasi metrik modal kerja pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
