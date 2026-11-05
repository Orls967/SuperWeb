@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Pusat Investigasi Khusus (SIU)</h1>
            <p class="text-muted small mb-0">Deteksi fraud, analisis sindikat ring fraud, dan penanganan klaim berisiko tinggi</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nomor Kasus</th>
                        <th>Subjek / Nasabah</th>
                        <th>Skor Fraud</th>
                        <th>Alasan Investigasi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $c)
                        <tr>
                            <td><code>{{ $c->case_number }}</code></td>
                            <td>{{ $c->subject?->legal_name ?? $c->subject?->name ?? 'Party #'.substr($c->subject_party_id, 0, 8) }}</td>
                            <td>
                                @php $score = $c->assessment?->risk_score ?? 0; @endphp
                                @if($score >= 80)
                                    <span class="badge bg-danger">{{ $score }} / 100 (Kritis)</span>
                                @elseif($score >= 60)
                                    <span class="badge bg-warning text-dark">{{ $score }} / 100 (Tinggi)</span>
                                @else
                                    <span class="badge bg-info text-dark">{{ $score }} / 100 (Sedang)</span>
                                @endif

                                @if($c->assessment?->ring_fraud_detected)
                                    <span class="badge bg-dark ms-1">Ring Fraud</span>
                                @endif
                            </td>
                            <td><small>{{ $c->reason }}</small></td>
                            <td>
                                @if($c->status === 'confirmed_fraud')
                                    <span class="badge bg-danger">Terbukti Fraud</span>
                                @elseif($c->status === 'cleared')
                                    <span class="badge bg-success">Dibersihkan</span>
                                @else
                                    <span class="badge bg-secondary">Sedang Diinvestigasi</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('insurance.siu.show', $c->id) }}" class="btn btn-sm btn-outline-primary">Periksa Kasus</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Tidak ada berkas investigasi aktif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

