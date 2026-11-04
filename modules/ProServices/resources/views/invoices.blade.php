<!DOCTYPE html>
<html>
<head><title>Invoices</title></head>
<body>
    <h1>Invoices</h1>
    <ul>
        @foreach($invoices as $inv)
            <li>{{ $inv->invoice_code }} - Rp {{ number_format($inv->subtotal_idr) }}</li>
        @endforeach
    </ul>
</body>
</html>
