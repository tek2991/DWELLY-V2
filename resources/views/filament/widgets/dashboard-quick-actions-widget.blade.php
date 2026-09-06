<x-filament-widgets::widget style="grid-column: 1 / -1;">
    <x-filament::section>
        <x-slot name="heading">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 1.75rem; height: 1.75rem; border-radius: 0.375rem; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center;">
                        <x-filament::icon icon="heroicon-m-bolt" style="width: 1rem; height: 1rem;" />
                    </div>
                    <span style="font-weight: 700; font-size: 0.9375rem; color: #0f172a;">Quick Launch & Fast Actions</span>
                </div>
                <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                    High-frequency daily workflows
                </span>
            </div>
        </x-slot>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.625rem; margin-top: 0.25rem;">
            @foreach ($this->getQuickActions() as $action)
                <a href="{{ $action['url'] }}" 
                   style="text-decoration: none; display: flex; align-items: center; gap: 0.625rem; padding: 0.625rem 0.75rem; border-radius: 0.5rem; background: #ffffff; border: 1px solid {{ $action['border_color'] }}; transition: all 0.15s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.02);"
                   onmouseover="this.style.borderColor='{{ $action['color'] }}'; this.style.backgroundColor='{{ $action['bg_light'] }}'; this.style.transform='translateY(-1px)';"
                   onmouseout="this.style.borderColor='{{ $action['border_color'] }}'; this.style.backgroundColor='#ffffff'; this.style.transform='translateY(0)';">
                    <div style="width: 2rem; height: 2rem; border-radius: 0.375rem; background: {{ $action['bg_light'] }}; color: {{ $action['color'] }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-filament::icon :icon="$action['icon']" style="width: 1.125rem; height: 1.125rem;" />
                    </div>
                    <div style="min-width: 0; flex: 1;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $action['label'] }}
                        </div>
                        <div style="font-size: 0.625rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $action['description'] }}
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
