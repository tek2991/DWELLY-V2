<x-public-layout :filters="null">
    <div class="Site-Max-Width mx-auto px-4 mt-6">
        {{-- Mobile Top Book Button --}}
        <div class="flex justify-end lg:hidden mb-4">
            <button type="button"
                onclick="window.dispatchEvent(new CustomEvent('open-book-modal', { detail: { property_id: '{{ $property->id }}' } }))"
                class="bg-darker-3 text-piss-yellow px-6 py-2.5 rounded-sm text-sm font-GraphikMedium hover:bg-piss-yellow hover:text-darker-3 ease-in-out duration-300 cursor-pointer">
                BOOK A VISIT
            </button>
        </div>

        {{-- Photos Gallery Slider --}}
        @if ($propertyImages->isNotEmpty())
            <section id="property-gallery-slide" class="splide" aria-label="Property Images">
                <div class="splide__track rounded-lg overflow-hidden border border-gray-200">
                    <ul class="splide__list">
                        @foreach ($propertyImages as $image)
                            @php
                                $imgUrl = Storage::disk('public')->url($image->file_path);
                            @endphp
                            <li class="splide__slide">
                                <div class="aspect-16/9 md:aspect-21/9 bg-gray-100 overflow-hidden">
                                    <img src="{{ $imgUrl }}" alt="{{ $image->title ?: $property->building_name }}"
                                        class="w-full h-full object-cover">
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        {{-- Property Header Information --}}
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mt-6 pb-6 border-b border-gray-200 gap-4">
            <div class="space-y-1">
                <div class="text-xs uppercase tracking-wider font-GraphikMedium {{ $property->isAvailable() ? 'text-green-700' : 'text-darker-2' }}">
                    {{ $property->isAvailable() ? '● Available Now' : '● Available Soon' }}
                </div>
                <div class="flex items-center flex-wrap gap-2">
                    <h1 class="text-2xl md:text-3xl font-GraphikMedium text-darker-3">
                        {{ ($property->bhkType?->name ?? 'BHK') }} • {{ $property->propertyType?->name ?? 'Apartment' }}
                    </h1>
                    <x-property-share-button :property="$property" />
                </div>
                <h2 class="text-lg md:text-xl font-GraphikMedium text-darker-2">
                    {{ $property->building_name ?: 'Residential Home' }}
                </h2>
                <p class="text-sm text-darker-2">
                    {{ $property->address ?: ($property->landmark ?: 'Guwahati, Assam') }}
                    @if ($property->localityRef), {{ $property->localityRef->name }}@endif
                </p>
            </div>

            <div class="w-full lg:w-auto shrink-0 flex flex-col sm:flex-row items-center gap-4">
                <div class="bg-darker-3 text-white px-6 py-3 rounded-sm flex items-center gap-3 w-full sm:w-auto justify-between">
                    <span class="text-sm font-Graphik">Monthly Rent:</span>
                    <span class="text-piss-yellow font-GraphikBold text-2xl">₹{{ number_format($property->rent) }}</span>
                </div>
                <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-book-modal', { detail: { property_id: '{{ $property->id }}' } }))"
                    class="bg-piss-yellow text-darker-3 font-GraphikMedium px-6 py-3.5 rounded-sm text-sm hover:bg-yellow-400 transition w-full sm:w-auto text-center cursor-pointer">
                    BOOK A VISIT
                </button>
            </div>
        </div>

        {{-- Detailed Specs --}}
        <div class="py-8 border-b border-gray-200">
            <h3 class="text-lg font-GraphikSemibold text-darker-3 mb-4">Property Specifications</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-sm">
                @foreach ($primaryRooms as $room)
                    @php $count = $property->getNoOfRooms($room); @endphp
                    @if ($count > 0)
                        <div class="p-3 bg-gray-50 rounded-sm">
                            <span class="text-darker-2 block text-xs">{{ $room }}</span>
                            <span class="font-GraphikSemibold text-base text-darker-3">{{ $count }}</span>
                        </div>
                    @endif
                @endforeach

                <div class="p-3 bg-gray-50 rounded-sm">
                    <span class="text-darker-2 block text-xs">Floor</span>
                    <span class="font-GraphikSemibold text-base text-darker-3">{{ $property->floor ?: '1' }} of {{ $property->total_floors ?: '4' }}</span>
                </div>
                <div class="p-3 bg-gray-50 rounded-sm">
                    <span class="text-darker-2 block text-xs">Floor Space</span>
                    <span class="font-GraphikSemibold text-base text-darker-3">{{ $property->floor_space_sqft ? $property->floor_space_sqft . ' sqft' : '-' }}</span>
                </div>
                <div class="p-3 bg-gray-50 rounded-sm">
                    <span class="text-darker-2 block text-xs">Furnishing</span>
                    <span class="font-GraphikSemibold text-base text-darker-3">{{ $property->furnishingType?->name ?? 'Standard' }}</span>
                </div>
                <div class="p-3 bg-gray-50 rounded-sm">
                    <span class="text-darker-2 block text-xs">Flooring</span>
                    <span class="font-GraphikSemibold text-base text-darker-3">{{ $property->flooringType?->name ?? 'Vitrified' }}</span>
                </div>
            </div>
        </div>

        {{-- Amenities --}}
        @if ($amenities->isNotEmpty())
            <div class="py-8 border-b border-gray-200">
                <h3 class="text-lg font-GraphikSemibold text-darker-3 mb-4">Amenities & Features</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    @foreach ($amenities as $propAmenity)
                        <div class="flex items-center space-x-2 text-darker-3">
                            <span class="text-green-600 font-bold">✓</span>
                            <span>{{ $propAmenity->amenityType?->name ?? 'Amenity' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Points of Interest / Nearby Establishments --}}
        @if ($nearbyEstablishments->isNotEmpty())
            <div class="py-8 border-b border-gray-200">
                <h3 class="text-lg font-GraphikSemibold text-darker-3 mb-4">Nearby Establishments</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    @foreach ($nearbyEstablishments as $typeId => $items)
                        <div class="p-4 bg-gray-50 rounded-sm">
                            <h4 class="font-GraphikSemibold text-darker-3 text-xs uppercase tracking-wider mb-2">
                                {{ $items[0]->establishment?->establishmentType?->name ?? 'Point of Interest' }}
                            </h4>
                            <ul class="space-y-1">
                                @foreach ($items as $estItem)
                                    <li class="flex justify-between items-center text-xs">
                                        <span class="text-darker-3">{{ $estItem->remarks ?: ($estItem->establishment?->name ?? 'Nearby') }}</span>
                                        <span class="font-GraphikMedium text-darker-2">
                                            {{ $estItem->distance_km ? $estItem->distance_km . ' km' : 'Nearby' }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Financial Terms Breakdown --}}
        <div class="py-8 border-b border-gray-200">
            <h3 class="text-lg font-GraphikSemibold text-darker-3 mb-4">Amount Breakdown</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div class="p-4 bg-gray-50 rounded-sm">
                    <span class="text-darker-2 block text-xs">Monthly Rent</span>
                    <span class="font-GraphikBold text-lg text-darker-3">₹{{ number_format($property->rent) }}</span>
                </div>
                <div class="p-4 bg-gray-50 rounded-sm">
                    <span class="text-darker-2 block text-xs">Security Deposit</span>
                    <span class="font-GraphikBold text-lg text-darker-3">₹{{ number_format($property->security_deposit) }}</span>
                </div>
                <div class="p-4 bg-gray-50 rounded-sm">
                    <span class="text-darker-2 block text-xs">Society Fee</span>
                    <span class="font-GraphikBold text-lg text-darker-3">₹{{ number_format($property->society_fee) }}</span>
                </div>
                <div class="p-4 bg-gray-50 rounded-sm">
                    <span class="text-darker-2 block text-xs">One-time Service Fee</span>
                    <span class="font-GraphikBold text-lg text-darker-3">₹5,000</span>
                </div>
            </div>
        </div>

        {{-- Location / Map --}}
        <div class="py-8">
            <h3 class="text-lg font-GraphikSemibold text-darker-3 mb-4">Location</h3>
            @php
                $mapQuery = urlencode($property->latitude && $property->longitude ? ($property->latitude . ',' . $property->longitude) : ($property->building_name . ', ' . ($property->localityRef?->name ?? 'Guwahati')));
            @endphp
            <div class="rounded-lg overflow-hidden border border-gray-200 h-96">
                <iframe width="100%" height="100%" frameborder="0" style="border:0"
                    src="https://maps.google.com/maps?q={{ $mapQuery }}&output=embed"
                    allowfullscreen loading="lazy"></iframe>
            </div>
        </div>
    </div>

    @section('before-body-end')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (document.getElementById('property-gallery-slide')) {
                    new Splide('#property-gallery-slide', {
                        type: 'loop',
                        arrows: true,
                        pagination: true,
                        autoplay: true,
                        interval: 4000,
                        perPage: 1,
                    }).mount();
                }
            });
        </script>
    @endsection
</x-public-layout>
