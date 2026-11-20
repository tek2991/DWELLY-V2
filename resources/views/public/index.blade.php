<x-public-layout>
    <header
        class="text-piss-yellow font-Graphik max-w-4xl mx-auto pt-6 pb-2 px-4 sm:px-0 text-2xl sm:text-3xl sm:py-8">
        <h1 class="text-piss-yellow">Want a place to Stay?</h1>
    </header>
    <form action="{{ route('allProperties') }}" method="GET" class="max-w-4xl mx-auto px-4 lg:px-0" x-data="{ bhkOpen: false }">
        <script>
            function set_bhk(id, name) {
                document.getElementById('bhk_id').value = id;
                document.getElementById('bhk_id').removeAttribute('disabled');
                document.getElementById('bhk_name').innerHTML = name;
            }
        </script>
        <input type="hidden" name="bhks[]" value="" id="bhk_id" disabled>
        <div class="flex justify-center items-stretch h-11 md:h-14">
            <div class="relative flex shrink-0">
                <button id="bhk_button" @click="bhkOpen = !bhkOpen"
                    class="flex items-center w-full bg-secondary rounded-bl-3xl pl-4 pr-2 md:rounded-bl-3xl md:pl-3 md:pr-2 lg:pl-8 lg:gap-6 cursor-pointer border-t border-b border-l border-secondary h-full"
                    type="button">
                    <span id="bhk_name" class="text-sm md:text-base font-Graphik">
                        BHK
                    </span>
                    <svg aria-hidden="true" class="ml-1 md:mt-1 w-4 h-4" fill="currentColor" viewBox="0 0 20 20"
                        xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
                <div id="bhk_dropdown" x-show="bhkOpen" @click.outside="bhkOpen = false" x-cloak
                    class="absolute top-full left-0 z-30 w-44 bg-white rounded divide-y divide-gray-100 shadow">
                    <ul class="py-1 text-sm text-gray-700" aria-labelledby="bhk_button">
                        <li>
                            <button type="button" @click="bhkOpen = false; set_bhk(1, '1 BHK')"
                                class="py-2 px-4 w-full text-sm text-gray-700 text-center hover:bg-gray-100 cursor-pointer">
                                1 BHK
                            </button>
                        </li>
                        <li>
                            <button type="button" @click="bhkOpen = false; set_bhk(2, '2 BHK')"
                                class="py-2 px-4 w-full text-sm text-gray-700 text-center hover:bg-gray-100 cursor-pointer">
                                2 BHK
                            </button>
                        </li>
                        <li>
                            <button type="button" @click="bhkOpen = false; set_bhk(3, '3 BHK')"
                                class="py-2 px-4 w-full text-sm text-gray-700 text-center hover:bg-gray-100 cursor-pointer">
                                3 BHK
                            </button>
                        </li>
                        <li>
                            <button type="button" @click="bhkOpen = false; set_bhk(4, '4 BHK')"
                                class="py-2 px-4 w-full text-sm text-gray-700 text-center hover:bg-gray-100 cursor-pointer">
                                4 BHK
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
            <input type="text" name="query" id=""
                class="search-input-field w-1/2 md:w-3/4 border-l-0 border-r-0 text-sm sm:text-base border-t border-b border-t-secondary border-b-secondary focus:border-t-secondary focus:border-b-piss-yellow focus:border-b-2 focus:ring-0 focus:outline-none outline-none px-4 text-darker-3 bg-white h-full"
                placeholder="Location, Amenities...">
            <button type="submit"
                class="bg-piss-yellow rounded-tr-3xl rounded-bl-3xl -ml-5 px-8 text-sm sm:text-base hover:bg-darker ease-in-out duration-300 cursor-pointer font-Graphik border border-piss-yellow h-full flex items-center justify-center shrink-0">Search</button>
        </div>
    </form>
    <div id="hero">
        <img src="{{ url('resources/images/city_bg.svg') }}" alt="City Background" class="w-full mt-12 mb-6 sm:mb-12">
    </div>

    <!-- Show on Desktop -->
    <div class="hidden sm:flex bg-piss-yellow H-30pvw MaxH-18 sm:justify-between sm:mt-20">
        <div class="w-1/4 3xl:w-2/6 z-10 p-2">
            <div
                class="Left-Space H-40pvw MaxH-23 W-30pvw MaxW-23 bg-white border-piss-yellow border-4 Rounded-BOX-2 NegM-T flex items-center">
                <div>
                    <div class="flex justify-between px-4 mb-6">
                        <div class="flex items-center">
                            <h2 class="text-piss-yellow font-GraphikMedium text-xl md:text-2xl lg:text-4xl">Want
                                a<br>place <br>to stay?</h2>
                        </div>
                        <img src="{{ url('resources/logos/dwelly_logo.png') }}" alt="Dwelly Logo" class="w-1/2 h-1/2">
                    </div>
                    <div class="col-span-2 border-l-8 border-l-piss-yellow mx-4 px-4">
                        <h2 class="font-GraphikMedium text-sm md:text-base">DWELLY for Tenants</h2>
                        <p class="font-GraphikLight text-sm md:text-base">A hassle free way of looking, booking and
                            staying in a city</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="w-3/4 3xl:w-4/6 bg-darker-3 border-piss-yellow border-t-8 border-b-8 z-0 flex justify-evenly pl-4 md:pl-0 items-center text-secondary text-center font-GraphikLight"
            style="background-image:url({{ url('resources/images/real-estate-bg.svg') }}); background-size:50vw; background-repeat: no-repeat; background-position: right;">
            <div class="flex-col justify-center items-center">
                <img src="{{ url('resources/images/house.svg') }}" alt="House" class="w-full">
                <h3>100% Verified <br> properties</h3>
            </div>
            <div class="flex-col justify-center items-center">
                <img src="{{ url('resources/images/agent.svg') }}" alt="Agent" class="w-full">
                <h3>Fixed service <br> fee</h3>
            </div>
            <div class="flex-col justify-center items-center">
                <img src="{{ url('resources/images/tools.svg') }}" alt="Tools" class="w-full">
                <h3>Maintenance <br> on demand</h3>
            </div>
        </div>
    </div>
    <!-- Show on mobile -->
    <div class="sm:hidden">
        <div class="p-4">
            <h2 class="font-GraphikLight text-sm text-darker-2">Have a place to rent out?</h2>
            <a href="{{ route('owners') }}" class="bg-darker-3 text-piss-yellow font-GraphikSemibold text-sm  text-center uppercase p-2 rounded-sm w-full block">Learn
                More About Renting Out</a>
        </div>
        <h2 class="text-center font-GraphikMedium text-2xl text-darker-3 py-6">Dwelly for Tenants</h2>
        <div class="bg-darker-3 h-56">
            <section id="dwelly-for-tenants-slide" class="splide h-full" aria-label="Beautiful Images">
                <div class="splide__track">
                    <ul class="splide__list">
                        <li class="splide__slide">
                            <div class="flex justify-around gap-3 p-8 items-center self-center mt-4">
                                <img src="{{ url('resources/images/house.svg') }}" alt="House">
                                <div>
                                    <h3 class="text-secondary font-GraphikMedium">100% Verified properties</h3>
                                    <p class="text-secondary font-GraphikLight">Get fully verified properties for rent
                                        in Guwahati. What you see here is what you get.</p>
                                </div>
                            </div>
                        </li>
                        <li class="splide__slide">
                            <div class="flex justify-around gap-3 p-8 items-center self-center mt-4">
                                <img src="{{ url('resources/images/agent.svg') }}" alt="Agent">
                                <div>
                                    <h3 class="text-secondary font-GraphikMedium">Fixed service fee</h3>
                                    <p class="text-secondary font-GraphikLight">Find your next home without paying
                                        exorbitant brokerage.</p>
                                </div>
                            </div>
                        </li>
                        <li class="splide__slide">
                            <div class="flex justify-around gap-3 p-8 items-center self-center mt-4">
                                <img src="{{ url('resources/images/tools.svg') }}" alt="Tools">
                                <div>
                                    <h3 class="text-secondary font-GraphikMedium">Maintenance on demand</h3>
                                    <p class="text-secondary font-GraphikLight">For any property related issue get 24x7
                                        support during your entire stay.</p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </div>

    <!-- Ready to move properties -->
    <div class="lg:flex gap-0 sm:gap-8 p-0 sm:p-2 my-12 sm:my-36 items-center lg:Left-Space">
        <div class="shrink-0 min-w-max lg:pr-4">
            <h2 class="hidden lg:block font-GraphikSemibold text-5xl leading-snug text-darker-3">
                Ready to <br>
                move <br>
                properties
            </h2>
            <h2
                class="lg:hidden font-GraphikSemibold text-2xl md:text-4xl leading-snug text-darker-3 text-center mb-8">
                Ready to move properties
            </h2>
        </div>
        <div class="flex-1 min-w-0">
            <section id="prime-properties-slide" class="splide" aria-label="Beautiful Images">
            <div class="splide__track">
                <ul class="splide__list">
                    @foreach ($properties as $property)
                        @php
                            $coverPhoto = $property->coverPhoto ?: $property->photos->first() ?: $property->propertyImages->first();
                            $coverUrl = $coverPhoto ? url('storage/' . ($coverPhoto->file_path ?? $coverPhoto->image_path)) : url('resources/images/sample_prop.jpeg');
                        @endphp
                        <li class="splide__slide">
                            <a href="{{ route('viewProperty', $property->id) }}">
                                <div class="p-0 sm:p-2">
                                    <img src="{{ $coverUrl }}"
                                        alt="{{ $property->building_name }}" class="w-full h-56 object-cover">
                                    <div class="flex items-center justify-between pt-2 px-2 text-sm font-Graphik">
                                        <div class="shrink-0 flex items-center gap-1">
                                            <svg height="10" width="10" class="shrink-0" viewBox="0 0 10 10">
                                                <circle cx="5" cy="5" r="5" fill="#686868" />
                                            </svg>
                                            <span>{{ $property->bhk?->name ?? 'BHK' }}</span>
                                        </div>
                                        <div class="flex-1 min-w-0 flex justify-center items-center px-1.5">
                                            <div class="flex items-center gap-1 min-w-0 max-w-full">
                                                <svg height="10" width="10" class="shrink-0" viewBox="0 0 10 10">
                                                    <circle cx="5" cy="5" r="5" fill="#686868" />
                                                </svg>
                                                <span class="truncate" title="{{ $property->building_name }}">
                                                    {{ $property->building_name }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="shrink-0 flex items-center gap-1 whitespace-nowrap">
                                            <svg height="10" width="10" class="shrink-0" viewBox="0 0 10 10">
                                                <circle cx="5" cy="5" r="5" fill="#e8c811" />
                                            </svg>
                                            <span>₹ {{ number_format($property->rent) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
        </div>
    </div>

    {{-- Hide on mobile --}}
    <div class="hidden sm:block bg-darker-3 sm:mt-20 p-4">
        <div class="Site-Max-Width mx-auto H-30pvw MaxH-18 flex justify-around items-center"
            style="background-image:url({{ url('resources/images/real-estate-bg.svg') }}); background-size:65vw; background-repeat: no-repeat;">
            <div class="grid grid-cols-3 gap-16 justify-between items-center">
                <a href="{{ route('allProperties') }}" class="group block">
                    <div
                        class="flex-col p-6 md:p-10 lg:p-12 border-2 border-transparent hover:border-piss-yellow group-hover:border-piss-yellow hover:Rounded-BOX-1 group-hover:Rounded-BOX-1 transition-all duration-300 ease-in-out cursor-pointer text-center">
                        <img class="mx-auto mb-4" src="{{ url('resources/icons/students.svg') }}" alt="students">
                        <h3 class="text-secondary font-GraphikLight text-xl text-center">Students</h3>
                    </div>
                </a>
                <a href="{{ route('allProperties') }}" class="group block">
                    <div
                        class="flex-col p-6 md:p-10 lg:p-12 border-2 border-transparent hover:border-piss-yellow group-hover:border-piss-yellow hover:Rounded-BOX-1 group-hover:Rounded-BOX-1 transition-all duration-300 ease-in-out cursor-pointer text-center">
                        <img class="mx-auto mb-4" src="{{ url('resources/icons/families.svg') }}" alt="families">
                        <h3 class="text-secondary font-GraphikLight text-xl text-center">Families</h3>
                    </div>
                </a>
                <a href="{{ route('allProperties') }}" class="group block">
                    <div
                        class="flex-col p-6 md:p-10 lg:p-12 border-2 border-transparent hover:border-piss-yellow group-hover:border-piss-yellow hover:Rounded-BOX-2 group-hover:Rounded-BOX-2 transition-all duration-300 ease-in-out cursor-pointer text-center">
                        <img class="mx-auto mb-4" src="{{ url('resources/icons/bachelors.svg') }}" alt="bachelors">
                        <h3 class="text-secondary font-GraphikLight text-xl text-center">Working <br> Bachelors</h3>
                    </div>
                </a>
            </div>
            <div>
                <h2 class="text-secondary font-system text-4xl md:text-5xl lg:text-6xl">
                    Categories
                </h2>
            </div>
        </div>
    </div>

    <!-- Hide on desktop -->
    <div class="sm:hidden">
        <h2 class="text-center font-GraphikMedium text-2xl text-darker-3 py-6">Categories</h2>
        <div class="h-56">
            <section id="categories-slide" class="splide max-h-96" aria-label="Beautiful Images"
                style="background-image: linear-gradient(#686868, #686868 100%);
                background-size: 100% 60px;
                background-repeat: no-repeat;
                background-position: center bottom;">
                <div class="splide__track">
                    <ul class="splide__list">
                        <li class="splide__slide">
                            <a href="{{ route('allProperties') }}">
                                <div class="grid grid-cols-1 bg-darker-3 justify-between p-8 mt-4 m-4 rounded-3xl">
                                    <img class="mx-auto" src="{{ url('resources/icons/students.svg') }}" alt="students">
                                    <h3 class="text-secondary font-GraphikMedium text-center">Students</h3>
                                </div>
                            </a>
                        </li>
                        <li class="splide__slide">
                            <a href="{{ route('allProperties') }}">
                                <div class="grid grid-cols-1 bg-darker-3 justify-between p-8 mt-4 m-4 rounded-3xl">
                                    <img class="mx-auto" src="{{ url('resources/icons/families.svg') }}" alt="families">
                                    <h3 class="text-secondary font-GraphikMedium text-center">Families</h3>
                                </div>
                            </a>
                        </li>
                        <li class="splide__slide">
                            <a href="{{ route('allProperties') }}">
                                <div class="grid grid-cols-1 bg-darker-3 justify-between p-8 mt-4 m-4 rounded-3xl">
                                    <img class="mx-auto" src="{{ url('resources/icons/bachelors.svg') }}"
                                        alt="bachelors">
                                    <h3 class="text-secondary font-GraphikMedium text-center">Bachelors</h3>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </div>


    <div class="hidden sm:block sm:my-20 p-4">
        <div class="Site-Max-Width mx-auto H-30pvw MaxH-18 flex justify-around items-center">
            <div class="grid grid-cols-3 justify-between items-center">
                <div class="flex-col p-6 md:p-10 lg:p-14">
                    <img class="mx-auto mb-4" src="{{ url('resources/icons/rent_on_time.svg') }}"
                        alt="Rent On Time">
                    <h3 class="text-dark-2 font-GraphikLight text-center">Rent on time</h3>
                </div>
                <div class="flex-col p-6 md:p-10 lg:p-14">
                    <img class="mx-auto mb-4" src="{{ url('resources/icons/property_maintenance.svg') }}"
                        alt="Property maintenance">
                    <h3 class="text-dark-2 font-GraphikLight text-center">Property maintenance</h3>
                </div>
                <div class="flex-col p-6 md:p-10 lg:p-14">
                    <img class="mx-auto mb-4" src="{{ url('resources/icons/no_paperworks.svg') }}"
                        alt="No Paperworks">
                    <h3 class="text-dark-2 font-GraphikLight text-center">No paperwork</h3>
                </div>
                <a href="{{ route('owners') }}"
                    class="col-span-3 mx-12 bg-darker-3 text-piss-yellow rounded-sm text-center p-4 text-lg hover:bg-piss-yellow hover:text-darker-3 ease-in-out duration-300 font-GraphikMedium">
                    LEARN MORE ABOUT RENTING OUT</a>
            </div>
            <div
                class="H-40pvw MaxH-23 W-30pvw MaxW-23 bg-white border-piss-yellow border-4 Rounded-BOX-1 flex items-center">
                <div>
                    <div class="flex justify-between px-4 mb-6">
                        <div class="flex items-center">
                            <h2 class="text-darker-2 font-GraphikMedium text-xl md:text-2xl lg:text-3xl">Want
                                to<br>rent your<br>Property?</h2>
                        </div>
                        <img src="{{ url('resources/logos/dwelly_logo.png') }}" alt="Dwelly Logo"
                            class="w-1/2 h-1/2">
                    </div>
                    <div class="col-span-2 border-l-8 border-l-darker-2 mx-4 px-4">
                        <h2 class="font-GraphikMedium text-sm md:text-base">DWELLY for Owners</h2>
                        <p class="font-GraphikLight text-sm md:text-base text-justify">End to end property managment
                            solutions for real estate properties</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hide on desktop -->
    <div class="sm:hidden">
        <h2 class="text-center font-GraphikMedium text-2xl text-darker-3 py-6">Dwelly for Owners</h2>
        <div>
            <section id="dwelly-for-owners-slide" class="splide h-full" aria-label="Beautiful Images">
                <div class="splide__track">
                    <ul class="splide__list">
                        <li class="splide__slide">
                            <div class="flex justify-evenly gap-6 p-8 items-center self-center mt-4">
                                <img src="{{ url('resources/icons/rent_on_time.svg') }}" alt="Rent on Time">
                                <div>
                                    <h3 class="text-darker-3 text-lg pb-2 font-GraphikMedium">Rent on time</h3>
                                    <p class="text-darker-3 font-GraphikLight">We ensure that you get your rent payment
                                        on time.</p>
                                </div>
                            </div>
                        </li>
                        <li class="splide__slide">
                            <div class="flex justify-around gap-6 p-8 items-center self-center mt-4">
                                <img src="{{ url('resources/icons/property_maintenance.svg') }}"
                                    alt="Property maintenance">
                                <div>
                                    <h3 class="text-darker-3 text-lg pb-2 font-GraphikMedium">Property maintenance</h3>
                                    <p class="text-darker-3 font-GraphikLight">We help with figuring out and solving
                                        your day to day operational issues.</p>
                                </div>
                            </div>
                        </li>
                        <li class="splide__slide">
                            <div class="flex justify-around gap-6 p-8 items-center self-center mt-4">
                                <img src="{{ url('resources/icons/no_paperworks.svg') }}" alt="No paperwork">
                                <div>
                                    <h3 class="text-darker-3 text-lg pb-2 font-GraphikMedium">No paperwork</h3>
                                    <p class="text-darker-3 font-GraphikLight">We do all the paperworks for you like
                                        rental agreements and verification documents.</p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
        <div class="p-4 my-2">
            <a href="{{ route('owners') }}"
                class="bg-darker-3 text-piss-yellow font-GraphikSemibold text-sm  text-center uppercase p-2 rounded-sm w-full block">
                Learn More About Renting Out
            </a>
        </div>
    </div>

    @section('before-body-end')
        <script>
            function initIndexSplides() {
                if (typeof Splide === 'undefined') {
                    setTimeout(initIndexSplides, 50);
                    return;
                }

                if (document.querySelector('#dwelly-for-tenants-slide')) {
                    new Splide('#dwelly-for-tenants-slide', {
                        type: 'loop',
                        arrows: false,
                        autoplay: true,
                        interval: 3000,
                    }).mount();
                }

                if (document.querySelector('#prime-properties-slide')) {
                    new Splide('#prime-properties-slide', {
                        type: 'loop',
                        arrows: true,
                        pagination: false,
                        autoplay: true,
                        interval: 5000,
                        perPage: 6,
                        breakpoints: {
                            2560: { perPage: 4 },
                            1920: { perPage: 3 },
                            1400: { perPage: 2 },
                            700: { perPage: 1 }
                        }
                    }).mount();
                }

                if (document.querySelector('#categories-slide')) {
                    new Splide('#categories-slide', {
                        type: 'loop',
                        arrows: false,
                        pagination: false,
                        autoplay: true,
                        interval: 3000,
                        padding: '6rem',
                    }).mount();
                }

                if (document.querySelector('#dwelly-for-owners-slide')) {
                    new Splide('#dwelly-for-owners-slide', {
                        type: 'loop',
                        arrows: false,
                        autoplay: true,
                        interval: 3000,
                    }).mount();
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initIndexSplides);
            } else {
                initIndexSplides();
            }
        </script>
        <style>
            .splide__pagination__page {
                background-color: #e6e6e6;
            }

            .splide__pagination__page.is-active {
                background-color: #e8c811;
            }

            .splide__arrow {
                background-color: #fff;
            }

            .splide__arrow svg {
                fill: #e8c811;
            }

            .splide__slide.is-active {
                width: 110%;
                height: 110%;
            }
        </style>
    @endsection
</x-public-layout>
