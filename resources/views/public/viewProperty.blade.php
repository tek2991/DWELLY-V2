<x-public-layout :filters="null">
    @php
        $showCode = $showPropertyCode ?? \App\Domain\Shared\Services\SettingService::shouldShowPropertyCode();
        $galleryImages = $propertyImages->map(function ($img) use ($property) {
            return [
                'url' => !empty($img->file_path) ? Storage::disk('public')->url($img->file_path) : url('resources/images/sample_prop.jpeg'),
                'title' => $img->title ?: ($property->building_name ?: 'Property Photo'),
            ];
        })->values();

        if ($galleryImages->isEmpty()) {
            $galleryImages = collect([
                [
                    'url' => url('resources/images/sample_prop.jpeg'),
                    'title' => $property->building_name ?: 'Property Photo',
                ]
            ]);
        }
    @endphp

    <div x-data="{
        galleryOpen: false,
        currentIndex: 0,
        touchStartX: 0,
        touchEndX: 0,
        images: {{ Js::from($galleryImages) }},
        openGallery(index = 0) {
            this.currentIndex = index;
            this.galleryOpen = true;
            document.body.classList.add('overflow-hidden');
            this.scrollToActiveThumb();
        },
        closeGallery() {
            this.galleryOpen = false;
            document.body.classList.remove('overflow-hidden');
        },
        next() {
            if (this.images.length === 0) return;
            this.currentIndex = (this.currentIndex + 1) % this.images.length;
            this.scrollToActiveThumb();
        },
        prev() {
            if (this.images.length === 0) return;
            this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
            this.scrollToActiveThumb();
        },
        select(index) {
            this.currentIndex = index;
            this.scrollToActiveThumb();
        },
        handleSwipe() {
            const diff = this.touchStartX - this.touchEndX;
            if (Math.abs(diff) > 40) {
                if (diff > 0) this.next();
                else this.prev();
            }
        },
        scrollToActiveThumb() {
            this.$nextTick(() => {
                const el = document.getElementById('gallery-thumb-' + this.currentIndex);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                }
            });
        }
    }"
    @keydown.window.escape="if (galleryOpen) closeGallery()"
    @keydown.window.arrow-left="if (galleryOpen) prev()"
    @keydown.window.arrow-right="if (galleryOpen) next()"
    class="Site-Max-Width mx-auto px-4 mt-4 sm:mt-6 pb-24 lg:pb-16">
        {{-- Breadcrumbs & Quick Back --}}
        <nav class="flex items-center text-xs sm:text-sm text-darker-2 mb-3 space-x-1.5 flex-wrap">
            <a href="{{ route('home') }}" class="hover:text-darker-3 transition">Home</a>
            <span>/</span>
            <a href="{{ route('allProperties') }}" class="hover:text-darker-3 transition">Properties</a>
            @if ($property->localityRef)
                <span>/</span>
                <a href="{{ route('allProperties', ['localities' => [$property->localityRef->id]]) }}" class="hover:text-darker-3 transition">{{ $property->localityRef->name }}</a>
            @endif
            <span>/</span>
            <span class="text-darker-3 font-GraphikMedium truncate max-w-xs sm:max-w-md">{{ $property->building_name ?: 'Property Details' }}</span>
        </nav>

        {{-- Property Header Information --}}
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 pb-4 sm:pb-6 border-b border-gray-200">
            <div class="space-y-2 min-w-0 w-full md:w-auto">
                <div class="flex items-center gap-2 flex-wrap">
                    @if ($property->isAvailable())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-GraphikMedium bg-green-50 text-green-700 border border-green-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span>
                            Available Now
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-GraphikMedium bg-amber-50 text-amber-700 border border-amber-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                            Available {{ $property->available_from ? $property->available_from->diffForHumans() : 'Soon' }}
                        </span>
                    @endif

                    @if ($showCode && $property->code)
                        <span class="text-xs text-gray-500 font-mono bg-gray-100 px-2 py-0.5 rounded">
                            ID: {{ $property->code }}
                        </span>
                    @endif
                </div>

                <h1 class="text-xl sm:text-3xl lg:text-4xl font-GraphikMedium text-darker-3 leading-tight">
                    {{ $property->bhkType?->name ?? 'BHK' }} • {{ $property->propertyType?->name ?? 'Apartment' }} in {{ $property->building_name }}
                </h1>

                <p class="text-xs sm:text-base text-darker-2 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-piss-yellow shrink-0" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                    </svg>
                    {{-- Clean concise locality on mobile to prevent 4-line deed repetition --}}
                    <span class="sm:hidden truncate">
                        {{ $property->localityRef?->name ?? ($property->locality ?: 'Guwahati') }}, {{ $property->city ?? 'Assam' }}{{ $property->pincode ? ' - ' . $property->pincode : '' }}
                    </span>
                    {{-- Full address on desktop --}}
                    <span class="hidden sm:inline">
                        {{ $property->address ?: $property->building_name }}@if($property->localityRef), {{ $property->localityRef->name }}@endif{{ $property->pincode ? ' - ' . $property->pincode : '' }}
                    </span>
                </p>

                {{-- Mobile-Only Hero Pricing Strip (Surfaces price immediately without scrolling) --}}
                <div class="sm:hidden pt-3 mt-1 flex items-center justify-between border-t border-gray-100">
                    <div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-2xl font-GraphikBold text-darker-3">₹{{ number_format($property->rent) }}</span>
                            <span class="text-xs text-darker-2 font-Graphik">/ mo</span>
                        </div>
                        <div class="text-[11px] text-darker-2 flex items-center gap-1.5 mt-0.5">
                            @if ($property->society_fee > 0)
                                <span>+ ₹{{ number_format($property->society_fee) }} maint.</span>
                                <span>•</span>
                            @else
                                <span class="text-green-700 font-GraphikMedium">Maint. incl.</span>
                                <span>•</span>
                            @endif
                            <span>Dep: ₹{{ number_format($property->security_deposit) }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <x-property-share-button :property="$property" />
                    </div>
                </div>
            </div>

            {{-- Quick Action Buttons on Desktop --}}
            <div class="hidden sm:flex shrink-0 items-center gap-2.5 self-start pt-1">
                <x-property-share-button :property="$property" />
            </div>
        </div>

        {{-- Photos Gallery Slider --}}
        <div class="mt-6 relative group">
            <section id="property-gallery-slide" class="splide" aria-label="Property Images">
                <div class="splide__track rounded-xl overflow-hidden border border-gray-200 bg-gray-900 shadow-xs">
                    <ul class="splide__list">
                        @php
                            $slides = $propertyImages;
                            if ($slides->isEmpty()) {
                                $slides = collect([(object)['file_path' => null, 'title' => $property->building_name]]);
                            }
                        @endphp
                        @foreach ($slides as $image)
                            @php
                                $imgUrl = !empty($image->file_path) ? Storage::disk('public')->url($image->file_path) : url('resources/images/sample_prop.jpeg');
                            @endphp
                            <li class="splide__slide cursor-pointer" @click="openGallery({{ $loop->index }})">
                                <div class="aspect-16/9 md:aspect-21/9 bg-gray-950 flex items-center justify-center overflow-hidden relative">
                                    <img src="{{ $imgUrl }}" alt="{{ $image->title ?? $property->building_name }}"
                                        class="w-full h-full object-cover group-hover:scale-[1.01] transition duration-300">
                                    <div class="absolute inset-0 bg-black/15 opacity-0 hover:opacity-100 transition flex items-center justify-center">
                                        <span class="bg-black/70 text-white text-xs px-3.5 py-1.5 rounded-full flex items-center gap-1.5 backdrop-blur-xs font-GraphikMedium">
                                            <svg class="w-4 h-4 text-piss-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path></svg>
                                            Click to view full photo
                                        </span>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- "View all X photos" floating pill --}}
            <button type="button" @click="openGallery(0)"
                class="absolute bottom-3 right-3 sm:bottom-4 sm:right-4 z-10 bg-black/75 hover:bg-black/90 text-white text-xs sm:text-sm font-GraphikMedium px-3.5 sm:px-4 py-1.5 sm:py-2 rounded-lg backdrop-blur-md flex items-center gap-2 transition shadow-md cursor-pointer">
                <svg class="w-4 h-4 text-piss-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>View all {{ $slides->count() }} photos</span>
            </button>
        </div>

        {{-- Split-Pane Grid: Main Details (Left 8 cols) vs Sticky Pricing Card (Right 4 cols) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10 mt-6 sm:mt-8 items-start">
            {{-- MAIN COLUMN (65% on Desktop) --}}
            <div class="lg:col-span-8 space-y-5 sm:space-y-8 min-w-0">

                {{-- 1. Key Specifications Strip --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-xs">
                    <h2 class="text-base sm:text-lg font-GraphikSemibold text-darker-3 mb-3 sm:mb-4">Property Overview</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5 sm:gap-4">
                        <div class="p-2.5 sm:p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <span class="text-darker-2 block text-[11px] sm:text-xs">Configuration</span>
                            <span class="font-GraphikSemibold text-sm sm:text-base text-darker-3">{{ $property->bhkType?->name ?? 'BHK' }}</span>
                        </div>
                        <div class="p-2.5 sm:p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <span class="text-darker-2 block text-[11px] sm:text-xs">Floor Space</span>
                            <span class="font-GraphikSemibold text-sm sm:text-base text-darker-3">
                                {{ $property->floor_space_sqft ? number_format($property->floor_space_sqft) . ' sqft' : '-' }}
                            </span>
                        </div>
                        <div class="p-2.5 sm:p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <span class="text-darker-2 block text-[11px] sm:text-xs">Floor Level</span>
                            <span class="font-GraphikSemibold text-sm sm:text-base text-darker-3">
                                {{ $property->floor ?: '1' }} of {{ $property->total_floors ?: '1' }}
                            </span>
                        </div>
                        <div class="p-2.5 sm:p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <span class="text-darker-2 block text-[11px] sm:text-xs">Furnishing</span>
                            <span class="font-GraphikSemibold text-sm sm:text-base text-darker-3 truncate block">
                                {{ $property->furnishingType?->name ?? 'Standard' }}
                            </span>
                        </div>
                        <div class="p-2.5 sm:p-3 bg-gray-50 rounded-lg border border-gray-100 col-span-2 sm:col-span-1 flex items-center justify-between sm:block">
                            <span class="text-darker-2 block text-[11px] sm:text-xs">Flooring</span>
                            <span class="font-GraphikSemibold text-sm sm:text-base text-darker-3">
                                {{ $property->flooringType?->name ?? 'Vitrified' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- 2. Room Breakdown & Spaces --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-xs">
                    <div class="flex items-center justify-between mb-3 sm:mb-4 flex-wrap gap-2">
                        <h2 class="text-base sm:text-lg font-GraphikSemibold text-darker-3">Room Configuration & Spaces</h2>
                        <div class="text-xs text-darker-2 font-GraphikMedium">
                            {{ $property->getNoOfRooms('Bedroom') }} Beds • {{ $property->getNoOfRooms('Bathroom') }} Baths
                            @if ($property->getNoOfRooms('Balcony') > 0) • {{ $property->getNoOfRooms('Balcony') }} Balcony @endif
                            @if ($property->getNoOfRooms('Kitchen') > 0) • Kitchen @endif
                        </div>
                    </div>

                    {{-- Room Counts Summary Grid (Showing only available rooms, filtering out 0-counts) --}}
                    @php
                        $filteredRooms = collect($primaryRooms)->filter(function ($room) use ($property) {
                            return $property->getNoOfRooms($room) > 0;
                        });
                        if ($filteredRooms->isEmpty()) {
                            $filteredRooms = collect(['Bedroom', 'Bathroom', 'Kitchen']);
                        }
                    @endphp
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 sm:gap-3 mb-4">
                        @foreach ($filteredRooms as $room)
                            @php $count = $property->getNoOfRooms($room); @endphp
                            <div class="p-2.5 sm:p-3 rounded-lg border bg-amber-50/50 border-amber-200/60 flex items-center justify-between">
                                <span class="text-xs font-GraphikMedium text-darker-2">{{ $room }}</span>
                                <span class="text-sm font-GraphikBold text-darker-3">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Individual Room Spaces (Modern horizontal wrapping chips instead of vertical stacked rows) --}}
                    @if ($property->rooms->isNotEmpty())
                        <div class="pt-3 sm:pt-4 border-t border-gray-100">
                            <span class="text-xs font-GraphikMedium uppercase tracking-wider text-darker-2 block mb-2.5">Included Spaces</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($property->rooms as $room)
                                    @php
                                        $roomTitle = $room->custom_name ?: ($room->roomDefinition?->name ?? 'Room');
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 hover:bg-gray-100 rounded-full border border-gray-200/80 text-xs text-darker-3 font-Graphik transition">
                                        <svg class="w-3.5 h-3.5 text-piss-yellow shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                        </svg>
                                        <span>{{ $roomTitle }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- 3. Furnishing & Inclusions --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-xs">
                    <div class="flex items-center justify-between mb-3 sm:mb-4 flex-wrap gap-2">
                        <div>
                            <h2 class="text-base sm:text-lg font-GraphikSemibold text-darker-3">Furnishing & Inclusions</h2>
                            <p class="text-xs text-darker-2 mt-0.5">
                                Status: <span class="font-GraphikMedium text-darker-3">{{ $property->furnishingType?->name ?? 'Standard' }}</span>
                            </p>
                        </div>
                        @if ($furnitures->isNotEmpty())
                            <span class="text-xs font-GraphikMedium px-2.5 py-1 bg-yellow-50 text-yellow-800 border border-yellow-200 rounded-full">
                                {{ $furnitures->sum('count') ?: $furnitures->count() }} Items Included
                            </span>
                        @endif
                    </div>

                    @if ($furnitures->isNotEmpty())
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 sm:gap-3">
                            @foreach ($furnitures as $furniture)
                                @php
                                    $itemName = $furniture->inventoryType?->name ?? 'Item';
                                    $itemCount = $furniture->count ?: 1;
                                @endphp
                                <div class="p-2 sm:p-3 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-between gap-1.5 sm:gap-2">
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center shrink-0 text-darker-3">
                                            @if (stripos($itemName, 'bed') !== false)
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-darker-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M5 18v3m14-3v3M5 6v4m14-4v4"></path></svg>
                                            @elseif (stripos($itemName, 'sofa') !== false || stripos($itemName, 'chair') !== false)
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-darker-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V7a2 2 0 012-2h14a2 2 0 012 2v3a2 2 0 01-2 2M5 12v6m14-6v6"></path></svg>
                                            @elseif (stripos($itemName, 'fan') !== false)
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-darker-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4m13.657-5.657L6.343 17.657m0-11.314l11.314 11.314"></path></svg>
                                            @elseif (stripos($itemName, 'light') !== false)
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-darker-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                                            @elseif (stripos($itemName, 'conditioner') !== false || stripos($itemName, 'ac') !== false)
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-darker-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21a9 9 0 110-18 9 9 0 010 18z"></path></svg>
                                            @elseif (stripos($itemName, 'television') !== false || stripos($itemName, 'tv') !== false)
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-darker-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                            @elseif (stripos($itemName, 'wardrobe') !== false)
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-darker-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16a1 1 0 001 1h14a1 1 0 001-1V4a1 1 0 00-1-1H5a1 1 0 00-1 1zm8-1v18m-4-10v2m8-2v2"></path></svg>
                                            @elseif (stripos($itemName, 'dining') !== false)
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-darker-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M4 6v12m16-12v12M9 6v6m6-6v6"></path></svg>
                                            @elseif (stripos($itemName, 'key') !== false)
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-darker-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                            @else
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-darker-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                            @endif
                                        </div>
                                        <span class="text-xs font-GraphikMedium text-darker-3 truncate" title="{{ $itemName }}">
                                            {{ $itemName }}
                                        </span>
                                    </div>
                                    <span class="text-[11px] sm:text-xs font-GraphikBold bg-white px-1.5 sm:px-2 py-0.5 rounded border border-gray-200 text-darker-3 shrink-0">
                                        ×{{ $itemCount }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-4 sm:p-6 bg-gray-50 rounded-lg text-center border border-gray-100">
                            <p class="text-xs sm:text-sm text-darker-2">
                                Standard {{ strtolower($property->furnishingType?->name ?? 'unfurnished') }} fittings provided. Contact our property manager for customized furnishing requests.
                            </p>
                        </div>
                    @endif
                </div>

                {{-- 4. Society & Building Amenities (Highlighting confirmed features, folding unavailable features) --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-xs">
                    <h2 class="text-base sm:text-lg font-GraphikSemibold text-darker-3 mb-3 sm:mb-4">Society & Building Features</h2>
                    @php
                        $amenityList = isset($allAmenities) && $allAmenities->isNotEmpty() ? $allAmenities : $amenities->map(fn($a) => $a->amenityType)->filter();
                        $availableAmenities = $amenityList->filter(fn($a) => $property->checkPropertyAmenity($a->name));
                        $unavailableAmenities = $amenityList->reject(fn($a) => $property->checkPropertyAmenity($a->name));
                    @endphp

                    {{-- Confirmed / Available Amenities --}}
                    @if ($availableAmenities->isNotEmpty())
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 sm:gap-3">
                            @foreach ($availableAmenities as $amenity)
                                <div class="p-2.5 sm:p-3 rounded-lg border bg-green-50/50 border-green-200 flex items-center gap-2 sm:gap-2.5">
                                    <span class="w-5 h-5 rounded-full bg-green-600 text-white flex items-center justify-center shrink-0 text-xs font-bold">✓</span>
                                    <span class="text-xs font-GraphikMedium text-darker-3 truncate">{{ $amenity->name }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-darker-2">Basic society facilities apply. Inquire for specific amenities.</p>
                    @endif

                    {{-- Unavailable Amenities (Collapsible toggle to eliminate 6 gray boxes cluttering mobile view) --}}
                    @if ($unavailableAmenities->isNotEmpty())
                        <div x-data="{ showAll: false }" class="mt-3 pt-3 border-t border-gray-100">
                            <button type="button" @click="showAll = !showAll"
                                class="text-xs font-GraphikMedium text-darker-2 hover:text-darker-3 flex items-center gap-1.5 transition cursor-pointer">
                                <span x-text="showAll ? 'Hide features not included' : '+ View features not included ({{ $unavailableAmenities->count() }})'"></span>
                                <svg class="w-3.5 h-3.5 transition-transform" :class="showAll ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div x-show="showAll" x-cloak x-transition class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 sm:gap-3 mt-3">
                                @foreach ($unavailableAmenities as $amenity)
                                    <div class="p-2 sm:p-2.5 rounded-lg border bg-gray-50/70 border-gray-100 text-gray-400 flex items-center gap-2">
                                        <span class="w-4 h-4 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center shrink-0 text-[10px]">✕</span>
                                        <span class="text-xs font-Graphik text-gray-400 line-through truncate">{{ $amenity->name }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- 5. Full Address & Landmark Details --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-xs">
                    <h2 class="text-base sm:text-lg font-GraphikSemibold text-darker-3 mb-3 sm:mb-4">Location & Address Details</h2>
                    <div class="space-y-2.5 text-xs sm:text-sm font-Graphik">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-2 py-2 border-b border-gray-100">
                            <span class="text-darker-2">Building Name</span>
                            <span class="sm:col-span-2 text-darker-3 font-GraphikMedium">{{ $property->building_name ?: 'Residential Society' }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-2 py-2 border-b border-gray-100">
                            <span class="text-darker-2">Complete Address</span>
                            <span class="sm:col-span-2 text-darker-3 leading-relaxed">{{ $property->address ?: 'Address provided upon inquiry' }}</span>
                        </div>
                        @if ($property->landmark)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-2 py-2 border-b border-gray-100">
                                <span class="text-darker-2">Landmark</span>
                                <span class="sm:col-span-2 text-darker-3 font-GraphikMedium">{{ $property->landmark }}</span>
                            </div>
                        @endif
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-2 py-2">
                            <span class="text-darker-2">Locality & City</span>
                            <span class="sm:col-span-2 text-darker-3">
                                {{ $property->localityRef?->name ?? 'Guwahati' }}, Assam{{ $property->pincode ? ' - ' . $property->pincode : '' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- 6. Points of Interest & Commute --}}
                @if ($nearbyEstablishments->isNotEmpty())
                    <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-xs">
                        <h2 class="text-base sm:text-lg font-GraphikSemibold text-darker-3 mb-3 sm:mb-4">Nearby Establishments & Commute</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            @foreach ($nearbyEstablishments as $typeId => $items)
                                @php
                                    $firstEst = $items[0] ?? null;
                                    $typeName = $firstEst?->establishment?->establishmentType?->name ?? 'Points of Interest';
                                @endphp
                                <div class="p-3 sm:p-4 bg-gray-50 rounded-lg border border-gray-100">
                                    <h3 class="font-GraphikSemibold text-darker-3 text-xs uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-piss-yellow"></span>
                                        {{ $typeName }}
                                    </h3>
                                    <ul class="space-y-1.5">
                                        @foreach ($items as $estItem)
                                            @php
                                                $estName = $estItem->remarks ?: ($estItem->establishment?->name ?? 'Nearby Location');
                                                $dist = $estItem->distance_km ?? $estItem->distance_in_kms ?? 0;
                                            @endphp
                                            <li class="flex justify-between items-center text-xs">
                                                <span class="text-darker-3 truncate pr-2">{{ $estName }}</span>
                                                <span class="font-GraphikMedium text-darker-2 bg-white px-2 py-0.5 rounded border border-gray-100 shrink-0">
                                                    {{ $dist < 1 ? 'Nearby' : $dist . ' km' }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- 7. Location Map Embed --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-xs">
                    <h2 class="text-base sm:text-lg font-GraphikSemibold text-darker-3 mb-3 sm:mb-4">Neighborhood Location</h2>
                    @php
                        $lat = $property->latitude;
                        $lng = $property->longitude;
                        $apiKey = config('services.google_maps.key');
                        $mapQuery = urlencode($lat && $lng ? ($lat . ',' . $lng) : ($property->building_name . ', ' . ($property->localityRef?->name ?? 'Guwahati')));
                    @endphp
                    <div class="rounded-lg overflow-hidden border border-gray-200 h-64 sm:h-80 w-full bg-gray-100">
                        @if ($apiKey)
                            <iframe width="100%" height="100%" frameborder="0" style="border:0"
                                src="https://www.google.com/maps/embed/v1/place?key={{ $apiKey }}&q={{ $lat }},+{{ $lng }}"
                                allowfullscreen loading="lazy"></iframe>
                        @else
                            <iframe width="100%" height="100%" frameborder="0" style="border:0"
                                src="https://maps.google.com/maps?q={{ $mapQuery }}&output=embed"
                                allowfullscreen loading="lazy"></iframe>
                        @endif
                    </div>
                </div>
            </div>

            {{-- STICKY SIDEBAR (35% on Desktop - Sticky Action & Pricing Card) --}}
            <div class="lg:col-span-4 w-full">
                <div class="lg:sticky lg:top-28 space-y-5">
                    {{-- Main Financial & Booking Card --}}
                    <div class="bg-white rounded-xl border border-gray-200 p-5 sm:p-6 shadow-md overflow-hidden relative">
                        {{-- Top Accent Bar --}}
                        <div class="absolute top-0 inset-x-0 h-1.5 bg-piss-yellow"></div>

                        {{-- Monthly Rent Header --}}
                        <div class="mb-5">
                            <span class="text-xs uppercase tracking-wider font-GraphikMedium text-darker-2 block">Monthly Rent</span>
                            <div class="flex items-baseline gap-1 mt-1">
                                @if ($property->rent > 0)
                                    <span class="text-3xl sm:text-4xl font-GraphikBold text-darker-3">₹{{ number_format($property->rent) }}</span>
                                    <span class="text-sm font-Graphik text-darker-2">/ month</span>
                                @else
                                    <span class="text-2xl sm:text-3xl font-GraphikBold text-darker-3">Price on Request</span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-darker-2">
                                @if ($property->society_fee > 0)
                                    <span>+ ₹{{ number_format($property->society_fee) }}/mo society maintenance</span>
                                @else
                                    <span class="text-green-700 font-GraphikMedium">Society maintenance included</span>
                                @endif
                            </div>
                        </div>

                        {{-- Transparent Financial Terms Breakdown --}}
                        <div class="border-t border-b border-gray-100 py-4 my-4 space-y-2.5 text-xs sm:text-sm font-Graphik">
                            <div class="flex justify-between items-center">
                                <span class="text-darker-2">Monthly Rent</span>
                                <span class="font-GraphikSemibold text-darker-3">{{ $property->rent > 0 ? '₹' . number_format($property->rent) : 'On Request' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-darker-2">Security Deposit</span>
                                    <span class="text-[10px] bg-green-50 text-green-700 border border-green-200 px-1.5 py-0.2 rounded font-medium">Refundable</span>
                                </div>
                                <span class="font-GraphikSemibold text-darker-3">{{ $property->security_deposit > 0 ? '₹' . number_format($property->security_deposit) : 'On Request' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-darker-2">Society Maintenance</span>
                                <span class="font-GraphikSemibold text-darker-3">{{ $property->society_fee > 0 ? '₹' . number_format($property->society_fee) : 'Included' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-darker-2">One-time Service Fee</span>
                                    <span class="text-[10px] text-gray-400">Fixed</span>
                                </div>
                                <span class="font-GraphikSemibold text-darker-3">₹5,000</span>
                            </div>

                            @php
                                $totalMoveIn = $property->rent + $property->security_deposit + $property->society_fee + 5000;
                            @endphp
                            <div class="pt-2 border-t border-dashed border-gray-200 flex justify-between items-center text-xs">
                                <span class="font-GraphikMedium text-darker-3">Estimated Move-in Total</span>
                                <span class="font-GraphikBold text-darker-3">{{ $property->rent > 0 ? '₹' . number_format($totalMoveIn) : 'On Request' }}</span>
                            </div>
                        </div>

                        {{-- Primary Actions --}}
                        <div class="space-y-3 pt-2">
                            <button type="button"
                                onclick="window.dispatchEvent(new CustomEvent('open-book-modal', { detail: { property_id: '{{ $property->id }}' } }))"
                                class="w-full bg-piss-yellow text-darker-3 hover:bg-darker-3 hover:text-piss-yellow transition-colors duration-200 ease-in-out font-GraphikMedium py-3.5 px-4 rounded-sm text-center text-base shadow-xs cursor-pointer tracking-wide flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span>BOOK A VISIT</span>
                            </button>
                        </div>

                        {{-- Dwelly Guarantee Badges --}}
                        <div class="mt-6 pt-5 border-t border-gray-100 space-y-2 text-xs text-darker-2">
                            <div class="flex items-center gap-2">
                                <span class="text-green-600 font-bold">✓</span>
                                <span>100% Verified Dwelly Property</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-green-600 font-bold">✓</span>
                                <span>Zero Paperwork Hassle with Online Agreement</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-green-600 font-bold">✓</span>
                                <span>Dedicated Property Manager Support</span>
                            </div>
                        </div>
                    </div>

                    {{-- Need Assistance Card --}}
                    <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 text-center">
                        <h3 class="font-GraphikMedium text-darker-3 text-sm mb-1">Have Questions About This Property?</h3>
                        <p class="text-xs text-darker-2 mb-3">Our Guwahati rental team is available 7 days a week.</p>
                        <div class="flex justify-center items-center gap-3">
                            <a href="tel:+919678644832" class="inline-flex items-center gap-1.5 text-xs font-GraphikMedium text-darker-3 bg-white px-3 py-2 rounded border border-gray-200 hover:bg-piss-yellow transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                <span>+91 96786 44832</span>
                            </a>
                            <a href="https://api.whatsapp.com/send?text={{ urlencode('Hello Dwelly, I would like to inquire about property ' . (($showCode && $property->code) ? $property->code : $property->building_name) . ' (' . route('viewProperty', $property) . ')') }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs font-GraphikMedium text-green-800 bg-green-50 px-3 py-2 rounded border border-green-200 hover:bg-green-100 transition">
                                <img src="{{ url('resources/icons/whatsapp.svg') }}" alt="WhatsApp" class="w-3.5 h-3.5">
                                <span>WhatsApp</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Full-Page Modal Gallery --}}
        <div x-show="galleryOpen" x-cloak
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 bg-black/95 flex flex-col justify-between"
            role="dialog"
            aria-modal="true">

            {{-- Top Header Bar --}}
            <div class="flex items-center justify-between px-4 sm:px-8 py-3.5 text-white z-20 bg-gradient-to-b from-black/80 to-transparent">
                {{-- Property Details on Left --}}
                <div class="flex items-center gap-3 min-w-0">
                    @if ($showCode && $property->code)
                        <span class="text-xs uppercase tracking-wider font-mono text-piss-yellow hidden sm:inline bg-white/10 px-2 py-0.5 rounded">
                            {{ $property->code }}
                        </span>
                    @endif
                    <div class="truncate">
                        <h3 class="text-sm sm:text-base font-GraphikMedium truncate text-white">
                            {{ $property->building_name }}
                        </h3>
                        <p class="text-xs text-gray-400 truncate">
                            {{ $property->bhkType?->name }} • {{ $property->localityRef?->name ?? ($property->locality ?: 'Guwahati') }}
                        </p>
                    </div>
                </div>

                {{-- Photo Counter in Center --}}
                <div class="px-3.5 py-1 rounded-full bg-white/15 backdrop-blur-md text-xs sm:text-sm font-GraphikMedium">
                    <span x-text="(currentIndex + 1) + ' / ' + images.length"></span>
                </div>

                {{-- Close Button on Right --}}
                <div class="flex items-center gap-2">
                    <button type="button" @click="closeGallery()"
                        class="text-gray-300 hover:text-white bg-white/10 hover:bg-white/20 p-2 sm:p-2.5 rounded-full transition cursor-pointer"
                        title="Close Gallery (Esc)">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Main Image Area with Previous & Next Chevrons --}}
            <div class="relative flex-1 flex items-center justify-center p-2 sm:p-6 min-h-0 overflow-hidden"
                 @touchstart="touchStartX = $event.touches[0].clientX"
                 @touchend="touchEndX = $event.changedTouches[0].clientX; handleSwipe()">

                {{-- Previous Button --}}
                <button type="button" @click="prev()"
                    class="absolute left-2 sm:left-6 z-20 text-white bg-black/60 hover:bg-black/90 p-2.5 sm:p-3.5 rounded-full backdrop-blur-md transition cursor-pointer hover:scale-110 active:scale-95"
                    title="Previous Photo (Left Arrow)">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>

                {{-- Active Image Stage --}}
                <div class="flex flex-col items-center justify-center w-full h-full max-h-[75vh]">
                    <template x-for="(img, idx) in images" :key="idx">
                        <img x-show="currentIndex === idx"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             :src="img.url"
                             :alt="img.title"
                             class="max-w-full max-h-[66vh] sm:max-h-[72vh] object-contain rounded-lg shadow-2xl">
                    </template>

                    {{-- Photo Caption / Room Name --}}
                    <div class="mt-3 text-center" x-show="images[currentIndex]?.title">
                        <span class="text-xs sm:text-sm font-GraphikMedium text-gray-300 bg-black/70 px-4 py-1.5 rounded-full backdrop-blur-xs"
                              x-text="images[currentIndex]?.title"></span>
                    </div>
                </div>

                {{-- Next Button --}}
                <button type="button" @click="next()"
                    class="absolute right-2 sm:right-6 z-20 text-white bg-black/60 hover:bg-black/90 p-2.5 sm:p-3.5 rounded-full backdrop-blur-md transition cursor-pointer hover:scale-110 active:scale-95"
                    title="Next Photo (Right Arrow)">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>

            {{-- Bottom Thumbnail Strip --}}
            <div class="bg-black/85 backdrop-blur-md border-t border-white/10 px-4 py-3 z-20">
                <div class="flex items-center gap-2.5 overflow-x-auto py-1 max-w-5xl mx-auto scrollbar-none" id="gallery-thumbs-container">
                    <template x-for="(img, idx) in images" :key="idx">
                        <button type="button" @click="select(idx)"
                            :id="'gallery-thumb-' + idx"
                            class="relative shrink-0 w-16 h-12 sm:w-20 sm:h-14 rounded-md overflow-hidden transition-all duration-200 cursor-pointer border-2"
                            :class="currentIndex === idx ? 'border-piss-yellow scale-105 opacity-100 shadow-md ring-2 ring-piss-yellow/40' : 'border-transparent opacity-50 hover:opacity-90'">
                            <img :src="img.url" :alt="img.title" class="w-full h-full object-cover">
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Floating Bottom Action Bar for Mobile Screens --}}
    <div class="fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur-md border-t border-gray-200 px-4 py-2.5 flex items-center justify-between shadow-lg lg:hidden">
        <div>
            <div class="text-[10px] text-gray-500 uppercase tracking-wider font-GraphikMedium">Monthly Rent</div>
            <div class="text-lg font-GraphikBold text-darker-3 leading-tight">
                @if ($property->rent > 0)
                    ₹{{ number_format($property->rent) }}
                    <span class="text-xs font-normal text-gray-500">/ mo</span>
                @else
                    <span class="text-sm">Price on Request</span>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="tel:+919678644832"
               class="p-2.5 rounded-sm border border-gray-300 bg-white text-darker-3 hover:bg-gray-50 transition"
               title="Call Dwelly Support">
                <svg class="w-4 h-4 text-darker-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                </svg>
            </a>
            <a href="https://api.whatsapp.com/send?text={{ urlencode('Hello Dwelly, I would like to inquire about property ' . (($showCode && $property->code) ? $property->code : $property->building_name) . ' (' . route('viewProperty', $property) . ')') }}"
               target="_blank" rel="noopener noreferrer"
               class="p-2.5 rounded-sm border border-green-200 bg-green-50 text-green-700 hover:bg-green-100 transition"
               title="Chat on WhatsApp">
                <img src="{{ url('resources/icons/whatsapp.svg') }}" alt="WhatsApp" class="w-4 h-4">
            </a>
            <button type="button"
                onclick="window.dispatchEvent(new CustomEvent('open-book-modal', { detail: { property_id: '{{ $property->id }}' } }))"
                class="bg-piss-yellow text-darker-3 hover:bg-darker-3 hover:text-piss-yellow transition-colors font-GraphikMedium px-4 sm:px-6 py-2.5 rounded-sm text-xs sm:text-sm shadow-xs cursor-pointer tracking-wide uppercase">
                Book Visit
            </button>
        </div>
    </div>

    @section('before-body-end')
        <style>
            @media (max-width: 640px) {
                #property-gallery-slide .splide__pagination {
                    display: none !important;
                }
            }
        </style>
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
                        breakpoints: {
                            640: {
                                pagination: false,
                            }
                        }
                    }).mount();
                }
            });
        </script>
    @endsection
</x-public-layout>
