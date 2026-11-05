@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Riwayat Komisi Broker</h1>
            <p class="text-muted small mb-0">Daftar komisi per polis dan status penyelesaian akuntansi ledger</p>
        </div>
        <a href="{{ route('insurance.brokers.index') }}" class="btn btn-outline-secondary btn-sm">Kembali ke Broker</a>
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
                        <th>ID</th>
                        <th>Broker</th>
                        <th>Polis</th>
                        <th class="text-end">Premi</th>
                        <th>Rate</th>
                        <th class="text-end">Komisi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commissions as $c)
                        <tr>
                            <td>#{{ $c->id }}</td>
                            <td>{{ $c->broker?->party?->legal_name ?? 'Broker #'.$c->broker_id }}</td>
                            <td><code>{{ $c->policy?->policy_number }}</code></td>
                            <td class="text-end">Rp {{ number_format($c->premium_idr) }}</td>
                            <td>{{ $c->commission_bps / 100 }}%</td>
                            <td class="text-end font-monospace text-success">Rp {{ number_format($c->commission_amount_idr) }}</td>
                            <td>
                                @if($c->status === 'settled')
                                    <span class="badge bg-success">Terselesaikan</span>
                                @else
                                    <span class="badge bg-warning text-dark">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if($c->status === 'pending')
                                    <form action="{{ route('insurance.brokers.settle', $c->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Settle</button>
                                    </form>
                                @else
                                    <span class="text-muted small">TX #{{ $c->ledger_transaction_id }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Belum ada catatan komisi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

