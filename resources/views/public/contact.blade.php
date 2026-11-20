<x-public-layout>
    <div id="hero">
        <img src="{{ url('resources/images/city_bg.svg') }}" alt="City Background" class="w-full mt-12 mb-6 sm:mb-12">
    </div>

    <div class="Site-Max-Width mx-auto px-4 md:flex justify-between items-start gap-8">
        <div class="flex-1">
            <h1 class="font-GraphikSemibold text-4xl text-darker-3 mb-6">Contact Us</h1>
            <div class="border-r-8 border-r-piss-yellow bg-secondary p-6 rounded-l-sm">
                <address class="font-GraphikMedium text-darker-3 not-italic space-y-4">
                    <div>
                        <a href="tel:+919678644832" class="flex items-center gap-3 hover:text-piss-yellow transition">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-6 h-6 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                            </svg>
                            +91 96786 44832
                        </a>
                    </div>
                    <div>
                        <a href="mailto:hello@dwelly.in" class="flex items-center gap-3 hover:text-piss-yellow transition">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-6 h-6 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                            hello@dwelly.in
                        </a>
                    </div>
                    <div>
                        <a href="#map" class="flex items-center gap-3 hover:text-piss-yellow transition">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-6 h-6 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                            </svg>
                            Debadaru Path, Ambikagiri Nagar, Guwahati, Assam 781024
                        </a>
                    </div>
                </address>
            </div>
        </div>

        <div class="flex-1 bg-white border-2 border-secondary rounded-sm shadow-md p-6 md:p-8 mt-8 md:mt-0">
            <livewire:contact-form />
        </div>
    </div>

    {{-- Map Section --}}
    <div id="map" class="Site-Max-Width mx-auto px-4 mt-16">
        <div class="rounded-lg overflow-hidden border border-gray-200 h-96">
            <iframe width="100%" height="100%" frameborder="0" style="border:0"
                src="https://maps.google.com/maps?q={{ urlencode('Debadaru Path, Ambikagiri Nagar, Zoo Road, Guwahati, Assam 781024') }}&output=embed"
                allowfullscreen loading="lazy"></iframe>
        </div>
    </div>
</x-public-layout>
