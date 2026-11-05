@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 900px;">
    <div class="mb-4">
        <a href="{{ route('insurance.siu.index') }}" class="btn btn-outline-secondary btn-sm mb-3">&larr; Kembali ke Daftar Kasus</a>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="h3 mb-1">Kasus SIU: <code>{{ $case->case_number }}</code></h1>
                <p class="text-muted small mb-0">Subjek: {{ $case->subject?->legal_name ?? $case->subject?->name ?? 'Party #'.$case->subject_party_id }}</p>
            </div>
            <div>
                @if($case->status === 'confirmed_fraud')
                    <span class="badge bg-danger fs-6">CONFIRMED FRAUD</span>
                @elseif($case->status === 'cleared')
                    <span class="badge bg-success fs-6">CLEARED</span>
                @else
                    <span class="badge bg-warning text-dark fs-6">DALAM PENYELIDIKAN</span>
                @endif
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <!-- Rincian Skor & Analisis -->
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 h-100 p-3">
                <h5 class="h6 mb-3">Hasil Penilaian Risiko Fraud</h5>
                <div class="d-flex align-items-baseline mb-2">
                    <span class="display-6 fw-bold text-danger me-2">{{ $case->assessment?->risk_score ?? 0 }}</span>
                    <span class="text-muted">/ 100</span>
                </div>
                <div class="text-muted small mb-3">Tingkat Risiko: <strong>{{ strtoupper($case->assessment?->risk_level ?? 'LOW') }}</strong></div>

                <h6 class="small fw-bold">Rincian Komponen Skor:</h6>
                <ul class="list-group list-group-flush small mb-3">
                    @foreach(($case->assessment?->score_breakdown ?? []) as $k => $val)
                        <li class="list-group-item d-flex justify-content-between px-0 py-1">
                            <span>{{ ucwords(str_replace('_', ' ', $k)) }}</span>
                            <span class="fw-bold">+{{ $val }}</span>
                        </li>
                    @endforeach
                </ul>

                @if($case->assessment?->ring_fraud_detected)
                    <div class="alert alert-danger py-2 small mb-0">
                        <strong>⚠️ Terdeteksi Sindikat (Ring Fraud)!</strong>
                        <div class="mt-1">Ditemukan keterkaitan nomor kontak, rekening, atau alamat yang sama pada multiple klaim:</div>
                        <ul class="mb-0 mt-1">
                            @foreach(($case->assessment?->ring_cluster_nodes ?? []) as $ring)
                                <li>{{ implode(' ↔ ', $ring) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        <!-- Form Putusan Four-Eyes -->
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 h-100 p-3">
                <h5 class="h6 mb-3">Putusan Investigasi (Four-Eyes Principle)</h5>

                @if(in_array($case->status, ['confirmed_fraud', 'cleared'], true))
                    <div class="alert alert-light border small">
                        <div><strong>Diputus Oleh:</strong> {{ $case->decider?->name ?? 'User #'.$case->decided_by }}</div>
                        <div><strong>Tanggal:</strong> {{ $case->decided_at?->format('Y-m-d H:i') }}</div>
                        <div class="mt-2"><strong>Catatan Putusan:</strong></div>
                        <p class="mb-0 text-muted">{{ $case->decision_notes }}</p>
                    </div>
                @else
                    <form action="{{ route('insurance.siu.decide', $case->id) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Keputusan</label>
                            <select name="decision" class="form-select form-select-sm" required>
                                <option value="confirmed_fraud">Terbukti Fraud (Konfirmasi & Tolak Klaim)</option>
                                <option value="cleared">Dibersihkan (Klaim Layak Diproses)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nomor Identitas untuk Blacklist</label>
                            <input type="text" name="identity_to_blacklist" class="form-control form-control-sm" placeholder="NIK / NPWP (jika confirmed fraud)">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Catatan Putusan & Dasar Bukti</label>
                            <textarea name="decision_notes" rows="4" class="form-control form-control-sm" required placeholder="Jelaskan alasan dan temuan bukti investigasi..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-danger w-100">Eksekusi Putusan</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

