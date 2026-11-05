@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Matriks Embedded Insurance 30 Lini</h1>
            <p class="text-muted small mb-0">Katalog proteksi mikro & asuransi tertanam lintas lini bisnis</p>
        </div>
        <a href="{{ route('insurance.embedded.policies') }}" class="btn btn-outline-primary btn-sm">Lihat Polis Terbit</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3 mb-4">
        @foreach($products as $prod)
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-primary">{{ $prod->line_domain }}</span>
                            <span class="badge bg-light text-dark border">{{ $prod->coverage_type }}</span>
                        </div>
                        <h5 class="card-title h6 mb-2">{{ $prod->name }}</h5>
                        <p class="text-muted small mb-3">Kode: <code>{{ $prod->code }}</code></p>
                        
                        <div class="mt-auto pt-2 border-top">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Default SI:</span>
                                <strong>Rp {{ number_format($prod->default_sum_insured_idr) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-3">
                                <span>Rate / Min:</span>
                                <span>{{ $prod->premium_rate_bps / 100 }}% (Min Rp {{ number_format($prod->min_premium_idr) }})</span>
                            </div>

                            <button class="btn btn-sm btn-outline-success w-100" data-bs-toggle="modal" data-bs-target="#issueModal{{ $prod->id }}">
                                Terbitkan Proteksi
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Issue -->
            <div class="modal fade" id="issueModal{{ $prod->id }}" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <form action="{{ route('insurance.embedded.issue') }}" method="POST" class="modal-content">
                        @csrf
                        <input type="hidden" name="event_key" value="emb:issue:{{ $prod->code }}:{{ uniqid() }}">
                        <input type="hidden" name="product_code" value="{{ $prod->code }}">
                        <div class="modal-header">
                            <h5 class="modal-title h6">Terbitkan {{ $prod->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small">Party ID Nasabah</label>
                                <input type="text" name="customer_party_id" class="form-control form-control-sm" required placeholder="UUID Party Nasabah">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Tipe Konteks</label>
                                <input type="text" name="context_type" class="form-control form-control-sm" required value="booking">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Referensi Transaksi Lini</label>
                                <input type="text" name="context_ref" class="form-control form-control-sm" required placeholder="Contoh: HTL-BK-2026-001">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Broker (Opsional)</label>
                                <select name="broker_party_id" class="form-select form-select-sm">
                                    <option value="">Tanpa Broker (Direct)</option>
                                    @foreach($brokers as $b)
                                        <option value="{{ $b->party_id }}">{{ $b->party?->legal_name ?? $b->party?->name ?? 'Broker #'.$b->id }} ({{ $b->tier }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-sm btn-primary">Konfirmasi Penerbitan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

