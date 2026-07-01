<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Reference Data Management
            </x-slot>
            <x-slot name="description">
                Manage the lookup tables used throughout the system.
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- Example cards for Reference Data --}}
                <x-filament::card>
                    <h3 class="text-lg font-medium">Property Types</h3>
                    <p class="text-sm text-gray-500 mb-4">Apartment, Villa, etc.</p>
                    <x-filament::button href="#" tag="a" color="gray" size="sm">
                        Manage
                    </x-filament::button>
                </x-filament::card>

                <x-filament::card>
                    <h3 class="text-lg font-medium">Amenities</h3>
                    <p class="text-sm text-gray-500 mb-4">Lift, Power Backup, etc.</p>
                    <x-filament::button href="#" tag="a" color="gray" size="sm">
                        Manage
                    </x-filament::button>
                </x-filament::card>

                <x-filament::card>
                    <h3 class="text-lg font-medium">Vendor Trades</h3>
                    <p class="text-sm text-gray-500 mb-4">Plumbing, Electrical, etc.</p>
                    <x-filament::button href="#" tag="a" color="gray" size="sm">
                        Manage
                    </x-filament::button>
                </x-filament::card>
                
                {{-- Future iterations would use livewire components or dynamically rendered cards --}}
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
