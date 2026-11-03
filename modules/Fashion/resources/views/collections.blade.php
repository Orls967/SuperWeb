<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Koleksi Busana & Sourcing Musiman (Lini 28)</title>
</head>
<body>
    <h1>Koleksi Busana & Sourcing Musiman</h1>
    <div>
        <form action="{{ route('fashion.collections.store') }}" method="POST">
            @csrf
            <input type="text" name="collection_code" placeholder="Kode Koleksi" required>
            <input type="text" name="name" placeholder="Nama Koleksi" required>
            <input type="text" name="season" placeholder="Musim (SS26/FW26)" required>
            <input type="number" name="year" value="2026" required>
            <button type="submit">Tambah Koleksi</button>
        </form>
    </div>
    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama</th>
                <th>Musim</th>
                <th>Tahun</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($collections as $c)
                <tr>
                    <td>{{ $c->collection_code }}</td>
                    <td>{{ $c->name }}</td>
                    <td>{{ $c->season }}</td>
                    <td>{{ $c->year }}</td>
                    <td>{{ $c->status }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada koleksi terdaftar.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
