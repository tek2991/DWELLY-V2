<x-public-layout :filters="$filters">
    <div x-data="{ mobileFiltersOpen: false }" @toggle-filters.window="mobileFiltersOpen = !mobileFiltersOpen" @open-filters.window="mobileFiltersOpen = true" @close-filters.window="mobileFiltersOpen = false">
        {{-- Mobile Filter Drawer (Slide out from Left) --}}
        <div x-show="mobileFiltersOpen" x-cloak
            class="fixed inset-0 z-50 flex sm:hidden"
            @keydown.escape.window="mobileFiltersOpen = false">
            
            {{-- Backdrop --}}
            <div x-show="mobileFiltersOpen"
                x-transition:enter="transition-opacity ease-linear duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-linear duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-black/50"
                @click="mobileFiltersOpen = false"></div>

            {{-- Slide-out Drawer Panel from Left --}}
            <div x-show="mobileFiltersOpen"
                x-transition:enter="transform transition ease-in-out duration-300"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in-out duration-300"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                class="relative z-50 h-full w-80 max-w-[85vw] bg-white shadow-2xl flex flex-col justify-between overflow-hidden"
                @click.stop>
                
                {{-- Header --}}
                <div class="flex justify-between items-center px-5 py-4 border-b border-gray-100 bg-gray-50/80">
                    <div class="flex items-center gap-2">
                        <h3 class="font-GraphikBold text-base uppercase tracking-wider text-darker-3">Filters</h3>
                        @php
                            $filterCount = count($filters->bhks ?? []) + count($filters->propertyTypes ?? []) + count($filters->furnishings ?? []) + count($filters->amenities ?? []) + count($filters->localities ?? []);
                        @endphp
                        @if ($filterCount > 0)
                            <span class="bg-piss-yellow text-darker-3 font-GraphikBold text-[10px] w-5 h-5 rounded-full flex items-center justify-center">
                                {{ $filterCount }}
                            </span>
                        @endif
                    </div>
                    <button type="button" @click="mobileFiltersOpen = false"
                        class="text-gray-400 hover:text-darker-3 p-1.5 rounded-lg hover:bg-gray-200 transition cursor-pointer">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>

                {{-- Scrollable Filters Content --}}
                <div class="p-5 overflow-y-auto flex-1 text-sm text-darker-3">
                    <form action="{{ route('allProperties') }}" method="GET" id="mobileFilterForm">
                        <input type="hidden" name="query" value="{{ request('query') }}">
                        <input type="hidden" name="sortBy" value="{{ request('sortBy', 'recomended') }}">

                        {{-- Bedroom (BHK) --}}
                        <div class="mb-4 pb-4 border-b border-secondary">
                            <h4 class="font-GraphikMedium mb-2 text-xs uppercase tracking-wide text-darker-2">Bedroom</h4>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($bhks as $bhk)
                                    <label class="flex items-center space-x-2 text-xs text-darker-3 cursor-pointer">
                                        <input class="text-piss-yellow rounded-sm border-gray-300 focus:ring-0" type="checkbox"
                                            name="bhks[]" value="{{ $bhk->id }}" id="mobile-bhks-{{ $bhk->id }}"
                                            @if (in_array($bhk->id, $filters->bhks)) checked @endif>
                                        <span>{{ $bhk->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Accommodation Type --}}
                        <div class="mb-4 pb-4 border-b border-secondary">
                            <h4 class="font-GraphikMedium mb-2 text-xs uppercase tracking-wide text-darker-2">Accommodation Type</h4>
                            <div class="grid grid-cols-1 gap-2">
                                @foreach ($propertyTypes as $type)
                                    <label class="flex items-center space-x-2 text-xs text-darker-3 cursor-pointer">
                                        <input class="text-piss-yellow rounded-sm border-gray-300 focus:ring-0" type="checkbox"
                                            name="propertyTypes[]" value="{{ $type->id }}" id="mobile-propertyTypes-{{ $type->id }}"
                                            @if (in_array($type->id, $filters->propertyTypes)) checked @endif>
                                        <span>{{ $type->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Furnishing --}}
                        <div class="mb-4 pb-4 border-b border-secondary">
                            <h4 class="font-GraphikMedium mb-2 text-xs uppercase tracking-wide text-darker-2">Furnishing</h4>
                            <div class="grid grid-cols-1 gap-2">
                                @foreach ($furnishings as $furnishing)
                                    <label class="flex items-center space-x-2 text-xs text-darker-3 cursor-pointer">
                                        <input class="text-piss-yellow rounded-sm border-gray-300 focus:ring-0" type="checkbox"
                                            name="furnishings[]" value="{{ $furnishing->id }}" id="mobile-furnishings-{{ $furnishing->id }}"
                                            @if (in_array($furnishing->id, $filters->furnishings)) checked @endif>
                                        <span>{{ $furnishing->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Amenities --}}
                        <div class="mb-4 pb-4 border-b border-secondary">
                            <h4 class="font-GraphikMedium mb-2 text-xs uppercase tracking-wide text-darker-2">Amenities</h4>
                            <div class="grid grid-cols-1 gap-2">
                                @foreach ($amenities as $am)
                                    <label class="flex items-center space-x-2 text-xs text-darker-3 cursor-pointer">
                                        <input class="text-piss-yellow rounded-sm border-gray-300 focus:ring-0" type="checkbox"
                                            name="amenities[]" value="{{ $am->id }}" id="mobile-amenities-{{ $am->id }}"
                                            @if (in_array($am->id, $filters->amenities)) checked @endif>
                                        <span>{{ $am->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Localities --}}
                        <div class="mb-4 pb-4 border-b border-secondary">
                            <h4 class="font-GraphikMedium mb-2 text-xs uppercase tracking-wide text-darker-2">Locality</h4>
                            <div class="grid grid-cols-1 gap-2 max-h-48 overflow-y-auto pr-1">
                                @foreach ($localities as $loc)
                                    <label class="flex items-center space-x-2 text-xs text-darker-3 cursor-pointer">
                                        <input class="text-piss-yellow rounded-sm border-gray-300 focus:ring-0" type="checkbox"
                                            name="localities[]" value="{{ $loc->id }}" id="mobile-localities-{{ $loc->id }}"
                                            @if (in_array($loc->id, $filters->localities)) checked @endif>
                                        <span>{{ $loc->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Drawer Footer Actions --}}
                <div class="p-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
                    @if (!empty($filters->bhks) || !empty($filters->amenities) || !empty($filters->propertyTypes) || !empty($filters->furnishings) || !empty($filters->localities))
                        <a href="{{ route('allProperties') }}" class="text-xs text-darker-2 hover:text-piss-yellow font-GraphikMedium transition underline">
                            Reset All
                        </a>
                    @else
                        <span></span>
                    @endif
                    <button type="button" onclick="document.getElementById('mobileFilterForm').submit()"
                        class="bg-piss-yellow text-darker-3 hover:bg-darker-3 hover:text-piss-yellow px-5 py-2.5 rounded-sm text-xs font-GraphikMedium transition cursor-pointer">
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>

        <div class="mx-auto mt-8 flex flex-col sm:flex-row Site-Max-Width px-4" id="filters-container">
        {{-- Filters Sidebar --}}
        <div class="hidden sm:block overflow-y-auto h-screen p-4 sticky top-20 sm:w-80 sm:mr-8 border-r border-gray-100" id="filters">
            <form action="{{ route('allProperties') }}" method="GET" class="text-darker-3 text-sm" id="allPropertiesForm">
                <input type="hidden" name="query" id="hidden-search" value="{{ request('query') }}">
                <input type="hidden" name="sortBy" id="hidden-sort-by" value="{{ request('sortBy', 'recomended') }}">

                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-GraphikBold text-base uppercase tracking-wider text-darker-3">Filters</h3>
                    @if (!empty($filters->bhks) || !empty($filters->amenities) || !empty($filters->propertyTypes) || !empty($filters->furnishings) || !empty($filters->localities))
                        <a href="{{ route('allProperties') }}" class="text-xs text-piss-yellow hover:text-darker-3 font-GraphikMedium">Reset All</a>
                    @endif
                </div>

                {{-- Bedroom (BHK) --}}
                <div class="mb-4 pb-4 border-b border-secondary">
                    <h4 class="font-GraphikMedium mb-2 text-xs uppercase tracking-wide text-darker-2">Bedroom</h4>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($bhks as $bhk)
                            <label class="flex items-center space-x-2 text-xs text-darker-3 cursor-pointer">
                                <input class="text-piss-yellow rounded-sm border-gray-300 focus:ring-0" type="checkbox"
                                    name="bhks[]" value="{{ $bhk->id }}" id="bhks-{{ $bhk->id }}"
                                    @if (in_array($bhk->id, $filters->bhks)) checked @endif>
                                <span>{{ $bhk->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Accommodation Type --}}
                <div class="mb-4 pb-4 border-b border-secondary">
                    <h4 class="font-GraphikMedium mb-2 text-xs uppercase tracking-wide text-darker-2">Accommodation Type</h4>
                    <div class="grid grid-cols-1 gap-2">
                        @foreach ($propertyTypes as $type)
                            <label class="flex items-center space-x-2 text-xs text-darker-3 cursor-pointer">
                                <input class="text-piss-yellow rounded-sm border-gray-300 focus:ring-0" type="checkbox"
                                    name="propertyTypes[]" value="{{ $type->id }}" id="propertyTypes-{{ $type->id }}"
                                    @if (in_array($type->id, $filters->propertyTypes)) checked @endif>
                                <span>{{ $type->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Furnishing --}}
                <div class="mb-4 pb-4 border-b border-secondary">
                    <h4 class="font-GraphikMedium mb-2 text-xs uppercase tracking-wide text-darker-2">Furnishing</h4>
                    <div class="grid grid-cols-1 gap-2">
                        @foreach ($furnishings as $furnishing)
                            <label class="flex items-center space-x-2 text-xs text-darker-3 cursor-pointer">
                                <input class="text-piss-yellow rounded-sm border-gray-300 focus:ring-0" type="checkbox"
                                    name="furnishings[]" value="{{ $furnishing->id }}" id="furnishings-{{ $furnishing->id }}"
                                    @if (in_array($furnishing->id, $filters->furnishings)) checked @endif>
                                <span>{{ $furnishing->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Amenities --}}
                <div class="mb-4 pb-4 border-b border-secondary">
                    <h4 class="font-GraphikMedium mb-2 text-xs uppercase tracking-wide text-darker-2">Amenities</h4>
                    <div class="grid grid-cols-1 gap-2">
                        @foreach ($amenities as $am)
                            <label class="flex items-center space-x-2 text-xs text-darker-3 cursor-pointer">
                                <input class="text-piss-yellow rounded-sm border-gray-300 focus:ring-0" type="checkbox"
                                    name="amenities[]" value="{{ $am->id }}" id="amenities-{{ $am->id }}"
                                    @if (in_array($am->id, $filters->amenities)) checked @endif>
                                <span>{{ $am->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Localities --}}
                <div class="mb-4 pb-4 border-b border-secondary">
                    <h4 class="font-GraphikMedium mb-2 text-xs uppercase tracking-wide text-darker-2">Locality</h4>
                    <div class="grid grid-cols-1 gap-2 max-h-48 overflow-y-auto pr-1">
                        @foreach ($localities as $loc)
                            <label class="flex items-center space-x-2 text-xs text-darker-3 cursor-pointer">
                                <input class="text-piss-yellow rounded-sm border-gray-300 focus:ring-0" type="checkbox"
                                    name="localities[]" value="{{ $loc->id }}" id="localities-{{ $loc->id }}"
                                    @if (in_array($loc->id, $filters->localities)) checked @endif>
                                <span>{{ $loc->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </form>
        </div>

        {{-- Property Listing Results --}}
        <div class="w-full">
            {{-- Top Bar: Results Count & Sort --}}
            <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                <div class="text-sm text-darker-2">
                    Showing <span class="font-GraphikMedium text-darker-3">{{ $properties->total() }}</span> properties
                    @if (request('query'))
                        for "<span class="font-GraphikMedium text-darker-3">{{ request('query') }}</span>"
                    @endif
                </div>

                <div class="hidden sm:flex items-center space-x-2 text-sm">
                    <span class="text-darker-2 font-GraphikMedium text-xs">Sort by:</span>
                    <div class="relative" x-data="{ desktopSortOpen: false }">
                        <button id="desktop_sort_button" type="button" @click="desktopSortOpen = !desktopSortOpen"
                            class="flex items-center justify-between min-w-[130px] bg-secondary hover:bg-darker/40 rounded-xl px-3 py-1.5 cursor-pointer text-xs font-GraphikMedium text-darker-3 transition duration-150 border border-secondary shadow-xs focus:outline-none">
                            <span id="desktop_current_sort_name">
                                @php
                                    $sortMap = [
                                        'recomended' => 'Featured',
                                        'price_asc' => 'Price: Low to High',
                                        'price_desc' => 'Price: High to Low',
                                    ];
                                    $curSort = request('sortBy', 'recomended');
                                    echo $sortMap[$curSort] ?? 'Featured';
                                @endphp
                            </span>
                            <svg class="ml-2 w-3.5 h-3.5 text-darker-3 transition-transform duration-200" :class="{ 'rotate-180': desktopSortOpen }" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>

                        <div x-show="desktopSortOpen" @click.outside="desktopSortOpen = false" x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 mt-1.5 z-30 w-44 bg-white rounded-xl shadow-lg border border-secondary divide-y divide-gray-100 py-1 text-xs overflow-hidden">
                            <button type="button" @click="desktopSortOpen = false; updateSortBy({ value: 'recomended' })"
                                class="flex items-center justify-between w-full text-left px-3.5 py-2.5 text-darker-3 hover:bg-gray-50 hover:text-piss-yellow font-Graphik transition cursor-pointer {{ request('sortBy', 'recomended') == 'recomended' ? 'bg-secondary/40 font-GraphikMedium text-piss-yellow' : '' }}">
                                <span>Featured</span>
                                @if(request('sortBy', 'recomended') == 'recomended')
                                    <svg class="w-3.5 h-3.5 text-piss-yellow shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                @endif
                            </button>
                            <button type="button" @click="desktopSortOpen = false; updateSortBy({ value: 'price_asc' })"
                                class="flex items-center justify-between w-full text-left px-3.5 py-2.5 text-darker-3 hover:bg-gray-50 hover:text-piss-yellow font-Graphik transition cursor-pointer {{ request('sortBy', 'price_asc') == 'price_asc' ? 'bg-secondary/40 font-GraphikMedium text-piss-yellow' : '' }}">
                                <span>Price: Low to High</span>
                                @if(request('sortBy', 'price_asc') == 'price_asc')
                                    <svg class="w-3.5 h-3.5 text-piss-yellow shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                @endif
                            </button>
                            <button type="button" @click="desktopSortOpen = false; updateSortBy({ value: 'price_desc' })"
                                class="flex items-center justify-between w-full text-left px-3.5 py-2.5 text-darker-3 hover:bg-gray-50 hover:text-piss-yellow font-Graphik transition cursor-pointer {{ request('sortBy', 'price_desc') == 'price_desc' ? 'bg-secondary/40 font-GraphikMedium text-piss-yellow' : '' }}">
                                <span>Price: High to Low</span>
                                @if(request('sortBy', 'price_desc') == 'price_desc')
                                    <svg class="w-3.5 h-3.5 text-piss-yellow shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                @endif
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Properties List --}}
            <div class="mt-6 space-y-6">
                @forelse ($properties as $property)
                    @php
                        $cPhoto = $property->coverPhoto ?: $property->photos->first();
                        $photoUrl = $cPhoto ? Storage::disk('public')->url($cPhoto->file_path) : url('resources/images/sample_prop.jpeg');
                    @endphp
                    <div class="bg-white rounded-2xl border border-secondary/80 shadow-xs hover:shadow-lg transition-all duration-300 overflow-hidden flex flex-col md:flex-row md:h-[13.5rem] group">
                        {{-- Photo & Overlay Badges --}}
                        <a href="{{ route('viewProperty', $property) }}" class="block relative md:w-72 lg:w-80 shrink-0 aspect-16/10 md:aspect-auto md:h-full overflow-hidden bg-gray-100">
                            <img src="{{ $photoUrl }}" alt="{{ $property->building_name }}"
                                class="w-full h-full object-cover group-hover:scale-105 transition duration-500 ease-out">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-black/20 md:hidden pointer-events-none"></div>

                            {{-- Top BHK Pill Badge --}}
                            @if ($property->bhkType)
                                <div class="absolute top-3 left-3">
                                    <span class="bg-darker-3/90 backdrop-blur-xs text-white text-xs font-GraphikMedium px-2.5 py-1 rounded-md shadow-xs">
                                        {{ $property->bhkType->name }}
                                    </span>
                                </div>
                            @endif

                            {{-- Mobile Availability Pill Badge Overlay --}}
                            <div class="absolute bottom-3 left-3 sm:hidden">
                                <span class="bg-white/95 backdrop-blur-xs {{ $property->isAvailable() ? 'text-green-700' : 'text-darker-2' }} text-[11px] font-GraphikMedium px-2.5 py-1 rounded-full shadow-xs flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $property->isAvailable() ? 'bg-green-600' : 'bg-gray-400' }}"></span>
                                    {{ $property->isAvailable() ? 'Available Now' : 'Available Soon' }}
                                </span>
                            </div>
                        </a>

                        {{-- Details --}}
                        <div class="p-4 md:py-3.5 md:px-5 flex-1 flex flex-col justify-between overflow-hidden">
                            <div>
                                {{-- Header Row: Locality, Title, and Desktop Rent --}}
                                <div class="flex justify-between items-start gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 text-xs uppercase tracking-wider text-darker-2 font-GraphikMedium mb-0.5">
                                            <svg class="w-3.5 h-3.5 text-piss-yellow shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span class="truncate">{{ $property->localityRef?->name ?? ($property->locality ?: 'Guwahati') }}</span>
                                        </div>
                                        <a href="{{ route('viewProperty', $property) }}" class="block">
                                            <h3 class="font-GraphikBold text-base sm:text-lg text-darker-3 hover:text-piss-yellow transition leading-snug line-clamp-1">
                                                {{ $property->building_name ?: 'Residential Property' }}
                                            </h3>
                                        </a>
                                        <p class="text-xs text-darker-2 mt-0.5 line-clamp-1" title="{{ $property->address ?: ($property->landmark ?: 'Guwahati, Assam') }}">
                                            {{ $property->landmark ? $property->landmark . ', ' : '' }}{{ $property->address ?: 'Guwahati, Assam' }}
                                        </p>
                                    </div>

                                    {{-- Desktop Rent Display (Hidden on Mobile, shown in header on Desktop) --}}
                                    <div class="hidden sm:block text-right shrink-0">
                                        <span class="text-[11px] text-darker-2 block font-GraphikMedium leading-tight">Monthly Rent</span>
                                        <span class="font-GraphikBold text-xl text-darker-3 leading-tight">₹{{ number_format($property->rent) }}</span>
                                    </div>
                                </div>

                                {{-- Spec Highlights Grid (Soft rounded card chips) --}}
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-2.5 pt-2 border-t border-gray-100">
                                    <div class="bg-gray-50/90 rounded-lg py-1 px-2 sm:py-1.5 sm:px-2.5 border border-gray-100">
                                        <span class="text-[10px] uppercase tracking-wider text-darker-2 block font-GraphikMedium leading-none">Type</span>
                                        <span class="font-GraphikMedium text-xs text-darker-3 truncate block mt-0.5">{{ $property->propertyType?->name ?? 'Apartment' }}</span>
                                    </div>
                                    <div class="bg-gray-50/90 rounded-lg py-1 px-2 sm:py-1.5 sm:px-2.5 border border-gray-100">
                                        <span class="text-[10px] uppercase tracking-wider text-darker-2 block font-GraphikMedium leading-none">Furnishing</span>
                                        <span class="font-GraphikMedium text-xs text-darker-3 truncate block mt-0.5">{{ $property->furnishingType?->name ?? 'Standard' }}</span>
                                    </div>
                                    <div class="bg-gray-50/90 rounded-lg py-1 px-2 sm:py-1.5 sm:px-2.5 border border-gray-100">
                                        <span class="text-[10px] uppercase tracking-wider text-darker-2 block font-GraphikMedium leading-none">Floor Space</span>
                                        <span class="font-GraphikMedium text-xs text-darker-3 truncate block mt-0.5">{{ $property->floor_space_sqft ? $property->floor_space_sqft . ' sqft' : '-' }}</span>
                                    </div>
                                    <div class="bg-gray-50/90 rounded-lg py-1 px-2 sm:py-1.5 sm:px-2.5 border border-gray-100">
                                        <span class="text-[10px] uppercase tracking-wider text-darker-2 block font-GraphikMedium leading-none">Deposit</span>
                                        <span class="font-GraphikMedium text-xs text-darker-3 truncate block mt-0.5">₹{{ number_format($property->security_deposit) }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Bottom Actions & Mobile Rent --}}
                            <div class="mt-2.5 pt-2 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                                {{-- Left: Mobile Rent; Desktop: Availability dot --}}
                                <div class="flex items-center justify-between sm:justify-start gap-2">
                                    <div class="sm:hidden flex items-baseline gap-1.5">
                                        <span class="text-xs text-darker-2 font-Graphik">Rent:</span>
                                        <span class="font-GraphikBold text-xl text-darker-3">₹{{ number_format($property->rent) }}</span>
                                        <span class="text-[11px] text-darker-2">/mo</span>
                                    </div>
                                    <span class="hidden sm:flex items-center gap-1.5 text-xs font-GraphikMedium {{ $property->isAvailable() ? 'text-green-700' : 'text-darker-2' }}">
                                        <span class="w-2 h-2 rounded-full {{ $property->isAvailable() ? 'bg-green-600' : 'bg-gray-400' }}"></span>
                                        {{ $property->isAvailable() ? 'Available Now' : 'Available Soon' }}
                                    </span>
                                </div>

                                {{-- Action Buttons --}}
                                <div class="flex items-center gap-2 sm:gap-2.5 w-full sm:w-auto">
                                    <button type="button"
                                        onclick="window.dispatchEvent(new CustomEvent('open-book-modal', { detail: { property_id: '{{ $property->id }}' } }))"
                                        class="flex-1 sm:flex-none justify-center bg-darker-3 text-piss-yellow hover:bg-piss-yellow hover:text-darker-3 px-3.5 py-1.5 rounded-lg text-xs font-GraphikMedium transition shadow-xs cursor-pointer text-center">
                                        Book Visit
                                    </button>
                                    <a href="{{ route('viewProperty', $property) }}"
                                        class="flex-1 sm:flex-none justify-center border border-gray-300 text-darker-3 hover:border-darker-3 hover:bg-gray-50 px-3.5 py-1.5 rounded-lg text-xs font-GraphikMedium transition text-center">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-16 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                        <img src="{{ url('resources/images/no_match_found.png') }}" alt="No Match Found" class="w-48 mx-auto mb-4 opacity-75">
                        <h3 class="font-GraphikMedium text-xl text-darker-3">No Properties Found</h3>
                        <p class="text-sm text-darker-2 mt-1">Please try clearing some filters or searching for another locality.</p>
                        <div class="mt-6">
                            <a href="{{ route('allProperties') }}"
                                class="bg-piss-yellow text-darker-3 px-6 py-2.5 rounded-sm text-sm font-GraphikMedium hover:bg-darker-3 hover:text-piss-yellow transition inline-block">
                                Reset All Filters
                            </a>
                        </div>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            <div class="flex justify-center mt-12 mb-16">
                {{ $properties->links('vendor.pagination.styled-tailwind') }}
            </div>
        </div>
    </div>
    </div>

    @section('before-body-end')
        <script>
            function updateSortBy(element) {
                const hiddenSort = document.getElementById('hidden-sort-by');
                if (hiddenSort) hiddenSort.value = element.value;
                document.getElementById('allPropertiesForm').submit();
            }

            function updateBHK(element) {
                const form = document.getElementById('allPropertiesForm');
                if (form) form.submit();
            }

            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('allPropertiesForm');
                if (form) {
                    form.addEventListener('change', function(e) {
                        if (e.target.type === 'checkbox') {
                            form.submit();
                        }
                    });
                }
            });
        </script>
    @endsection
</x-public-layout>
