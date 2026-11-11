@extends('layouts.app')

@section('title', 'Integrasi Keuangan M&A & Evaluasi Goodwill')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Integrasi Keuangan Pasca M&A & Uji Penurunan Nilai Goodwill (54.8)</h5>
            <span class="badge bg-secondary">PSAK 48 / PSAK 22</span>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Pencatatan alokasi harga beli (Purchase Price Allocation - PPA), nilai wajar aset teridentifikasi neto, pengakuan saldo goodwill, dan pengujian penurunan nilai tahunan (Impairment Testing).
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Kode Akuisisi</th>
                            <th scope="col">Nama Target Akuisisi</th>
                            <th scope="col">Tanggal Penutupan</th>
                            <th scope="col">Harga Transaksi (IDR)</th>
                            <th scope="col">Aset Bersih Wajar (IDR)</th>
                            <th scope="col">Goodwill Tercatat (IDR)</th>
                            <th scope="col">Uji Penurunan Nilai</th>
                            <th scope="col">Status Integrasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($acquisitions ?? [] as $acq)
                            <tr>
                                <td class="font-monospace fw-bold">{{ $acq->acquisition_code }}</td>
                                <td>{{ $acq->target_name }}</td>
                                <td class="font-monospace">{{ $acq->closing_date }}</td>
                                <td class="font-monospace">Rp {{ number_format($acq->purchase_price_minor) }}</td>
                                <td class="font-monospace">Rp {{ number_format($acq->fair_net_assets_minor) }}</td>
                                <td class="font-monospace fw-bold text-primary">Rp {{ number_format($acq->goodwill_minor) }}</td>
                                <td>
                                    <span class="badge bg-success">NO_IMPAIRMENT</span>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark">{{ $acq->status ?? 'INTEGRATED' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Belum ada transaksi M&A yang tercatat dalam sistem.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
