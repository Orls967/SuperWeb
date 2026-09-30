<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran - {{ $order->number }}</title>
    <style>
        @media print {
            body { margin: 0; padding: 0; background: #fff; }
            .no-print { display: none !important; }
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            line-height: 1.4;
            color: #111;
            background: #f8fafc;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        .receipt-card {
            background: #fff;
            width: 320px;
            padding: 20px 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .divider { border-top: 1px dashed #94a3b8; margin: 8px 0; }
        .double-divider { border-top: 2px dashed #475569; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; }
        .qr-box { display: flex; justify-content: center; margin: 12px 0; }
        .btn-print {
            background: #0f172a;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 12px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="receipt-card">
        <div class="no-print">
            <button onclick="window.print()" class="btn-print">🖨️ Cetak Struk (Print)</button>
        </div>

        <div class="text-center">
            <h2 class="font-bold" style="margin: 0; font-size: 18px;">RM SARI RANAH</h2>
            <p style="margin: 2px 0; font-size: 11px;">Masakan Padang Asli Minangkabau</p>
            <p style="margin: 2px 0; font-size: 11px;">{{ $order->outlet?->name }}</p>
            <p style="margin: 2px 0; font-size: 10px;">{{ $order->outlet?->address }}, {{ $order->outlet?->city }}</p>
            <p style="margin: 2px 0; font-size: 10px;">Telp: {{ $order->outlet?->phone }}</p>
        </div>

        <div class="divider"></div>

        <div class="row">
            <span>No: {{ $order->number }}</span>
            <span>{{ $order->created_at->format('d/m/y H:i') }}</span>
        </div>
        <div class="row">
            <span>Kasir: {{ $order->shift?->cashier?->name ?: 'Kasir' }}</span>
            <span>{{ $order->session?->table ? 'Meja ' . $order->session->table->code : 'Takeaway' }}</span>
        </div>
        @if($order->customer)
            <div class="row">
                <span>Pelanggan: {{ $order->customer->name }}</span>
            </div>
        @endif

        <div class="divider"></div>

        <!-- Order Items -->
        <div>
            @foreach($order->items as $item)
                @if($item->consumed_state->value === 'consumed')
                    <div class="row font-bold">
                        <span>{{ $item->name_snapshot }}</span>
                    </div>
                    <div class="row" style="margin-bottom: 4px;">
                        <span>&nbsp;&nbsp;{{ $item->qty }} × {{ number_format($item->unit_price_snapshot, 0, ',', '.') }}</span>
                        <span>{{ number_format($item->line_total, 0, ',', '.') }}</span>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="divider"></div>

        <div class="row">
            <span>Subtotal</span>
            <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
        </div>
        @if($order->discount > 0)
            <div class="row">
                <span>Diskon</span>
                <span>-Rp {{ number_format($order->discount, 0, ',', '.') }}</span>
            </div>
        @endif
        <div class="row">
            <span>PB1 (10%)</span>
            <span>Rp {{ number_format($order->tax_pb1, 0, ',', '.') }}</span>
        </div>
        @if($order->rounding !== 0)
            <div class="row">
                <span>Pembulatan</span>
                <span>{{ $order->rounding >= 0 ? '+' : '' }}Rp {{ number_format($order->rounding, 0, ',', '.') }}</span>
            </div>
        @endif

        <div class="double-divider"></div>

        <div class="row font-bold" style="font-size: 15px;">
            <span>TOTAL</span>
            <span>Rp {{ number_format($order->grand_total, 0, ',', '.') }}</span>
        </div>

        <div class="row" style="margin-top: 4px;">
            <span>Metode Bayar:</span>
            <span class="font-bold">{{ strtoupper($order->payment_method ?? 'TUNAI') }}</span>
        </div>

        <div class="divider"></div>

        <div class="qr-box">
            {!! $qrSvg !!}
        </div>

        <div class="text-center" style="font-size: 11px;">
            <p style="margin: 2px 0;">Scan QR untuk e-Receipt Resmi</p>
            <p style="margin: 2px 0; font-weight: bold;">Terima Kasih Atas Kunjungan Anda</p>
            <p style="margin: 2px 0; font-size: 10px;">Rancak Bana! Tambuah ciek lai!</p>
        </div>
    </div>
</body>
</html>
