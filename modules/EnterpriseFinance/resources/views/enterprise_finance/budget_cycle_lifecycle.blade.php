@extends('layouts.app')

@section('title', 'Siklus Hidup Anggaran & Tata Kelola Maker-Checker')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Siklus Hidup Anggaran (8 Status) & Penegakan Prinsip Four-Eyes</h5>
            <span class="badge bg-primary">COSO Internal Control</span>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Manajemen siklus hidup pagu departemen dengan penegakan Four-Eyes Principle: Usulan anggaran (DRAFT) wajib ditelaah (REVIEWED) dan disahkan (APPROVED) oleh otorisator independen yang berbeda dari pembuat usulan.
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="border rounded p-3 bg-light text-center">
                        <span class="text-muted small">Status Awal</span>
                        <h6 class="fw-bold mt-1 text-secondary">DRAFT & SUBMITTED</h6>
                        <p class="text-xs text-muted mb-0">Perumusan kebutuhan unit dan pengajuan ke komite.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 bg-light text-center">
                        <span class="text-muted small">Verifikasi</span>
                        <h6 class="fw-bold mt-1 text-info">REVIEWED</h6>
                        <p class="text-xs text-muted mb-0">Uji kelayakan belanja dan kepatuhan pagu korporat.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 bg-light text-center">
                        <span class="text-muted small">Otorisasi</span>
                        <h6 class="fw-bold mt-1 text-success">APPROVED & ACTIVE</h6>
                        <p class="text-xs text-muted mb-0">Pengesahan Four-Eyes dan penerbitan pagu operasional.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 bg-light text-center">
                        <span class="text-muted small">Pengendalian Khusus</span>
                        <h6 class="fw-bold mt-1 text-warning">REVISED, FROZEN, CLOSED</h6>
                        <p class="text-xs text-muted mb-0">Penyesuaian, pembekuan darurat, dan tutup buku tahunan.</p>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Kode Anggaran</th>
                            <th scope="col">Cost Center</th>
                            <th scope="col">Tahun Fiskal</th>
                            <th scope="col">Pagu Alokasi (IDR)</th>
                            <th scope="col">Status Siklus</th>
                            <th scope="col">Pembuat (Maker)</th>
                            <th scope="col">Penyetuju (Checker)</th>
                            <th scope="col">Aksi Transisi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($budgets ?? [] as $b)
                            <tr>
                                <td class="font-monospace fw-bold">{{ $b->budget_code }}</td>
                                <td>{{ $b->cost_center_code }}</td>
                                <td>{{ $b->fiscal_year }}</td>
                                <td class="font-monospace">Rp {{ number_format($b->allocated_amount_idr) }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ $b->status ?? 'ACTIVE' }}</span>
                                </td>
                                <td>{{ $b->created_by ? 'User #'.$b->created_by : '-' }}</td>
                                <td>{{ $b->approved_by ? 'User #'.$b->approved_by : '-' }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" aria-label="Detail Transisi Anggaran">
                                        Transisi
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Belum ada rekaman anggaran departemen.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
