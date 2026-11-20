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
                class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto text-left my-8 transform z-10 border border-gray-100"
                @click.stop>
                {{-- Modal Header --}}
                <div class="bg-darker-3 text-secondary flex justify-between items-center px-6 py-3.5 sticky top-0 z-10">
                    <h3 class="font-GraphikMedium text-lg text-white">Rent Out With Dwelly</h3>
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
                <form wire:submit="submit" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="rent_name" class="font-GraphikMedium text-darker-3 text-sm">Name*</label>
                            @error('name')
                                <span class="text-xs text-red-600 block">{{ $message }}</span>
                            @enderror
                            <input type="text" id="rent_name" wire:model="name"
                                class="w-full mt-1 border-2 border-secondary rounded-sm p-2 text-sm focus:ring-0 focus:border-piss-yellow">
                        </div>

                        <div>
                            <label for="rent_phone" class="font-GraphikMedium text-darker-3 text-sm">Phone*</label>
                            @error('phone')
                                <span class="text-xs text-red-600 block">{{ $message }}</span>
                            @enderror
                            <input type="tel" id="rent_phone" wire:model="phone"
                                class="w-full mt-1 border-2 border-secondary rounded-sm p-2 text-sm focus:ring-0 focus:border-piss-yellow">
                        </div>

                        <div>
                            <label for="rent_email" class="font-GraphikMedium text-darker-3 text-sm">Email</label>
                            @error('email')
                                <span class="text-xs text-red-600 block">{{ $message }}</span>
                            @enderror
                            <input type="email" id="rent_email" wire:model="email"
                                class="w-full mt-1 border-2 border-secondary rounded-sm p-2 text-sm focus:ring-0 focus:border-piss-yellow">
                        </div>
                    </div>

                    <div class="pt-2">
                        <label for="num_of_properties" class="font-GraphikMedium text-darker-3 text-sm">No. of Properties to Rent Out</label>
                        <select id="num_of_properties" wire:model.live="num_of_properties"
                            class="w-24 mt-1 border-2 border-secondary rounded-sm p-2 text-sm focus:ring-0 focus:border-piss-yellow block">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>

                    <div>
                        @for ($i = 0; $i < $num_of_properties; $i++)
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <h4 class="font-GraphikMedium text-darker-3 text-base mb-3">Property {{ $i + 1 }}</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="property_type_{{ $i }}" class="font-GraphikMedium text-darker-3 text-xs">Property Type*</label>
                                        <select id="property_type_{{ $i }}" wire:model="property_type.{{ $i }}"
                                            class="w-full mt-1 border-2 border-secondary rounded-sm p-2 text-sm focus:ring-0 focus:border-piss-yellow">
                                            @foreach ($propertyTypes as $type)
                                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label for="availability_{{ $i }}" class="font-GraphikMedium text-darker-3 text-xs">Vacancy*</label>
                                        <select id="availability_{{ $i }}" wire:model="availability.{{ $i }}"
                                            class="w-full mt-1 border-2 border-secondary rounded-sm p-2 text-sm focus:ring-0 focus:border-piss-yellow">
                                            <option value="1">Vacant</option>
                                            <option value="0">Occupied</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="bedroom_{{ $i }}" class="font-GraphikMedium text-darker-3 text-xs">Bedrooms*</label>
                                        <select id="bedroom_{{ $i }}" wire:model="bedroom.{{ $i }}"
                                            class="w-full mt-1 border-2 border-secondary rounded-sm p-2 text-sm focus:ring-0 focus:border-piss-yellow">
                                            @for ($j = 1; $j <= 5; $j++)
                                                <option value="{{ $j }}">{{ $j }} BHK</option>
                                            @endfor
                                        </select>
                                    </div>

                                    <div>
                                        <label for="bathroom_{{ $i }}" class="font-GraphikMedium text-darker-3 text-xs">Bathrooms*</label>
                                        <select id="bathroom_{{ $i }}" wire:model="bathroom.{{ $i }}"
                                            class="w-full mt-1 border-2 border-secondary rounded-sm p-2 text-sm focus:ring-0 focus:border-piss-yellow">
                                            @for ($j = 1; $j <= 5; $j++)
                                                <option value="{{ $j }}">{{ $j }}</option>
                                            @endfor
                                        </select>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label for="building_name_{{ $i }}" class="font-GraphikMedium text-darker-3 text-xs">Building / Society Name*</label>
                                        @error('building_name.' . $i)
                                            <span class="text-xs text-red-600 block">{{ $message }}</span>
                                        @enderror
                                        <input type="text" id="building_name_{{ $i }}" wire:model="building_name.{{ $i }}"
                                            class="w-full mt-1 border-2 border-secondary rounded-sm p-2 text-sm focus:ring-0 focus:border-piss-yellow"
                                            placeholder="e.g. Greenwood Apartments">
                                    </div>

                                    <div class="md:col-span-2">
                                        <label for="address_{{ $i }}" class="font-GraphikMedium text-darker-3 text-xs">Address / Locality</label>
                                        <input type="text" id="address_{{ $i }}" wire:model="address.{{ $i }}"
                                            class="w-full mt-1 border-2 border-secondary rounded-sm p-2 text-sm focus:ring-0 focus:border-piss-yellow"
                                            placeholder="e.g. Zoo Road, Guwahati">
                                    </div>
                                </div>
                            </div>
                        @endfor
                    </div>

                    <div class="pt-4">
                        <button type="submit"
                            class="w-full bg-piss-yellow text-darker-3 font-GraphikMedium rounded-sm text-center py-3 text-base hover:bg-darker-3 hover:text-piss-yellow ease-in-out duration-300 cursor-pointer">
                            SUBMIT FOR REVIEW
                        </button>
                    </div>
                </form>
            @else
                <div class="p-8 text-center">
                    <img src="{{ url('resources/images/message_received.svg') }}" alt="Message Received" class="w-64 mx-auto">
                    <p class="text-darker-2 text-lg font-GraphikMedium mt-4">
                        Thank you for your interest! <br> Our onboarding executive will contact you shortly to begin the property assessment.
                    </p>
                    <div class="mt-6">
                        <button type="button" wire:click="closeModal"
                            class="bg-piss-yellow text-darker-3 px-6 py-2 rounded-sm text-sm font-GraphikMedium hover:bg-darker-3 hover:text-piss-yellow ease-in-out duration-300 cursor-pointer">
                            CLOSE
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
</div>
