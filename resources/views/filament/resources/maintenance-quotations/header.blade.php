<div class="fi-header" style="display: flex; flex-direction: column; width: 100%; gap: 0.75rem; margin-bottom: 0.75rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; gap: 1rem; flex-wrap: wrap;">
        <div>
            @if ($breadcrumbs)
                <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" style="margin-bottom: 0.25rem;" />
            @endif

            @if (filled($heading))
                <h1 class="fi-header-heading" style="font-size: 1.65rem; font-weight: 800; line-height: 1.25; margin: 0; color: inherit; letter-spacing: -0.02em;">
                    {{ $heading }}
                </h1>
            @endif
        </div>

        @if ($actions)
            <div class="fi-header-actions-ctn" style="display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;">
                <x-filament::actions
                    :actions="$actions"
                    :alignment="$actionsAlignment"
                />
            </div>
        @endif
    </div>

    @if ($headerHtml)
        <div style="width: 100%; margin: 0;">
            {!! $headerHtml !!}
        </div>
    @endif
</div>
