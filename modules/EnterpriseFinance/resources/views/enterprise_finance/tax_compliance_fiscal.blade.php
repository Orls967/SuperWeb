@extends('layouts.app')

@section('title', 'Simulator Kepatuhan Pajak Korporat & Rekonsiliasi Fiskal')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Simulator Kepatuhan Pajak Korporat & Rekonsiliasi Fiskal (54.3)</h5>
            <span class="badge bg-danger">DJP Compliance</span>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Rekonsiliasi fiskal masa PPN (Pajak Masukan vs Pajak Keluaran) dan simulasi pemotongan/pemungutan PPh (Pasal 21, 23, 4 ayat 2, 22, 25/29) berbasis data fixture terstandarisasi.
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Masa Pajak</th>
                            <th scope="col">Jenis Pajak</th>
                            <th scope="col">Dasar Pengenaan (DPP)</th>
                            <th scope="col">Pajak Masukan / Dipotong</th>
                            <th scope="col">Pajak Keluaran</th>
                            <th scope="col">Kurang / (Lebih) Bayar</th>
                            <th scope="col">Status Rekonsiliasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($taxes ?? [] as $t)
                            <tr>
                                <td class="font-monospace fw-bold">{{ $t->period }}</td>
                                <td><span class="badge bg-secondary">{{ $t->tax_type }}</span></td>
                                <td class="font-monospace">Rp {{ number_format($t->tax_base_idr) }}</td>
                                <td class="font-monospace text-success">Rp {{ number_format($t->input_tax_idr + $t->withheld_tax_idr) }}</td>
                                <td class="font-monospace text-danger">Rp {{ number_format($t->output_tax_idr) }}</td>
                                <td class="font-monospace fw-bold">Rp {{ number_format($t->payable_or_refundable_idr) }}</td>
                                <td>
                                    <span class="badge {{ $t->status === 'settled' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ strtoupper($t->status ?? 'DRAFT') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada data rekonsiliasi masa pajak.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
