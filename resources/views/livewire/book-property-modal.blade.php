<div>
    <div x-data="{ open: @entangle('isOpen') }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="$wire.closeModal()">

        {{-- Backdrop with smooth fade --}}
        <div x-show="open"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
            @click="$wire.closeModal()"></div>

        {{-- Centering Container --}}
        <div class="min-h-full flex items-center justify-center p-4 text-center sm:p-0">
            {{-- Modal Dialog Panel with soft scale & vertical slide --}}
            <div x-show="open"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden text-left my-8 transform z-10 border border-gray-100"
                @click.stop>
                {{-- Modal Header --}}
                <div class="bg-darker-3 text-secondary flex justify-between items-center px-5 py-3.5">
                    <h3 class="font-GraphikMedium text-lg text-white">Book A Visit</h3>
                    <button type="button" wire:click="closeModal"
                        class="text-secondary hover:text-piss-yellow rounded-lg text-sm p-1 inline-flex items-center cursor-pointer transition">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>

                @if ($success === false)
                    <form wire:submit="submit">
                        @if ($property)
                            <div class="p-4 bg-gray-50 border-b border-gray-100">
                                <h3 class="text-center text-darker-3 text-lg font-GraphikSemibold">
                                    {{ $property->building_name ?? 'Property' }}@if($property->localityRef), {{ $property->localityRef->name }}@elseif($property->locality), {{ $property->locality }}@endif
                                </h3>
                                <p class="text-center text-darker-2 font-GraphikLight text-sm mt-1">
                                    {{ ($property->bhkType?->name ?? 'BHK') . ' • ' . ($property->propertyType?->name ?? 'Apartment') }}
                                </p>
                            </div>
                        @endif

                        <div class="p-6 space-y-4">
                            <div>
                                <label for="book_name" class="font-GraphikMedium text-darker-3 text-sm">Name*</label>
                                @error('name')
                                    <span class="text-xs text-red-600 block">{{ $message }}</span>
                                @enderror
                                <input type="text" id="book_name" wire:model="name"
                                    class="w-full mt-1 border border-secondary rounded-lg p-2.5 text-sm outline-none focus:outline-none focus:ring-0 focus:border-piss-yellow transition duration-150">
                            </div>

                            <div>
                                <label for="book_phone" class="font-GraphikMedium text-darker-3 text-sm">Phone*</label>
                                @error('phone')
                                    <span class="text-xs text-red-600 block">{{ $message }}</span>
                                @enderror
                                <input type="tel" id="book_phone" wire:model="phone"
                                    class="w-full mt-1 border border-secondary rounded-lg p-2.5 text-sm outline-none focus:outline-none focus:ring-0 focus:border-piss-yellow transition duration-150">
                            </div>

                            <div>
                                <label for="book_email" class="font-GraphikMedium text-darker-3 text-sm">Email</label>
                                @error('email')
                                    <span class="text-xs text-red-600 block">{{ $message }}</span>
                                @enderror
                                <input type="email" id="book_email" wire:model="email"
                                    class="w-full mt-1 border border-secondary rounded-lg p-2.5 text-sm outline-none focus:outline-none focus:ring-0 focus:border-piss-yellow transition duration-150">
                            </div>

                            <div>
                                <label for="book_message" class="font-GraphikMedium text-darker-3 text-sm">Message / Preferred Time</label>
                                @error('message')
                                    <span class="text-xs text-red-600 block">{{ $message }}</span>
                                @enderror
                                <textarea id="book_message" rows="3" wire:model="message"
                                    class="w-full mt-1 border border-secondary rounded-lg p-2.5 text-sm outline-none focus:outline-none focus:ring-0 focus:border-piss-yellow transition duration-150"></textarea>
                            </div>

                            <div class="pt-2">
                                <button type="submit"
                                    class="w-full bg-piss-yellow text-darker-3 font-GraphikMedium rounded-xl text-center py-3 text-base hover:bg-darker-3 hover:text-piss-yellow transition duration-200 cursor-pointer shadow-xs">
                                    SCHEDULE VISIT
                                </button>
                            </div>
                        </div>
                    </form>
                @else
                    <div class="p-8 text-center">
                        <img src="{{ url('resources/images/message_received.svg') }}" alt="Message Received" class="w-64 mx-auto">
                        <p class="text-darker-2 text-lg font-GraphikMedium mt-4">
                            Thank you for your interest! <br> Our property manager will reach out shortly.
                        </p>
                        <div class="mt-6">
                            <button type="button" wire:click="closeModal"
                                class="bg-piss-yellow text-darker-3 px-6 py-2 rounded-xl text-sm font-GraphikMedium hover:bg-darker-3 hover:text-piss-yellow transition duration-200 cursor-pointer shadow-xs">
                                CLOSE
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
