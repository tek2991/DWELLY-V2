<x-filament::section>
    <x-slot name="heading">
        <div style="display: flex; align-items: center; gap: 8px;">
            <x-heroicon-o-bolt style="width: 1.25rem; height: 1.25rem; color: #2563eb;" />
            <span>Quick Actions</span>
        </div>
    </x-slot>

    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
        <x-filament::button
            tag="a"
            href="{{ \App\Filament\Resources\Operations\MOUResource::getUrl('create') }}"
            icon="heroicon-m-document-plus"
            color="primary"
        >
            Create MOU
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('create') }}"
            icon="heroicon-m-wrench-screwdriver"
            color="warning"
        >
            Create Maintenance Request
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ \App\Filament\Resources\Parties\PartyResource::getUrl('create') }}"
            icon="heroicon-m-user-plus"
            color="info"
        >
            Create Party
        </x-filament::button>
    </div>
</x-filament::section>
