@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Daftar Polis Embedded</h1>
            <p class="text-muted small mb-0">Polis proteksi tertanam yang diterbitkan dari transaksi bisnis</p>
        </div>
        <a href="{{ route('insurance.embedded.matrix') }}" class="btn btn-primary btn-sm">+ Terbitkan Baru</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nomor Polis</th>
                        <th>Domain / Produk</th>
                        <th>Nasabah</th>
                        <th>Konteks Lini</th>
                        <th class="text-end">Sum Insured</th>
                        <th class="text-end">Premi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($policies as $p)
                        <tr>
                            <td><code>{{ $p->policy_number }}</code></td>
                            <td>
                                <span class="badge bg-secondary me-1">{{ $p->product?->line_domain }}</span>
                                {{ $p->product?->name }}
                            </td>
                            <td>{{ $p->customer?->legal_name ?? $p->customer?->name ?? 'Party #'.substr($p->customer_party_id, 0, 8) }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $p->context_type }}</span> <code>{{ $p->context_ref }}</code></td>
                            <td class="text-end">Rp {{ number_format($p->sum_insured_idr) }}</td>
                            <td class="text-end">Rp {{ number_format($p->premium_idr) }}</td>
                            <td>
                                @if($p->status === 'active')
                                    <span class="badge bg-success">Aktif</span>
                                @elseif($p->status === 'claimed')
                                    <span class="badge bg-warning text-dark">Klaim Dibayar</span>
                                @else
                                    <span class="badge bg-secondary">{{ $p->status }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada polis embedded yang diterbitkan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($policies->hasPages())
            <div class="card-footer bg-white">
                {{ $policies->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

