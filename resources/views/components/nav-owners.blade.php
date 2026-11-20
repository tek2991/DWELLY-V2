<div x-data="{ drawerOpen: false }">
    <div class="flex justify-between fixed w-full z-20" style="background: linear-gradient(to right, #e6e6e6 60%, #4c4d47 40%);">
        <div class="flex justify-between mx-auto w-full Site-Max-Width p-4 pt-2">
            <!-- Logo -->
            <div class="shrink-0 flex items-center">
                <a href="{{ route('home') }}">
                    <x-site-logo class="w-32" />
                </a>
            </div>
            <!-- Hamburger -->
            <div class="items-center my-auto">
                <button type="button" @click="drawerOpen = !drawerOpen"
                    class="inline-flex items-center justify-center p-2 mb-2 rounded-md text-secondary hover:text-piss-yellow transition duration-150 ease-in-out cursor-pointer">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Drawer Backdrop & Panel -->
    <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-40 flex justify-end overflow-hidden" @keydown.escape.window="drawerOpen = false">
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
</div>
