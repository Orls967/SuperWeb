@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2>Lapangan Penumpukan Kontainer (Yard Blocks)</h2>
    <div class="yard-blocks my-4">
        @foreach($blocks as $block)
            <div class="card p-3 mb-2">
                <h4>{{ $block->code }} - {{ $block->name }} ({{ $block->block_type }})</h4>
                <p>Kapasitas: {{ $block->capacity_teu }} TEU | Terisi: {{ $block->slots->count() }} TEU</p>
            </div>
        @endforeach
    </div>
</div>
@endsection
