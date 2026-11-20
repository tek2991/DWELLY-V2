<x-nav-link :href="route('home')" :active="request()->routeIs('home')">
    {{ __('Home') }}
</x-nav-link>
<x-nav-link :href="route('allProperties')" :active="request()->routeIs('allProperties')">
    {{ __('All Properties') }}
</x-nav-link>
<x-nav-link :href="route('owners')" :active="request()->routeIs('owners')">
    {{ __('Owners') }}
</x-nav-link>
<x-nav-link :href="route('about')" :active="request()->routeIs('about')">
    {{ __('About') }}
</x-nav-link>
<x-nav-link :href="route('contact')" :active="request()->routeIs('contact')">
    {{ __('Contact') }}
</x-nav-link>
