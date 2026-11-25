@props(['filters' => null, 'total' => null])
<div x-data="{ drawerOpen: false, bhkOpen: false, scrolled: false }"
     @scroll.window="scrolled = (window.pageYOffset > 10)"
     x-init="scrolled = (window.pageYOffset > 10)">

    <!-- Sticky Nav Header with Scroll-Reactive Shadow (Idea B) -->
    <header class="fixed inset-x-0 top-0 w-full z-30 bg-white/95 backdrop-blur-md pt-2 sm:pt-2.5 transition-all duration-300 border-b"
        :class="scrolled ? 'border-gray-200/80 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.06)]' : 'border-gray-100/90 shadow-none'">
    <div class="flex justify-between mx-auto w-full Site-Max-Width px-3 sm:px-4 py-2 sm:pb-2.5 items-center">
        <!-- Logo -->
        <div class="shrink-0 flex items-center">
            <a href="{{ route('home') }}">
                <x-site-logo class="w-32 hidden sm:block" />
                <x-site-logo-small class="sm:hidden w-8" />
            </a>
        </div>

        {{-- Search Bar --}}
        <div class="flex-1 min-w-0 mx-2 sm:mx-4 max-w-3xl">
            <form action="{{ route('allProperties') }}" method="GET" id="search-form" class="w-full">
                <input type="hidden" name="bhks[]" value="{{ is_array(request('bhks')) ? request('bhks')[0] : (request('bhk_id') ?: '') }}" id="bhk_id">
                <div class="flex w-full items-stretch h-10 sm:h-11 md:h-12">
                    <div class="relative shrink-0 flex">
                        <button id="bhk_button" type="button" @click="bhkOpen = !bhkOpen"
                            class="flex items-center min-w-max bg-secondary rounded-bl-2xl pl-2.5 pr-2 sm:pl-4 sm:pr-3 lg:gap-4 cursor-pointer h-full">
                            <span id="bhk_name" class="text-xs sm:text-sm md:text-base font-GraphikMedium">
                                @php
                                    $selectedBhk = '';
                                    if (!empty($filters?->bhks) && is_array($filters->bhks) && count($filters->bhks) === 1) {
                                        $bhkModel = \Illuminate\Support\Facades\DB::table('bhk_types')->where('id', $filters->bhks[0])->first();
                                        $selectedBhk = $bhkModel?->name ?? 'BHK';
                                    } elseif (request('bhk_id')) {
                                        $bhkModel = \Illuminate\Support\Facades\DB::table('bhk_types')->where('id', request('bhk_id'))->first();
                                        $selectedBhk = $bhkModel?->name ?? 'BHK';
                                    } else {
                                        $selectedBhk = 'BHK';
                                    }
                                    echo $selectedBhk;
                                @endphp
                            </span>
                            <svg aria-hidden="true" class="ml-1 w-3.5 h-3.5 sm:w-4 sm:h-4 text-darker-3" fill="currentColor" viewBox="0 0 20 20"
                                xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd"></path>
                            </svg>
                        </button>

                        <div x-show="bhkOpen" @click.outside="bhkOpen = false" x-cloak
                            class="absolute left-0 top-full mt-1.5 z-30 w-44 bg-white rounded divide-y divide-gray-100 shadow">
                            <ul class="py-1 text-sm text-gray-700">
                                @php
                                    $allBhks = \Illuminate\Support\Facades\DB::table('bhk_types')->orderBy('name')->get();
                                @endphp
                                <li>
                                    <button type="button" onclick="set_bhk('', 'All BHK')"
                                        class="py-2 px-4 w-full text-sm text-gray-700 text-left hover:bg-gray-100 cursor-pointer">
                                        All BHK
                                    </button>
                                </li>
                                @foreach ($allBhks as $bhkItem)
                                    <li>
                                        <button type="button" onclick="set_bhk('{{ $bhkItem->id }}', '{{ $bhkItem->name }}')"
                                            class="py-2 px-4 w-full text-sm text-gray-700 text-left hover:bg-gray-100 cursor-pointer">
                                            {{ $bhkItem->name }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <input type="text" name="query" id="search" value="{{ request('query') }}"
                        class="search-input-field flex-1 min-w-0 border-l-0 text-xs sm:text-base rounded-tr-2xl rounded-br-2xl sm:rounded-br-none sm:border-r-0 border-t border-b border-t-secondary border-b-secondary focus:border-t-secondary focus:border-b-piss-yellow focus:border-b-2 focus:ring-0 focus:outline-none outline-none px-2.5 sm:px-3 bg-white text-darker-3 h-full"
                        placeholder="Location, Amenities...">
                    <button type="submit"
                        class="bg-piss-yellow rounded-tr-3xl rounded-bl-2xl -ml-5 px-8 text-sm sm:text-base hover:bg-darker ease-in-out duration-300 hidden sm:flex items-center justify-center cursor-pointer font-GraphikMedium shrink-0 h-full">Search</button>
                </div>
            </form>
        </div>

        <!-- Hamburger -->
        <div class="items-center my-auto shrink-0">
            <button type="button" @click="drawerOpen = !drawerOpen"
                class="inline-flex items-center justify-center p-1.5 sm:p-2 rounded-md text-darker-3 hover:text-piss-yellow transition duration-150 ease-in-out cursor-pointer">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    @if (Route::is('allProperties'))
        {{-- Sub-bar for Count, Sort & Mobile Filters (Sticky on both Mobile & Desktop) --}}
        <div class="border-t border-gray-100/90 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/40">
            <div class="flex justify-between items-center Site-Max-Width mx-auto">
                {{-- Showing properties count --}}
                <div class="text-xs sm:text-sm text-darker-2 flex items-center gap-1.5">
                    Showing <span class="font-GraphikMedium text-darker-3">{{ $total ?? 0 }}</span> properties
                    @if (request('query'))
                        <span class="hidden sm:inline">for "<span class="font-GraphikMedium text-darker-3">{{ request('query') }}</span>"</span>
                    @endif
                </div>

                {{-- Sort by & Mobile Filter Button --}}
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="flex items-center">
                        <span class="text-xs text-darker-2 mr-1.5 sm:mr-2 font-GraphikMedium shrink-0">Sort by:</span>
                        <div class="relative shrink-0" x-data="{ sortOpen: false }">
                            <button id="sort_button" type="button" @click="sortOpen = !sortOpen"
                                class="flex items-center justify-between min-w-[120px] sm:min-w-[135px] bg-secondary hover:bg-darker/40 rounded-xl px-2.5 sm:px-3 py-1 sm:py-1.5 cursor-pointer text-xs font-GraphikMedium text-darker-3 transition duration-150 border border-secondary shadow-xs focus:outline-none">
                                <span id="current_sort_name">
                                    @php
                                        $sortMap = [
                                            'recomended' => 'Featured',
                                            'price_asc' => 'Price: Low to High',
                                            'price_desc' => 'Price: High to Low',
                                            'code_asc' => 'Property Code',
                                        ];
                                        $curSort = request('sortBy', 'recomended');
                                        if ($curSort === 'property_code') {
                                            $curSort = 'code_asc';
                                        }
                                        echo $sortMap[$curSort] ?? 'Featured';
                                    @endphp
                                </span>
                                <svg class="ml-1.5 sm:ml-2 w-3.5 h-3.5 text-darker-3 transition-transform duration-200" :class="{ 'rotate-180': sortOpen }" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>

                            <input type="hidden" name="sort_by" id="mobile_sort_by" value="{{ $curSort }}">

                            <div x-show="sortOpen" @click.outside="sortOpen = false" x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute right-0 mt-1.5 z-30 w-44 bg-white rounded-xl shadow-lg border border-secondary divide-y divide-gray-100 py-1 text-xs overflow-hidden">
                                @foreach ($sortMap as $sortValue => $sortLabel)
                                    <button type="button" @click="sortOpen = false; selectSort('{{ $sortValue }}', '{{ $sortLabel }}')"
                                        class="flex items-center justify-between w-full text-left px-3.5 py-2.5 text-darker-3 hover:bg-gray-50 hover:text-piss-yellow font-Graphik transition cursor-pointer {{ $curSort === $sortValue ? 'bg-secondary/40 font-GraphikMedium text-piss-yellow' : '' }}">
                                        <span>{{ $sortLabel }}</span>
                                        @if ($curSort === $sortValue)
                                            <svg class="w-3.5 h-3.5 text-piss-yellow shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <button class="sm:hidden flex items-center justify-center p-1.5 rounded-xl bg-secondary/80 hover:bg-secondary text-darker-3 transition cursor-pointer shrink-0 border border-secondary shadow-xs"
                        id="mobile-filter-button" type="button" title="Filters"
                        @click.stop="window.dispatchEvent(new CustomEvent('open-filters'))">
                        <img src="{{ url('resources/icons/filter.svg') }}" alt="filters" class="w-4 h-4">
                    </button>
                </div>
            </div>
        </div>
    @endif
    </header>

    <!-- Drawer Backdrop & Panel (Teleported to body so it displays over entire screen) -->
    <template x-teleport="body">
        <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-50 flex justify-end overflow-hidden" @keydown.escape.window="drawerOpen = false">
            <!-- Backdrop -->
            <div x-show="drawerOpen"
                x-transition:enter="transition-opacity ease-linear duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-linear duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-black/50"
                @click="drawerOpen = false"></div>

            <!-- Slide-out Drawer Panel -->
            <div x-show="drawerOpen"
                x-transition:enter="transform transition ease-in-out duration-300"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in-out duration-300"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="relative z-50 h-screen py-4 overflow-y-auto bg-darker-2 w-80 sm:w-96 shadow-xl flex flex-col">
                <div class="flex justify-between items-center px-6 pt-2 pb-4 border-b border-darker-3">
                    <h5 class="text-base font-semibold text-secondary uppercase">Menu</h5>
                    <button type="button" @click="drawerOpen = false"
                        class="text-gray-400 bg-transparent hover:text-piss-yellow hover:bg-darker-3 rounded-lg text-sm p-1.5 inline-flex items-center cursor-pointer transition">
                        <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
                <div class="py-4 overflow-y-auto flex-1">
                    <x-responsive-nav-link-list />
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    function set_bhk(id, name) {
        const bhkInput = document.getElementById('bhk_id');
        if (bhkInput) {
            bhkInput.value = id;
        }
        const bhkName = document.getElementById('bhk_name');
        if (bhkName) {
            bhkName.innerHTML = name;
        }
        if (typeof updateBHK === 'function') {
            updateBHK(bhkInput);
        } else {
            const form = document.getElementById('search-form');
            if (form) form.submit();
        }
    }

    function selectSort(value, name) {
        const sortName = document.getElementById('current_sort_name');
        if (sortName) {
            sortName.innerText = name;
        }
        const mobileSortInput = document.getElementById('mobile_sort_by');
        if (mobileSortInput) {
            mobileSortInput.value = value;
        }
        const hiddenSort = document.getElementById('hidden-sort-by');
        if (hiddenSort) {
            hiddenSort.value = value;
        }
        if (typeof updateSortBy === 'function') {
            updateSortBy({ value: value });
        } else {
            const form = document.getElementById('allPropertiesForm') || document.getElementById('search-form');
            if (form) form.submit();
        }
    }
</script>
