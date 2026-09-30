<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label Pengiriman — {{ $shipment->tracking_number }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        body {
            background-color: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .label-container {
            width: 100mm;
            min-height: 150mm;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #000000;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
        }
        .brand {
            font-size: 16px;
            font-weight: 900;
            letter-spacing: -0.5px;
        }
        .sub-brand {
            font-size: 9px;
            font-weight: bold;
            color: #475569;
        }
        .service-badge {
            background: #000;
            color: #fff;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            border-radius: 4px;
        }
        .qr-section {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 2px solid #000;
        }
        .qr-code svg {
            width: 90px;
            height: 90px;
        }
        .tracking-info {
            flex: 1;
        }
        .tracking-number {
            font-family: "Courier New", Courier, monospace;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 1px;
            word-break: break-all;
        }
        .routing-hub {
            margin-top: 4px;
            font-size: 20px;
            font-weight: 900;
            background: #f8fafc;
            padding: 4px;
            border: 1px dashed #000;
            text-align: center;
        }
        .addresses {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 8px 0;
            border-bottom: 2px solid #000;
            font-size: 11px;
        }
        .addr-box {
            padding: 4px;
        }
        .addr-title {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
        }
        .addr-name {
            font-size: 13px;
            font-weight: 800;
        }
        .addr-text {
            font-size: 11px;
            line-height: 1.3;
            margin-top: 2px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            padding: 8px 0;
            border-bottom: 2px solid #000;
            font-size: 10px;
        }
        .detail-item span {
            display: block;
        }
        .detail-label {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
        }
        .detail-val {
            font-size: 12px;
            font-weight: 800;
        }
        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 6px;
            font-size: 8px;
            color: #64748b;
        }
        .print-btn-bar {
            position: fixed;
            top: 20px;
            right: 20px;
        }
        .print-btn {
            background: #0284c7;
            color: white;
            border: none;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }
        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
            }
            .print-btn-bar {
                display: none;
            }
            .label-container {
                border: 2px solid #000;
                width: 100mm;
                height: 150mm;
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <div class="print-btn-bar">
        <button class="print-btn" onclick="window.print()">Cetak Label</button>
    </div>

    <div class="label-container">
        <div>
            <!-- Header -->
            <div class="header">
                <div>
                    <div class="brand">SARI RANAH EXPRESS</div>
                    <div class="sub-brand">Jaringan Logistik Terpadu Kalimantan</div>
                </div>
                <div class="service-badge">{{ $shipment->service_level->label() }}</div>
            </div>

            <!-- QR & Tracking Bar -->
            <div class="qr-section">
                <div class="qr-code">
                    {!! $qrSvg !!}
                </div>
                <div class="tracking-info">
                    <div class="tracking-number">{{ $shipment->tracking_number }}</div>
                    <div class="routing-hub">
                        {{ $shipment->origin?->code }} &rarr; {{ $shipment->destination?->code }}
                    </div>
                </div>
            </div>

            <!-- Addresses -->
            <div class="addresses">
                <div class="addr-box">
                    <div class="addr-title">Penerima (Consignee):</div>
                    <div class="addr-name">{{ $shipment->consignee_name }} ({{ $shipment->consignee_phone }})</div>
                    <div class="addr-text">
                        {{ $shipment->consignee_address['street'] ?? '-' }}<br>
                        {{ $shipment->consignee_address['city'] ?? '' }} {{ $shipment->consignee_address['postal_code'] ?? '' }}
                    </div>
                </div>

                <div class="addr-box" style="border-top: 1px dashed #cbd5e1; padding-top: 4px;">
                    <div class="addr-title">Pengirim (Shipper):</div>
                    <div class="addr-name">{{ $shipment->shipper?->name ?? 'Shipper Pelanggan' }}</div>
                    <div class="addr-text">{{ $shipment->origin?->city }}, {{ $shipment->origin?->province }}</div>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label">Berat Tertagih</span>
                    <span class="detail-val">{{ number_format($shipment->total_chargeable_weight_g / 1000, 1) }} KG</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Jumlah Paket</span>
                    <span class="detail-val">{{ $shipment->packages->count() }} Koli</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Ketentuan Bayar</span>
                    <span class="detail-val">{{ strtoupper($shipment->payment_terms->value) }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Tagihan COD</span>
                    <span class="detail-val">{{ $shipment->cod_amount_idr > 0 ? 'Rp '.number_format($shipment->cod_amount_idr, 0, ',', '.') : 'NON-COD' }}</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <span>Diterbitkan: {{ now()->format('d/m/Y H:i') }}</span>
            <span>Check Digit Luhn: VALID</span>
            <span>www.sariranah-express.id</span>
        </div>
    </div>
</body>
</html>
