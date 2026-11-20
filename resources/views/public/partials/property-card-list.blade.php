@forelse ($properties as $property)
    @php
        $cPhoto = $property->coverPhoto ?: $property->photos->first();
        $photoUrl = $cPhoto ? Storage::disk('public')->url($cPhoto->file_path) : url('resources/images/sample_prop.jpeg');
        $isAjaxRequest = isset($isAjax) && $isAjax;
    @endphp
    <div class="bg-white rounded-2xl border border-secondary/80 shadow-xs hover:shadow-lg transition-all duration-300 overflow-hidden flex flex-col md:flex-row md:h-[13.5rem] group {{ $isAjaxRequest ? 'animate-card-enter' : '' }}"
        @if($isAjaxRequest) style="animation-delay: {{ min($loop->index * 60, 480) }}ms;" @endif>
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

                    {{-- Desktop Rent Display --}}
                    <div class="hidden sm:block text-right shrink-0">
                        <span class="text-[11px] text-darker-2 block font-GraphikMedium leading-tight">Monthly Rent</span>
                        <span class="font-GraphikBold text-xl text-darker-3 leading-tight">₹{{ number_format($property->rent) }}</span>
                    </div>
                </div>

                {{-- Spec Highlights Grid --}}
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
    @if (!isset($isAjax) || !$isAjax)
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
    @endif
@endforelse
