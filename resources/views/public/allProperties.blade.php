<x-public-layout :filters="$filters" :total="$properties->total()">
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

        <div class="mx-auto mt-4 sm:mt-6 flex flex-col sm:flex-row Site-Max-Width px-4" id="filters-container">
        {{-- Filters Sidebar --}}
        <div class="hidden sm:block overflow-y-auto h-screen p-4 sticky top-28 sm:top-32 sm:w-80 sm:mr-8 border-r border-gray-100" id="filters">
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
            {{-- Properties List & Infinite Scroll --}}
            <div x-data="infiniteScroll({
                    nextUrl: '{{ $properties->nextPageUrl() }}',
                    hasMore: {{ $properties->hasMorePages() ? 'true' : 'false' }},
                    total: {{ $properties->total() }}
                })"
                x-init="init()">
                
                {{-- Cards Container --}}
                <div id="properties-container" class="space-y-6">
                    @include('public.partials.property-card-list', ['properties' => $properties])
                </div>

                {{-- Loading Skeleton Card (Smooth shimmer & transition while fetching next page) --}}
                <div x-show="loading"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-3"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-2"
                    x-cloak class="mt-6 space-y-6">
                    <div class="bg-white rounded-2xl border border-secondary/80 p-0 overflow-hidden flex flex-col md:flex-row md:h-[13.5rem] shadow-xs">
                        <div class="shimmer-placeholder md:w-72 lg:w-80 shrink-0 aspect-16/10 md:aspect-auto md:h-full"></div>
                        <div class="p-4 md:py-3.5 md:px-5 flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start gap-3">
                                    <div class="flex-1 space-y-2">
                                        <div class="h-3 shimmer-placeholder rounded w-24"></div>
                                        <div class="h-5 shimmer-placeholder rounded w-3/4"></div>
                                        <div class="h-3 shimmer-placeholder rounded w-1/2"></div>
                                    </div>
                                    <div class="hidden sm:block space-y-1">
                                        <div class="h-3 shimmer-placeholder rounded w-16 ml-auto"></div>
                                        <div class="h-6 shimmer-placeholder rounded w-24"></div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-4 pt-2 border-t border-gray-100">
                                    <div class="h-10 shimmer-placeholder rounded-lg"></div>
                                    <div class="h-10 shimmer-placeholder rounded-lg"></div>
                                    <div class="h-10 shimmer-placeholder rounded-lg"></div>
                                    <div class="h-10 shimmer-placeholder rounded-lg"></div>
                                </div>
                            </div>
                            <div class="mt-4 pt-2 border-t border-gray-100 flex items-center justify-between">
                                <div class="h-4 shimmer-placeholder rounded w-24"></div>
                                <div class="flex gap-2">
                                    <div class="h-8 shimmer-placeholder rounded-lg w-24"></div>
                                    <div class="h-8 shimmer-placeholder rounded-lg w-24"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Sleek Floating Loading Badge --}}
                    <div class="flex justify-center py-2">
                        <div class="inline-flex items-center gap-2.5 px-4 py-1.5 bg-white/90 backdrop-blur-xs rounded-full border border-gray-200 shadow-xs">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-piss-yellow opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-piss-yellow"></span>
                            </span>
                            <span class="text-xs font-GraphikMedium text-darker-3">Loading more properties...</span>
                        </div>
                    </div>
                </div>

                {{-- Sentinel for Intersection Observer --}}
                <div x-ref="sentinel" class="h-4 w-full my-4 pointer-events-none"></div>

                {{-- End of Results Indicator --}}
                <div x-show="!hasMore && total > 0"
                    x-transition:enter="transition ease-out duration-400 delay-150"
                    x-transition:enter-start="opacity-0 translate-y-3"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="text-center py-10">
                    <p class="text-xs font-GraphikMedium text-darker-2 inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-50 border border-gray-200/60 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-piss-yellow"></span>
                        You've viewed all <span class="font-GraphikBold text-darker-3" x-text="total"></span> properties
                    </p>
                </div>

                {{-- Error / Retry State --}}
                <div x-show="error" x-cloak class="text-center py-6">
                    <p class="text-xs text-red-500 font-GraphikMedium mb-2">Failed to load more properties.</p>
                    <button type="button" @click="loadMore()" class="text-xs font-GraphikMedium bg-gray-100 hover:bg-gray-200 text-darker-3 px-4 py-2 rounded-lg transition cursor-pointer">
                        Try Again
                    </button>
                </div>

                {{-- Fallback pagination for crawlers / no-JS --}}
                <noscript>
                    <div class="flex justify-center mt-12 mb-16">
                        {{ $properties->links('vendor.pagination.styled-tailwind') }}
                    </div>
                </noscript>
            </div>
        </div>
    </div>
    </div>

    @section('before-body-end')
        <script>
            function infiniteScroll(config) {
                return {
                    nextUrl: config.nextUrl,
                    hasMore: config.hasMore,
                    total: config.total,
                    loading: false,
                    error: false,
                    observer: null,

                    init() {
                        if (!this.hasMore || !this.nextUrl) return;

                        this.observer = new IntersectionObserver((entries) => {
                            const entry = entries[0];
                            if (entry && entry.isIntersecting && this.hasMore && !this.loading) {
                                this.loadMore();
                            }
                        }, {
                            rootMargin: '250px 0px',
                            threshold: 0.1
                        });

                        this.$nextTick(() => {
                            if (this.$refs.sentinel) {
                                this.observer.observe(this.$refs.sentinel);
                            }
                        });
                    },

                    async loadMore() {
                        if (!this.hasMore || this.loading || !this.nextUrl) return;

                        this.loading = true;
                        this.error = false;

                        try {
                            const response = await fetch(this.nextUrl, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-Infinite-Scroll': '1',
                                    'Accept': 'application/json',
                                }
                            });

                            if (!response.ok) {
                                throw new Error('HTTP error ' + response.status);
                            }

                            const data = await response.json();

                            if (data.html) {
                                const container = document.getElementById('properties-container');
                                if (container) {
                                    container.insertAdjacentHTML('beforeend', data.html);
                                }
                            }

                            this.hasMore = Boolean(data.hasMore);
                            this.nextUrl = data.nextPageUrl;

                            if (!this.hasMore && this.observer && this.$refs.sentinel) {
                                this.observer.unobserve(this.$refs.sentinel);
                            }
                        } catch (err) {
                            console.error('Error fetching more properties:', err);
                            this.error = true;
                        } finally {
                            this.loading = false;
                        }
                    }
                };
            }
            window.infiniteScroll = infiniteScroll;

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
