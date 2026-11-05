@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Marketplace Broker & Agen Asuransi</h1>
            <p class="text-muted small mb-0">Manajemen perizinan, tier komisi, dan evaluasi kinerja broker</p>
        </div>
        <div>
            <a href="{{ route('insurance.brokers.commissions') }}" class="btn btn-outline-primary btn-sm me-1">Riwayat Komisi</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#registerBrokerModal">+ Daftarkan Broker</button>
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
                        <th>Broker / Entitas</th>
                        <th>Lisensi</th>
                        <th>Tier</th>
                        <th>Rate Komisi</th>
                        <th>Rating</th>
                        <th class="text-end">Total Produksi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($brokers as $b)
                        <tr>
                            <td>
                                <strong>{{ $b->party?->legal_name ?? $b->party?->name ?? 'Broker #'.$b->id }}</strong>
                            </td>
                            <td><code>{{ $b->license_number }}</code></td>
                            <td>
                                <span class="badge bg-info text-dark text-uppercase">{{ $b->tier }}</span>
                            </td>
                            <td>{{ $b->commission_rate_bps / 100 }}%</td>
                            <td>
                                <span class="badge bg-warning text-dark">★ {{ $b->rating_score }}/100</span>
                            </td>
                            <td class="text-end">Rp {{ number_format($b->total_gross_written_idr) }}</td>
                            <td>
                                <span class="badge bg-success">{{ $b->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada broker terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Register Broker -->
    <div class="modal fade" id="registerBrokerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('insurance.brokers.register') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title h6">Daftarkan Broker Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small">Entitas Party</label>
                        <select name="party_id" class="form-select form-select-sm" required>
                            @foreach($parties as $pty)
                                <option value="{{ $pty->id }}">{{ $pty->legal_name ?? $pty->name ?? 'Party #'.$pty->id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Nomor Lisensi OJK / AAJI / AAUI</label>
                        <input type="text" name="license_number" class="form-control form-control-sm" required placeholder="Contoh: BRK-OJK-2026-088">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Tier Kemitraan</label>
                        <select name="tier" class="form-select form-select-sm" required>
                            <option value="bronze">Bronze (5-10%)</option>
                            <option value="silver" selected>Silver (10-15%)</option>
                            <option value="gold">Gold (15-20%)</option>
                            <option value="platinum">Platinum (20-25%)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Rate Komisi (bps, misal 1000 = 10%)</label>
                        <input type="number" name="commission_rate_bps" class="form-control form-control-sm" required value="1000" min="100" max="3000">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan Broker</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

