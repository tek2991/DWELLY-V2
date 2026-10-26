@props([
    'audit',
    'actions' => null,
])

@php
    $propertyUrl = $audit->property_id ? \App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $audit->property_id]) : null;
@endphp

<div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; width: 100%;">
    <div style="flex: 1 1 260px; min-width: 0; max-width: 100%;">
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: rgba(17, 24, 39, 1); display: flex; align-items: center; gap: 0.375rem; word-break: break-word;">
                <span>{{ $audit->property->building_name ?? 'Property' }}</span>
                @if($audit->property?->code)
                    <span style="font-size: 0.9375rem; font-weight: 500; color: rgba(107, 114, 128, 1);">({{ $audit->property->code }})</span>
                @endif
            </h3>
            @if($propertyUrl)
                <a href="{{ $propertyUrl }}" target="_blank" style="display: inline-flex; align-items: center; justify-content: center; padding: 0.2rem; border-radius: 0.25rem; background-color: rgba(243, 244, 246, 1); color: rgba(75, 85, 99, 1); transition: background-color 150ms;" title="View Property Profile" aria-label="View Property Profile">
                    <svg style="width: 0.875rem; height: 0.875rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                </a>
            @endif
            <x-filament::badge :color="$audit->status?->getColor() ?? 'gray'" size="sm">
                {{ $audit->status?->getLabel() ?? 'Draft' }}
            </x-filament::badge>
            @if($audit->is_locked)
                <x-filament::badge color="danger" size="sm" icon="heroicon-o-lock-closed">
                    Locked
                </x-filament::badge>
            @endif
        </div>

        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.375rem 0.75rem; font-size: 0.8125rem; color: #4b5563; margin-top: 0.375rem;">
            <span><span style="color: #9ca3af;">Audit:</span> <strong style="color: #111827;">{{ $audit->audit_number }}</strong></span>
            <span><span style="color: #9ca3af;">Type:</span> <strong style="color: #111827;">{{ $audit->audit_type?->getLabel() }}</strong></span>
            <span><span style="color: #9ca3af;">Inspector:</span> <strong style="color: #111827;">{{ $audit->inspector?->name ?? 'Unassigned' }}</strong></span>
            @if($audit->reviewer && $audit->reviewer_id !== $audit->inspector_id)
                <span><span style="color: #9ca3af;">Reviewer:</span> <strong style="color: #111827;">{{ $audit->reviewer->name }}</strong></span>
            @endif
        </div>
    </div>

    @if($actions)
        <div class="audit-header-actions-wrapper" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; max-width: 100%;">
            {{ $actions }}
        </div>
    @endif
</div>
