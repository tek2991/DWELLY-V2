<x-responsive-nav-link :href="route('home')" :active="request()->routeIs('home')">
    {{ __('Home') }}
</x-responsive-nav-link>
<x-responsive-nav-link :href="route('allProperties')" :active="request()->routeIs('allProperties')">
    {{ __('All Properties') }}
</x-responsive-nav-link>
<x-responsive-nav-link :href="route('owners')" :active="request()->routeIs('owners')">
    {{ __('Owners') }}
</x-responsive-nav-link>
<x-responsive-nav-link :href="route('about')" :active="request()->routeIs('about')">
    {{ __('About') }}
</x-responsive-nav-link>
<x-responsive-nav-link :href="route('contact')" :active="request()->routeIs('contact')">
    {{ __('Contact') }}
</x-responsive-nav-link>
