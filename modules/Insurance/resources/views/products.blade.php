<!DOCTYPE html>
<html>
<head><title>Produk Asuransi & Reasuransi</title></head>
<body>
    <h1>Katalog Produk Asuransi</h1>
    <ul>
        @foreach($products as $product)
            <li>{{ $product->product_code }} - {{ $product->name }} ({{ $product->line_category }})</li>
        @endforeach
    </ul>
</body>
</html>

