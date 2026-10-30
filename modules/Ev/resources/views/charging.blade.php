<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SPKLU Charging Portal</title>
</head>
<body style="font-family: sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 16px;">
    <div style="max-width: 480px; margin: 0 auto;">
        <header style="border-bottom: 1px solid #334155; padding-bottom: 12px; margin-bottom: 16px;">
            <h1 style="font-size: 20px; font-weight: bold; margin: 0;">SPKLU Mobile Charging</h1>
            <p style="font-size: 12px; color: #94a3b8; margin: 4px 0 0 0;">Pengisian Daya Kendaraan Listrik Terpadu</p>
        </header>

        <section>
            @if(isset($sessions) && count($sessions) > 0)
                @foreach($sessions as $session)
                    <div style="background: #1e293b; border-radius: 8px; padding: 16px; margin-bottom: 12px; border: 1px solid #334155;">
                        <div style="font-size: 14px; font-weight: 600; color: #38bdf8;">{{ $session->session_code }}</div>
                        <div style="margin-top: 8px; display: flex; justify-content: space-between;">
                            <span style="font-size: 12px; color: #cbd5e1;">Status:</span>
                            <span style="font-size: 12px; font-weight: bold; color: #4ade80;">{{ $session->status }}</span>
                        </div>
                        <div style="margin-top: 4px; display: flex; justify-content: space-between;">
                            <span style="font-size: 12px; color: #cbd5e1;">Energi:</span>
                            <span style="font-size: 12px; color: #f1f5f9;">{{ number_format($session->energy_wh ?? 0) }} Wh</span>
                        </div>
                        <div style="margin-top: 4px; display: flex; justify-content: space-between;">
                            <span style="font-size: 12px; color: #cbd5e1;">Deposit:</span>
                            <span style="font-size: 12px; color: #f1f5f9;">Rp {{ number_format($session->deposit_held_idr ?? 0) }}</span>
                        </div>
                    </div>
                @endforeach
            @else
                <p style="font-size: 14px; color: #94a3b8;">Tidak ada sesi pengisian aktif saat ini.</p>
            @endif
        </section>
    </div>
</body>
</html>
