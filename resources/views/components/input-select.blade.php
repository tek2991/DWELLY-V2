@props(['disabled' => false])

<select {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full mt-1 border-2 border-secondary rounded-sm p-2 focus:ring-0 focus:border-piss-yellow disabled:bg-gray-100']) !!}>
    {{ $slot }}
</select>
