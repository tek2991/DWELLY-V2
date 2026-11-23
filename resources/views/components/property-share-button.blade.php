@props(['property' => null, 'iconOnly' => false])

<div class="flex" x-data="{ dropdownOpen: false }" @mouseleave="dropdownOpen = false">
    <button type="button" class="flex items-center px-4 tracking-wide @if(!$iconOnly) border-2 @endif rounded-full md:ml-4 cursor-pointer" @mouseover="dropdownOpen = true" @click="dropdownOpen = !dropdownOpen">
        <img src="{{ url('/resources/icons/share.svg') }}" alt="share icon" class="mr-2 h-5">
        @if (!$iconOnly)
            <span class="text-lg">
                Share
            </span>
        @endif
    </button>

    <div class="relative">
      @php
        $mt = $iconOnly ? 'mt-6' : 'mt-8';
        $propUrl = route('viewProperty', $property);
      @endphp
        <div x-show="dropdownOpen" x-cloak
            class="absolute right-0 {{ $mt }} w-48 bg-white rounded-md overflow-hidden shadow-xl z-30">
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($propUrl) }}" target="_blank" rel="noopener noreferrer" title="Share on Facebook" class="flex px-4 py-2 text-sm text-gray-800 border-b hover:bg-darker">
                <img src="{{ url('resources/icons/facebook.svg') }}" alt="" class="w-5 mr-3">
                <span class="text-gray-600">Facebook</span>
            </a>
            <a href="https://api.whatsapp.com/send?text={{ urlencode('Check out this property on Dwelly: ' . $propUrl) }}" target="_blank" rel="noopener noreferrer" title="Share on Whatsapp" class="flex px-4 py-2 text-sm text-gray-800 border-b hover:bg-darker">
                <img src="{{ url('resources/icons/whatsapp.svg') }}" alt="" class="w-5 mr-3">
                <span class="text-gray-600">Whatsapp</span>
            </a>
            <a href="mailto:?subject={{ urlencode('Property on Dwelly') }}&body={{ urlencode('Check out this property: ' . $propUrl) }}" title="Share on Email" class="flex px-4 py-2 text-sm text-gray-800 hover:bg-darker">
                <img src="{{ url('resources/icons/email.svg') }}" alt="" class="w-5 mr-3">
                <span class="text-gray-600">Email</span>
            </a>
        </div>
    </div>
</div>
