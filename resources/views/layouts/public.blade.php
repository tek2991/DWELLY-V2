@props(['filters' => null, 'total' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Dwelly') }}</title>

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak] { display: none !important; }</style>
</head>

<body class="antialiased overflow-x-clip">
    <div>
        @if (Route::is('owners'))
            <x-nav-owners />
        @elseif (Route::is('allProperties') || Route::is('viewProperty'))
            <x-nav-search :filters="$filters ?? null" :total="$total ?? null"/>
        @else
            <x-nav-main />
        @endif
    </div>

    <div class="font-sans text-gray-900 antialiased min-h-screen {{ Route::is('allProperties') ? 'pt-28 sm:pt-32' : (Route::is('owners') ? 'pt-0' : 'pt-24') }}">
        {{ $slot }}
    </div>

    <div class="bg-darker-3">
        <footer>
            <div class="Site-Max-Width mx-auto px-4 py-10 sm:py-12 text-secondary">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-12">
                    <div>
                        <h2 class="font-GraphikMedium text-xl mb-4 text-white">About Us</h2>
                        <p class="text-left font-Graphik text-sm sm:text-base leading-relaxed text-gray-300">
                            Dwelly is an end to end property management business, founded in August 2018, to enable tenants to find and live in rental properties without any hassle.
                        </p>
                    </div>
                    <div>
                        <h2 class="font-GraphikMedium text-xl mb-4 text-white">Contact Us</h2>
                        <p class="text-left font-Graphik text-sm sm:text-base leading-relaxed text-gray-300">
                            Debadaru Path, <br>
                            Ambikagiri Nagar, Zoo Road <br>
                            Guwahati, Assam 781024
                        </p>
                        <p class="text-left mt-3 font-Graphik text-sm sm:text-base leading-relaxed text-gray-300">
                            <a href="tel:+919678644832" class="hover:text-piss-yellow transition">+91 96786 44832</a> <br>
                            <a href="mailto:hello@dwelly.in" class="hover:text-piss-yellow transition">hello@dwelly.in</a>
                        </p>
                    </div>
                    <div>
                        <h2 class="font-GraphikMedium text-xl mb-4 text-white">Follow Us</h2>
                        <a href="https://www.facebook.com/www.dwelly.in" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-blue-400 hover:text-blue-300 font-GraphikSemibold text-base transition">
                            <svg fill="#3f83f8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" class="w-6 h-6 mr-2">
                                <path
                                    d="M25,3C12.85,3,3,12.85,3,25c0,11.03,8.125,20.137,18.712,21.728V30.831h-5.443v-5.783h5.443v-3.848 c0-6.371,3.104-9.168,8.399-9.168c2.536,0,3.877,0.188,4.512,0.274v5.048h-3.612c-2.248,0-3.033,2.131-3.033,4.533v3.161h6.588 l-0.894,5.783h-5.694v15.944C38.716,45.318,47,36.137,47,25C47,12.85,37.15,3,25,3z" />
                            </svg>
                            Facebook
                        </a>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-700/60 flex flex-col sm:flex-row justify-between items-center text-xs text-gray-400 gap-2">
                    <p>© {{ date('Y') }} Dwelly. All rights reserved.</p>
                    <p>Renting made easy.</p>
                </div>
            </div>

            <a title="Call: +91 96786 44832" href="tel:+919678644832"
                class="fixed z-40 bottom-20 sm:bottom-10 right-4 sm:right-8 bg-piss-yellow h-12 w-12 sm:h-14 sm:w-14 p-2 rounded-full drop-shadow-lg flex justify-center items-center text-white hover:bg-yellow-400 hover:drop-shadow-2xl transition duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    class="w-6 h-6 sm:w-7 sm:h-7" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                </svg>
            </a>
        </footer>
    </div>

    <!-- Modals -->
    <livewire:book-property-modal />
    <livewire:rent-out-modal />

    <!-- Before-body-end -->
    @yield('before-body-end')

    @livewireScripts
</body>

</html>
