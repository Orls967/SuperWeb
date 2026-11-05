<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Current {{ $accountCurrent->number }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 1rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; }
        .btn-outline { background: #fff; color: #475569; border: 1px solid #cbd5e1; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ route('insurance.reinsurance.account-current.index') }}" class="btn btn-outline" style="margin-bottom: 1rem;">&larr; Kembali</a>

        <div class="card">
            <h1>Account Current {{ $accountCurrent->number }}</h1>
            <div class="grid">
                <div><strong>Reinsurer:</strong> {{ $accountCurrent->reinsurer?->legal_name ?? 'Party #'.$accountCurrent->reinsurer_party_id }}</div>
                <div><strong>Periode:</strong> {{ $accountCurrent->period_ym }}</div>
                <div><strong>Status:</strong> {{ strtoupper($accountCurrent->status->value) }}</div>
                <div><strong>Diterbitkan:</strong> {{ $accountCurrent->issued_at?->format('Y-m-d H:i') ?? '-' }}</div>
                <div><strong>Diselesaikan:</strong> {{ $accountCurrent->settled_at?->format('Y-m-d H:i') ?? '-' }}</div>
                <div><strong>Ledger Tx:</strong> {{ $accountCurrent->settlement_ledger_tx ?? '-' }}</div>
            </div>

            <div style="margin-top: 1.5rem; background: #f8fafc; padding: 1rem; border-radius: 6px;">
                <h3>Rekonsiliasi Saldo Kas Neto:</h3>
                <p>Premi Ceded: Rp {{ number_format($accountCurrent->premium_ceded_idr, 0, ',', '.') }}</p>
                <p>Premi Reinstatement: + Rp {{ number_format($accountCurrent->reinstatement_idr, 0, ',', '.') }}</p>
                <p>Komisi Reasuransi: - Rp {{ number_format($accountCurrent->commission_idr, 0, ',', '.') }}</p>
                <p>Klaim Recoverable: - Rp {{ number_format($accountCurrent->claims_recoverable_idr, 0, ',', '.') }}</p>
                <hr style="border: none; border-top: 1px solid #cbd5e1;">
                <h2>Saldo Neto (Net Due): Rp {{ number_format($accountCurrent->net_due_idr, 0, ',', '.') }}</h2>
                <p style="font-size: 0.875rem; color: #64748b;">
                    @if($accountCurrent->net_due_idr > 0)
                        (Penanggung membayarkan kas neto ke Reinsurer)
                    @elseif($accountCurrent->net_due_idr < 0)
                        (Reinsurer membayarkan kas neto ke Penanggung)
                    @else
                        (Saldo kliring nihil)
                    @endif
                </p>
            </div>

            @if($accountCurrent->status === \Modules\Insurance\Domain\Enums\AccountCurrentStatus::Issued)
                <div style="margin-top: 1.5rem;">
                    <form action="{{ route('insurance.reinsurance.account-current.settle', $accountCurrent->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="settle-ac-{{ $accountCurrent->id }}">
                        <button type="submit" class="btn" style="background: #16a34a;">Selesaikan Settlement (Posting Buku Besar)</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</body>
</html>

