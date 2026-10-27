@php
    $hasDraftPdf = $record?->hasMedia('draft_pdf') ?? false;
    $hasDraftWord = $record?->hasMedia('draft_word') ?? false;
    $hasSigned = ($record?->signed_by_tenant ?? false) && ($record?->hasMedia('signed_agreement') ?? false);
    $status = $record?->status ?? 'draft';
    $version = $record?->version ?? 1;

    $latestPdf = $record?->getLatestDraftPdf();
    $lastCompiledDate = $latestPdf?->created_at ? $latestPdf->created_at->format('M d, Y h:i A') : null;

    if ($status === 'active') {
        $statusLabel = 'Active Tenancy';
        $statusColor = 'success';
    } elseif ($hasSigned) {
        $statusLabel = 'Signed Copy Attached';
        $statusColor = 'purple';
    } elseif ($hasDraftPdf) {
        $statusLabel = 'Drafts Generated';
        $statusColor = 'info';
    } else {
        $statusLabel = 'Draft Not Generated';
        $statusColor = 'warning';
    }
@endphp

<div style="background-color: rgba(128, 128, 128, 0.04); border: 1px solid rgba(128, 128, 128, 0.2); border-radius: 0.75rem; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
    {{-- Header Row: Badges and Metadata --}}
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(128, 128, 128, 0.15);">
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <x-filament::badge :color="$statusColor" size="sm">
                {{ $statusLabel }}
            </x-filament::badge>

            @if ($record?->code)
                <x-filament::badge color="gray" size="sm">
                    #{{ $record->code }}
                </x-filament::badge>
            @endif

            @if ($hasDraftPdf)
                <x-filament::badge color="gray" size="sm">
                    Version v{{ $version }}
                </x-filament::badge>
            @endif

            @if ($record?->agreement_customized_at)
                <x-filament::badge color="warning" size="sm">
                    Customized {{ $record->agreement_customized_at->diffForHumans() }}
                </x-filament::badge>
            @endif
        </div>

        @if ($lastCompiledDate)
            <div style="font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 5px;">
                <x-filament::icon icon="heroicon-m-clock" style="width: 14px; height: 14px; color: #94a3b8;" />
                <span>Last compiled: <strong style="color: inherit;">{{ $lastCompiledDate }}</strong></span>
            </div>
        @endif
    </div>

    {{-- Contextual Guidance Banner --}}
    @if (! $hasDraftPdf)
        <div style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 8px; background-color: rgba(217, 119, 6, 0.08); border-left: 4px solid #d97706; color: #b45309; font-size: 13px; line-height: 1.5;">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" style="width: 18px; height: 18px; color: #d97706; flex-shrink: 0; margin-top: 1px;" />
            <div>
                <strong>Agreement Drafts Pending:</strong>
                <span>Click <strong>Generate Draft Documents</strong> below to compile the official Leave & License Agreement (PDF & Word .docx) with Annexures I, II, and III (inventory pulled automatically from Move-In Audit).</span>
            </div>
        </div>
    @elseif (! $hasSigned)
        <div style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 8px; background-color: rgba(37, 99, 235, 0.08); border-left: 4px solid #2563eb; color: #1e40af; font-size: 13px; line-height: 1.5;">
            <x-filament::icon icon="heroicon-m-document-check" style="width: 18px; height: 18px; color: #2563eb; flex-shrink: 0; margin-top: 1px;" />
            <div>
                <strong>Drafts Ready for Review:</strong>
                <span>Review or download the compiled agreement drafts below, or customize clauses and terms as needed. Once verified and executed with tenant signatures, upload the signed agreement below.</span>
            </div>
        </div>
    @else
        <div style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 8px; background-color: rgba(16, 185, 129, 0.08); border-left: 4px solid #10b981; color: #065f46; font-size: 13px; line-height: 1.5;">
            <x-filament::icon icon="heroicon-m-check-badge" style="width: 18px; height: 18px; color: #10b981; flex-shrink: 0; margin-top: 1px;" />
            <div>
                <strong>Agreement Executed & Signed:</strong>
                <span>The executed Leave & License agreement copy has been recorded. You can proceed to <strong>5. Key Handover & Activation</strong> to finalize onboarding.</span>
            </div>
        </div>
    @endif

    {{-- Action Bar with Native Filament Buttons --}}
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        {{-- Compile / Recompile Button --}}
        <x-filament::button 
            type="button" 
            wire:click="generateDraftDocuments" 
            wire:loading.attr="disabled"
            color="primary"
            icon="heroicon-m-bolt"
            size="sm">
            {{ $hasDraftPdf ? 'Recompile Draft Documents' : 'Generate Draft Documents' }}
        </x-filament::button>

        {{-- Customize / Edit Draft Clauses Button --}}
        <x-filament::button 
            type="button" 
            wire:click="mountAction('draftAgreement')"
            color="warning"
            icon="heroicon-m-pencil-square"
            size="sm">
            {{ ! empty($record?->agreement_content) ? 'Edit Agreement Clauses' : 'Customize Clauses & Terms' }}
        </x-filament::button>
    </div>
</div>
