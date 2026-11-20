@props(['active'])

@php
$classes = ($active ?? false)
            ? 'w-full inline-flex items-center px-6 py-4 font-Graphik text-lg text-piss-yellow bg-darker-3 transition duration-200 ease-in-out'
            : 'w-full inline-flex items-center px-6 py-4 font-Graphik text-lg text-secondary transition duration-200 ease-in-out hover:text-piss-yellow';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
