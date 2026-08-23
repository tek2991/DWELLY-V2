<div class="fi-resource-relation-manager" style="display: flex; flex-direction: column; gap: 1.5rem;">
    {{-- Quick Financial Reference Cards (Client Invoice & Vendor Bills) --}}
    @include('filament.resources.operations.maintenance-requests.financial-reference-card', ['ticket' => $this->getOwnerRecord()])

    {{ $this->content }}

    <x-filament-panels::unsaved-action-changes-alert />
</div>
