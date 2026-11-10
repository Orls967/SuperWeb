@props(['header' => null, 'title' => 'Dashboard'])

<x-ds.shell :title="$title">
    @if(isset($header))
        <x-slot name="header">
            {{ $header }}
        </x-slot>
    @endif

    {{ $slot ?? '' }}
    @yield('content')
</x-ds.shell>
