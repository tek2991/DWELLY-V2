<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Mou\Enums\MouType;
use App\Domain\Mou\Models\Mou;
use App\Domain\Mou\Services\MouService;
use App\Domain\Mou\Services\MouTemplateService;
use App\Domain\Mou\Services\MouWorkflowService;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Services\IdentityResolutionService;
use App\Domain\Property\Services\PropertyOnboardingService;
use App\Filament\Resources\Operations\MOUResource\Pages;
use App\Filament\Resources\Operations\MOUResource\Schemas\MOUForm;
use App\Filament\Resources\Operations\MOUResource\Tables\MOUsTable;
use App\Filament\Resources\Properties\PropertyResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Tek2991\Accounting\Models\State;

class MOUResource extends Resource
{
    protected static ?string $model = Mou::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Directory & CRM';

    protected static ?string $navigationLabel = 'Owner MOUs';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Mou::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Mou::class) ?? false;
    }

    public static function canEdit(?Model $record = null): bool
    {
        if (! $record) {
            return auth()->user()?->can('create', Mou::class) ?? false;
        }

        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return MOUForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MOUsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMOUs::route('/'),
            'create' => Pages\CreateMOU::route('/create'),
            'view' => Pages\ViewMOU::route('/{record}'),
            'edit' => Pages\EditMOU::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getDraftAgreementAction(string $name = 'draftAgreement'): Action
    {
        return Action::make($name)
            ->label(fn (?Mou $record) => $record?->hasMedia('draft_pdf') ? 'Edit Draft' : 'Prepare Draft')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->size('sm')
            ->visible(function (?Mou $record, $livewire = null, ?string $operation = null): bool {
                if ($livewire instanceof \App\Filament\Resources\Operations\MOUResource\Pages\EditMOU || $operation === 'edit') {
                    return false;
                }

                return (bool) ($record && in_array($record->status, [
                    MouStatus::DRAFT,
                    MouStatus::PARTY_PENDING,
                    MouStatus::READY_TO_GENERATE,
                    MouStatus::PDF_GENERATED,
                    MouStatus::DOWNLOADED,
                    MouStatus::SIGNED_COPY_UPLOADED,
                ]) && static::canEdit($record));
            })
            ->slideOver()
            ->modalWidth('7xl')
            ->modalHeading(fn (?Mou $record) => "MOU Agreement Customizer — #{$record?->number}")
            ->modalDescription('Review and customize agreement clauses below. Form parameters (names, identification numbers, addresses, and bank details) are locked to maintain integrity with verified records.')
            ->fillForm(function (?Mou $record): array {
                if (! $record) {
                    return [];
                }

                $content = $record->agreement_content;
                if (blank($content)) {
                    $content = app(MouTemplateService::class)->getDefaultContent($record, true);
                } else {
                    $content = str_replace('<div style="text-align: center; margin-bottom: 25px;">', '', $content);
                    $content = str_replace('<div style="text-align: center; margin-bottom: 20px;">', '', $content);
                    $content = preg_replace('/<h1(?![^>]*text-align:\s*center)[^>]*>\s*Dwelly\s*<\/h1>/i', '<h1 style="text-align: center; font-size: 26px; margin: 0 0 6px 0; color: #1e293b;">Dwelly</h1>', $content);
                    $content = preg_replace('/<h2(?![^>]*text-align:\s*center)[^>]*>\s*Dwelly\s*<\/h2>/i', '<h2 style="text-align: center; font-size: 20px; margin: 0 0 5px 0;">Dwelly</h2>', $content);
                    $content = preg_replace('/<h3(?![^>]*text-align:\s*center)[^>]*>\s*Marketing &amp; Operational Agency Services Agreements?\s*<\/h3>/i', '<h3 style="text-align: center; font-size: 17px; margin: 0 0 6px 0; text-decoration: underline; color: #334155;">Marketing &amp; Operational Agency Services Agreement</h3>', $content);
                    $content = preg_replace('/<h3(?![^>]*text-align:\s*center)[^>]*>\s*Annexure I – Account Information\s*<\/h3>/i', '<h3 style="text-align: center; font-size: 16px; margin: 0 0 4px 0;">Annexure I – Account Information</h3>', $content);
                    $content = preg_replace('/<h4(?![^>]*text-align:\s*center)[^>]*>\s*RTGS \/ NEFT \/ E-Payment Mandate Form\s*<\/h4>/i', '<h4 style="text-align: center; font-size: 14px; margin: 4px 0 20px 0; color: #475569;">RTGS / NEFT / E-Payment Mandate Form</h4>', $content);
                    $content = preg_replace('/<p(?![^>]*text-align:\s*center)>\s*(Agreement Ref:.*?<\/p>)/i', '<p style="text-align: center; font-size: 12px; color: #64748b; margin-top: 4px; margin-bottom: 25px;">$1', $content);
                }

                return [
                    'agreement_content' => $content,
                ];
            })
            ->form([
                Placeholder::make('editor_guidance')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->content(fn (?Mou $record) => new HtmlString(
                        '<div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; margin-bottom: 6px;">'.
                        '<div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">'.
                        '<div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: #166534; line-height: 1.4;">'.
                        '<span style="font-size: 16px;">💡</span>'.
                        '<span><strong>Auto-Save Active:</strong> Changes are saved automatically as draft. Sensitive legal values are displayed as <span style="background: #f0f9ff; border: 1px solid #bae6fd; color: #0369a1; padding: 2px 7px; border-radius: 6px; font-weight: 500;">🔒 locked tokens: <strong style="font-weight: 700; color: #0c4a6e;">Value</strong></span> and cannot be modified inside the editor to protect verified records.</span>'.
                        '</div>'.
                        '<div x-data="{ status: \''.e($record?->agreement_customized_at ? '✓ Last customized: '.$record->agreement_customized_at->diffForHumans() : '✓ Auto-save active').'\', saving: false }" '.
                        '@mou-saving.window="saving = true" '.
                        '@mou-autosaved.window="saving = false; status = \'✓ Last customized: \' + ($event.detail?.timestamp || \'Just now\')" '.
                        'style="font-size: 12px; font-weight: 600; white-space: nowrap; display: flex; align-items: center; gap: 6px;" '.
                        ':style="{ color: saving ? \'#0284c7\' : \'#15803d\' }">'.
                        '<span x-show="saving" style="display: inline-flex; align-items: center; gap: 5px;">'.
                        '<svg style="width: 13px; height: 13px; animation: mou-spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">'.
                        '<circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>'.
                        '<path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>'.
                        '</svg>'.
                        '<span>Saving draft...</span>'.
                        '</span>'.
                        '<span x-show="!saving" x-text="status"></span>'.
                        '</div>'.
                        '</div>'.
                        '</div>'.
                        '<script>'.
                        '(function() {'.
                        '    function formatMouTokens() {'.
                        '        const root = document.querySelector(\'.fi-modal-window\') || document;'.
                        '        root.querySelectorAll(\'.mou-rich-editor-wrapper span[data-type="mergeTag"], .fi-fo-rich-editor-merge-tags-list span[data-type="mergeTag"], .mou-locked-param\').forEach(function(el) {'.
                        '            if (!el.dataset.mouFormatted && el.textContent.includes(\': \')) {'.
                        '                el.dataset.mouFormatted = \'true\';'.
                        '                const text = el.textContent.trim();'.
                        '                const colonIdx = text.indexOf(\': \');'.
                        '                if (colonIdx !== -1) {'.
                        '                    let label = text.substring(0, colonIdx).trim();'.
                        '                    let val = text.substring(colonIdx + 2).trim();'.
                        '                    if (!label.startsWith(\'🔒\')) {'.
                        '                        label = \'🔒 \' + label;'.
                        '                    }'.
                        '                    el.innerHTML = label + \': <strong style="font-weight: 700; color: #0c4a6e;">\' + val + \'</strong>\';'.
                        '                }'.
                        '            }'.
                        '        });'.
                        '        const pm = document.querySelector(\'.mou-rich-editor-wrapper .ProseMirror\');'.
                        '        if (pm && !pm.dataset.mouBlurBound) {'.
                        '            pm.dataset.mouBlurBound = \'true\';'.
                        '            pm.addEventListener(\'blur\', function() {'.
                        '                window.dispatchEvent(new CustomEvent(\'mou-saving\'));'.
                        '            });'.
                        '        }'.
                        '    }'.
                        '    formatMouTokens();'.
                        '    if (!window._mouScriptInitialized) {'.
                        '        window._mouScriptInitialized = true;'.
                        '        const obs = new MutationObserver(function() { formatMouTokens(); });'.
                        '        obs.observe(document.body, { childList: true, subtree: true });'.
                        '        window.addEventListener(\'keydown\', function(e) {'.
                        '            if (e.key === \'Escape\') {'.
                        '                const closeBtn = document.querySelector(\'.mou-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn button\');'.
                        '                if (closeBtn) { closeBtn.click(); }'.
                        '            }'.
                        '        });'.
                        '        if (window.Livewire) {'.
                        '            Livewire.hook(\'request\', function(context) {'.
                        '                if (context && context.succeed) {'.
                        '                    context.succeed(function() {'.
                        '                        window.dispatchEvent(new CustomEvent(\'mou-autosaved\', { detail: { timestamp: \'Just now\' } }));'.
                        '                    });'.
                        '                }'.
                        '            });'.
                        '        }'.
                        '    }'.
                        '})();'.
                        '</script>'.
                        '<style>'.
                        '@keyframes mou-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }'.
                        '.mou-rich-editor-wrapper { border: 1px solid #cbd5e1 !important; border-radius: 10px !important; background: #ffffff !important; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.05) !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper { border-color: #334155 !important; background: #0f172a !important; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.3) !important; }'.
                        '/* Sticky Toolbar at top of editor */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-toolbar { position: sticky !important; top: 0 !important; z-index: 30 !important; background: #ffffff !important; border-bottom: 1px solid #e2e8f0 !important; border-top-left-radius: 9px !important; border-top-right-radius: 9px !important; padding: 6px 10px !important; box-shadow: 0 2px 4px -1px rgba(0,0,0,0.05) !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-toolbar { background: #1e293b !important; border-color: #334155 !important; box-shadow: 0 2px 4px -1px rgba(0,0,0,0.4) !important; }'.
                        '/* Main Editor Container */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-main { position: relative !important; background: #ffffff !important; border-bottom-left-radius: 9px !important; border-bottom-right-radius: 9px !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-main { background: #0f172a !important; }'.
                        '/* Scrollable Text Content Area */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-content { height: calc(100vh - 300px) !important; min-height: 480px !important; max-height: calc(100vh - 260px) !important; overflow-y: auto !important; padding: 24px 32px !important; scroll-behavior: smooth !important; background: #ffffff !important; border-bottom-left-radius: 9px !important; border-bottom-right-radius: 9px !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-content { background: #0f172a !important; color: #f1f5f9 !important; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-content .ProseMirror { min-height: 100% !important; outline: none !important; }'.
                        '/* Custom Scrollbar for Text Content */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-content::-webkit-scrollbar { width: 8px; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-content::-webkit-scrollbar-track { background: #f8fafc; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-content::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-content::-webkit-scrollbar-thumb:hover { background: #94a3b8; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-content::-webkit-scrollbar-track { background: #0f172a; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-content::-webkit-scrollbar-thumb { background: #334155; }'.
                        '/* Suppress default TipTap {{ }} braces on locked tokens */'.
                        '.mou-rich-editor-wrapper span[data-type="mergeTag"]::before, .mou-rich-editor-wrapper span[data-type="mergeTag"]::after { display: none !important; content: "" !important; }'.
                        '/* Badges inside Editor Document: Inline pill matching banner representation */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-content span[data-type="mergeTag"], .mou-locked-param { display: inline-block !important; vertical-align: -2px !important; white-space: nowrap !important; background-color: #f0f9ff !important; border: 1px solid #bae6fd !important; color: #0369a1 !important; border-radius: 6px !important; padding: 2px 7px !important; margin: 0 2px !important; font-size: 13px !important; line-height: normal !important; box-sizing: border-box !important; user-select: all !important; cursor: default !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-content span[data-type="mergeTag"], :is(.dark) .mou-locked-param { background-color: #0c2d48 !important; border-color: #0284c7 !important; color: #bae6fd !important; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-content span[data-type="mergeTag"] strong, .mou-locked-param strong { font-weight: 700 !important; color: #0c4a6e !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-content span[data-type="mergeTag"] strong, :is(.dark) .mou-locked-param strong { color: #38bdf8 !important; }'.
                        '/* Floating Popover Container for Locked Fields */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panels { position: absolute !important; top: 8px !important; right: 12px !important; width: 520px !important; max-width: calc(100% - 24px) !important; max-height: min(480px, calc(100vh - 320px)) !important; overflow-x: hidden !important; overflow-y: auto !important; z-index: 50 !important; border-radius: 12px !important; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.18), 0 10px 10px -5px rgba(0,0,0,0.06) !important; border: 1px solid #cbd5e1 !important; background: #ffffff !important; box-sizing: border-box !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-panels { background: #1e293b !important; border-color: #475569 !important; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.6) !important; }'.
                        '/* Crucial: Ensure Alpine x-show display: none is strictly respected so panel can close */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panels[style*="display: none"], .mou-rich-editor-wrapper .fi-fo-rich-editor-panels[style*="display:none"], .mou-rich-editor-wrapper .fi-fo-rich-editor-panels[x-cloak], .mou-rich-editor-wrapper .fi-fo-rich-editor-panel[style*="display: none"], .mou-rich-editor-wrapper .fi-fo-rich-editor-panel[style*="display:none"], .mou-rich-editor-wrapper .fi-fo-rich-editor-panel[x-cloak] { display: none !important; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panel { width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; }'.
                        '/* Fixed Popup Header with Docked Close Button */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panel-header { position: sticky !important; top: 0 !important; z-index: 10 !important; display: flex !important; align-items: center !important; justify-content: space-between !important; padding: 12px 16px !important; border-bottom: 1px solid #e2e8f0 !important; background: #f8fafc !important; flex-shrink: 0 !important; width: 100% !important; box-sizing: border-box !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-panel-header { background: #1e293b !important; border-color: #334155 !important; }'.
                        '/* Rename popup title from "Merge tags" to "Insert Locked Field" (scoped strictly to mergeTags panel) */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panel[x-show*="mergeTags"] .fi-fo-rich-editor-panel-heading { font-size: 0 !important; line-height: 0 !important; margin: 0 !important; color: transparent !important; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panel[x-show*="mergeTags"] .fi-fo-rich-editor-panel-heading::after { content: "Insert Locked Field" !important; font-size: 14px !important; font-weight: 600 !important; line-height: 1.4 !important; color: #0f172a !important; letter-spacing: -0.01em !important; display: inline-block !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-panel[x-show*="mergeTags"] .fi-fo-rich-editor-panel-heading::after { color: #f8fafc !important; }'.
                        '/* Always-visible Close Button */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn { margin-left: auto !important; flex-shrink: 0 !important; display: flex !important; align-items: center !important; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn button { display: flex !important; align-items: center !important; justify-content: center !important; width: 28px !important; height: 28px !important; border-radius: 6px !important; color: #64748b !important; border: none !important; background: transparent !important; transition: all 0.15s ease !important; cursor: pointer !important; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn button:hover { background-color: #e2e8f0 !important; color: #0f172a !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn button:hover { background-color: #334155 !important; color: #f8fafc !important; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn button svg { width: 18px !important; height: 18px !important; }'.
                        '/* Scrollable List for Locked Fields without horizontal overflow */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-merge-tags-list { display: flex !important; flex-direction: column !important; gap: 8px !important; padding: 12px 14px !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panels::-webkit-scrollbar { width: 6px !important; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-panels::-webkit-scrollbar-thumb { background: #cbd5e1 !important; border-radius: 3px !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-panels::-webkit-scrollbar-thumb { background: #475569 !important; }'.
                        '/* List Item Button */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-merge-tag-btn { display: block !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; padding: 0 !important; border: none !important; background: transparent !important; cursor: pointer !important; text-align: left !important; }'.
                        '/* Badge inside List Item: Multi-line wrapping for long values (Address, etc.) */'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-merge-tags-list span[data-type="mergeTag"] { display: block !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; white-space: normal !important; word-break: break-word !important; overflow-wrap: break-word !important; background-color: #f0f9ff !important; border: 1px solid #bae6fd !important; color: #0369a1 !important; border-radius: 8px !important; padding: 8px 12px !important; font-size: 13px !important; line-height: 1.45 !important; cursor: pointer !important; transition: all 0.15s ease-in-out !important; box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-merge-tag-btn:hover span[data-type="mergeTag"] { background-color: #e0f2fe !important; border-color: #7dd3fc !important; box-shadow: 0 2px 5px rgba(3, 105, 161, 0.15) !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-merge-tags-list span[data-type="mergeTag"] { background-color: #0c2d48 !important; border-color: #0284c7 !important; color: #bae6fd !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-merge-tag-btn:hover span[data-type="mergeTag"] { background-color: #0c385a !important; border-color: #38bdf8 !important; }'.
                        '.mou-rich-editor-wrapper .fi-fo-rich-editor-merge-tags-list span[data-type="mergeTag"] strong { font-weight: 700 !important; color: #0c4a6e !important; word-break: break-word !important; }'.
                        ':is(.dark) .mou-rich-editor-wrapper .fi-fo-rich-editor-merge-tags-list span[data-type="mergeTag"] strong { color: #38bdf8 !important; }'.
                        '</style>'
                    )),

                RichEditor::make('agreement_content')
                    ->hiddenLabel()
                    ->extraAttributes([
                        'class' => 'mou-rich-editor-wrapper',
                    ])
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike'],
                        ['h1', 'h2', 'h3'],
                        ['alignStart', 'alignCenter', 'alignEnd'],
                        ['bulletList', 'orderedList', 'table'],
                        ['mergeTags'],
                        ['undo', 'redo'],
                    ])
                    ->tools([
                        \Filament\Forms\Components\RichEditor\RichEditorTool::make('mergeTags')
                            ->label('Insert Locked Field')
                            ->icon('heroicon-o-lock-closed')
                            ->jsHandler('togglePanel(\'mergeTags\')')
                            ->activeJsExpression('isPanelActive(\'mergeTags\')'),
                    ])
                    ->mergeTags(fn (?Mou $record) => app(MouTemplateService::class)->getMergeTags($record))
                    ->noMergeTagSearchResultsMessage('No locked legal fields found matching your search.')
                    ->extraInputAttributes([
                        'style' => 'font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6;',
                    ])
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, ?Mou $record, $livewire) {
                        if ($record && ! blank($state)) {
                            $record->update([
                                'agreement_content' => $state,
                                'agreement_customized_at' => now(),
                            ]);
                            if ($livewire && method_exists($livewire, 'dispatch')) {
                                $livewire->dispatch('mou-autosaved', timestamp: 'Just now');
                            }
                        }
                    })
                    ->columnSpanFull(),
            ])
            ->modalSubmitActionLabel(fn (?Mou $record) => $record?->hasMedia('draft_pdf') ? 'Recompile & Generate PDF' : 'Compile & Generate PDF')
            ->modalSubmitAction(fn (Action $action) => $action->icon('heroicon-o-document-arrow-down')->color('success'))
            ->action(function (array $data, Mou $record) {
                try {
                    $record->update([
                        'agreement_content' => $data['agreement_content'] ?? null,
                        'agreement_customized_at' => now(),
                    ]);

                    app(MouWorkflowService::class)->generatePdf($record);
                    $record->refresh();

                    Notification::make()
                        ->title('PDF Generated Successfully')
                        ->body("Agreement PDF (version {$record->version}) has been compiled and saved.")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Cannot Generate PDF')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->extraModalFooterActions([
                Action::make('saveDraft')
                    ->label('Save Draft')
                    ->icon('heroicon-o-bookmark')
                    ->color('gray')
                    ->action(function (array $data, Mou $record, Action $action) {
                        $record->update([
                            'agreement_content' => $data['agreement_content'] ?? null,
                            'agreement_customized_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Draft Saved')
                            ->body('Agreement custom text has been saved.')
                            ->success()
                            ->send();

                        $action->halt();
                    }),

                Action::make('resetToStandardTemplate')
                    ->label('Reset to Standard Template')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reset to Standard Template?')
                    ->modalDescription('Are you sure you want to discard your custom edits? The document will be reset to Dwelly’s standard agreement template using current form values.')
                    ->action(function (Mou $record, Set $set, Action $action) {
                        $record->update([
                            'agreement_content' => null,
                            'agreement_customized_at' => null,
                        ]);

                        $default = app(MouTemplateService::class)->getDefaultContent($record, true);
                        $set('agreement_content', $default);

                        Notification::make()
                            ->title('Template Reset')
                            ->body('The agreement has been reset to the standard template.')
                            ->info()
                            ->send();
                    }),
            ]);
    }

    public static function getGeneratePdfAction(string $name = 'generatePdf'): Action
    {
        return Action::make($name)
            ->label(fn (?Mou $record) => $record?->hasMedia('draft_pdf') ? 'Quick Recompile PDF' : 'Quick Generate PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->size('sm')
            ->tooltip('Directly compile the PDF without opening the text editor')
            ->visible(function (?Mou $record, $livewire = null, ?string $operation = null): bool {
                if ($livewire instanceof \App\Filament\Resources\Operations\MOUResource\Pages\EditMOU || $operation === 'edit') {
                    return false;
                }

                return (bool) ($record && in_array($record->status, [
                    MouStatus::DRAFT,
                    MouStatus::PARTY_PENDING,
                    MouStatus::READY_TO_GENERATE,
                    MouStatus::PDF_GENERATED,
                    MouStatus::DOWNLOADED,
                    MouStatus::SIGNED_COPY_UPLOADED,
                ]) && static::canEdit($record));
            })
            ->requiresConfirmation(fn (?Mou $record) => (bool) $record?->hasMedia('draft_pdf'))
            ->modalHeading(fn (?Mou $record) => $record?->hasMedia('draft_pdf') ? 'Regenerate Draft PDF' : 'Generate Draft PDF')
            ->modalDescription(fn (?Mou $record) => $record?->hasMedia('signed_pdf')
                ? 'Are you sure you want to regenerate the draft PDF? The currently uploaded signed PDF will be archived, and the MOU status will revert to "PDF Generated".'
                : 'Are you sure you want to generate a new draft PDF? This will increment the document version.')
            ->action(function (Mou $record) {
                try {
                    app(MouWorkflowService::class)->generatePdf($record);
                    $record->refresh();
                    Notification::make()->title('PDF Generated')->success()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('Cannot Generate PDF')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getUploadSignedCopyAction(string $name = 'uploadSignedCopy'): Action
    {
        return Action::make($name)
            ->label('Upload Signed')
            ->icon('heroicon-o-document-arrow-up')
            ->color('info')
            ->size('sm')
            ->visible(function (?Mou $record, $livewire = null, ?string $operation = null): bool {
                if ($livewire instanceof \App\Filament\Resources\Operations\MOUResource\Pages\EditMOU || $operation === 'edit') {
                    return false;
                }

                return (bool) ($record && in_array($record->status, [
                    MouStatus::PDF_GENERATED,
                    MouStatus::DOWNLOADED,
                    MouStatus::SIGNED_COPY_UPLOADED,
                ]) && static::canEdit($record));
            })
            ->form([
                Forms\Components\FileUpload::make('signed_pdf')
                    ->label('Signed PDF File')
                    ->directory('temp-signed-pdfs')
                    ->acceptedFileTypes(['application/pdf'])
                    ->required(),
            ])
            ->action(function (Mou $record, array $data) {
                app(MouWorkflowService::class)->uploadSignedCopy($record, $data['signed_pdf']);
                $record->refresh();
                Notification::make()->title('Signed Copy Uploaded')->success()->send();
            });
    }

    public static function getResolvePartyAction(string $name = 'resolveParty'): Action
    {
        return Action::make($name)
            ->label('Resolve Party')
            ->icon('heroicon-o-users')
            ->color('primary')
            ->visible(fn (?Mou $record) => $record && ! $record->party_id && static::canEdit($record))
            ->fillForm(function (Mou $record): array {
                $resolvedPan = $record->getResolvedPanNumber();
                $existingParty = $resolvedPan ? app(IdentityResolutionService::class)->findPartyByPan($resolvedPan) : null;

                if ($existingParty) {
                    return [
                        'action_type' => 'select_existing',
                        'existing_party_id' => $existingParty->id,
                        'party_type' => $existingParty->party_type ?? 'individual',
                        'pan_number' => $resolvedPan,
                        'aadhar_number' => $record->getResolvedAadhaarNumber(),
                        'voter_id' => $record->getResolvedVoterId(),
                    ];
                }

                return [
                    'action_type' => 'create_new',
                    'party_type' => 'individual',
                    'name' => $record->opportunity?->owner_name ?? ($record->owner_details['name'] ?? ''),
                    'phone' => $record->opportunity?->owner_phone ?? ($record->owner_details['phone'] ?? ''),
                    'email' => $record->opportunity?->owner_email ?? ($record->owner_details['email'] ?? ''),
                    'address' => $record->opportunity?->address ?? ($record->owner_details['address'] ?? ''),
                    'pan_number' => $resolvedPan ?? ($record->owner_details['pan_number'] ?? ''),
                    'aadhar_number' => $record->getResolvedAadhaarNumber() ?? ($record->owner_details['aadhar_number'] ?? ''),
                    'voter_id' => $record->getResolvedVoterId() ?? ($record->owner_details['voter_id'] ?? ''),
                ];
            })
            ->form(static::getResolvePartyFormSchema())
            ->action(function (Mou $record, array $data) {
                app(MouService::class)->resolveParty($record, $data);
                $record->refresh();
                Notification::make()->title('Party Resolved')->success()->send();
            });
    }

    public static function getUpdatePartyAction(string $name = 'updateParty'): Action
    {
        return Action::make($name)
            ->label('Update Party Details')
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->visible(fn (?Mou $record) => $record && $record->party_id && static::canEdit($record))
            ->fillForm(function (Mou $record): array {
                $party = $record->party;
                if (! $party) {
                    return [
                        'action_type' => 'create_new',
                        'party_type' => 'individual',
                        'name' => $record->opportunity?->owner_name ?? ($record->owner_details['name'] ?? ''),
                        'phone' => $record->opportunity?->owner_phone ?? ($record->owner_details['phone'] ?? ''),
                        'email' => $record->opportunity?->owner_email ?? ($record->owner_details['email'] ?? ''),
                        'address' => $record->opportunity?->address ?? ($record->owner_details['address'] ?? ''),
                    ];
                }

                $party->loadMissing(['individual', 'organization', 'addresses']);
                $primaryAddress = $party->addresses->where('is_primary', true)->first() ?? $party->addresses->first();

                return [
                    'action_type' => 'update_current',
                    'existing_party_id' => $party->id,
                    'party_type' => $party->party_type ?? 'individual',
                    'name' => $party->individual?->name ?? $party->display_name ?? ($record->owner_details['name'] ?? ''),
                    'parent_name' => $party->individual?->parent_name ?? ($record->owner_details['parent_name'] ?? ''),
                    'date_of_birth' => $party->individual?->date_of_birth,
                    'gender' => $party->individual?->gender,
                    'pan_number' => $party->individual?->pan_number ?? $party->organization?->pan ?? $record->getResolvedPanNumber() ?? ($record->owner_details['pan_number'] ?? ''),
                    'aadhar_number' => $party->individual?->aadhaar_number ?? $record->getResolvedAadhaarNumber() ?? ($record->owner_details['aadhar_number'] ?? ''),
                    'voter_id' => $party->individual?->voter_id ?? $record->getResolvedVoterId() ?? ($record->owner_details['voter_id'] ?? ''),
                    'legal_name' => $party->organization?->legal_name ?? $party->display_name ?? ($record->owner_details['name'] ?? ''),
                    'contact_person_name' => $party->organization?->contact_person_name ?? ($record->owner_details['contact_person_name'] ?? ''),
                    'contact_person_phone' => $party->organization?->contact_person_phone ?? ($record->owner_details['contact_person_phone'] ?? ''),
                    'gst_number' => $party->organization?->gstin ?? ($record->owner_details['gstin'] ?? ''),
                    'phone' => $party->phone ?? ($record->owner_details['phone'] ?? ''),
                    'email' => $party->email ?? ($record->owner_details['email'] ?? ''),
                    'state_id' => $party->state_id,
                    'address' => $primaryAddress?->address_line_1 ?? ($record->owner_details['address'] ?? ''),
                ];
            })
            ->form(static::getUpdatePartyFormSchema())
            ->action(function (Mou $record, array $data) {
                app(MouService::class)->updatePartyDetails($record, $data);
                $record->refresh();
                Notification::make()->title('Party Details Updated')->success()->send();
            });
    }

    public static function getVerifyAction(string $name = 'verify'): Action
    {
        return Action::make($name)
            ->label('Verify Agreement')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (?Mou $record) => $record?->status === MouStatus::SIGNED_COPY_UPLOADED && (auth()->user()?->can('verify', $record) ?? false))
            ->requiresConfirmation()
            ->modalHeading('Verify & Legally Approve MOU')
            ->modalDescription('Confirming verification will lock all legal terms and unlock property conversion.')
            ->modalSubmitActionLabel('Yes, Verify Agreement')
            ->action(function (Mou $record) {
                app(MouWorkflowService::class)->verify($record);
                $record->refresh();
                Notification::make()->title('Agreement Verified')->success()->send();
            });
    }

    public static function getConvertToPropertyAction(string $name = 'convertToProperty'): Action
    {
        return Action::make($name)
            ->label('Convert to Property')
            ->icon('heroicon-o-building-office')
            ->color('success')
            ->visible(fn (?Mou $record) => $record?->status === MouStatus::VERIFIED && ($record?->type === MouType::ONBOARDING || $record?->type === null) && (auth()->user()?->can('convert', $record) ?? false))
            ->requiresConfirmation()
            ->modalHeading('Convert Verified MOU into Active Property')
            ->modalDescription('This will create an official property record, establish unit structures, and transition onboarding workflow.')
            ->modalSubmitActionLabel('Yes, Convert to Property')
            ->action(function (?Mou $record = null) {
                $property = app(PropertyOnboardingService::class)->createPropertyFromMou($record);
                app(MouWorkflowService::class)->convert($record);

                Notification::make()->title('Property Created')->success()->send();

                return redirect(PropertyResource::getUrl('edit', ['record' => $property]));
            });
    }

    public static function getArchiveAction(string $name = 'archive'): Action
    {
        return Action::make($name)
            ->label('Archive')
            ->icon('heroicon-o-archive-box')
            ->color('danger')
            ->visible(fn (?Mou $record) => $record && $record->verified_at === null && ! in_array($record->status, [
                MouStatus::VERIFIED,
                MouStatus::CONVERTED,
                MouStatus::COMPLETED,
                MouStatus::CANCELLED,
            ]) && (auth()->user()?->can('archive', $record) ?? false))
            ->requiresConfirmation()
            ->modalHeading('Archive MOU')
            ->modalDescription('Are you sure you want to archive this MOU? The corresponding opportunity will also be marked as Closed Lost.')
            ->modalSubmitActionLabel('Archive')
            ->action(function (Mou $record, $livewire = null) {
                try {
                    app(MouWorkflowService::class)->archive($record);
                    Notification::make()
                        ->title('MOU Archived')
                        ->body('The MOU has been archived and the opportunity marked as Closed Lost.')
                        ->success()
                        ->send();
                    if ($livewire && method_exists($livewire, 'redirect')) {
                        $livewire->redirect(static::getUrl('index'));
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Cannot Archive MOU')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function getProvisionAccountingAction(string $name = 'provisionAccounting'): Action
    {
        return Action::make($name)
            ->label('Provision Accounting')
            ->icon('heroicon-o-banknotes')
            ->color('primary')
            ->visible(fn (?Mou $record) => $record && $record->party_id && empty($record->bank_details) && $record->status === MouStatus::DRAFT && static::canEdit($record))
            ->form([
                Forms\Components\TextInput::make('bank_name')->required(),
                Forms\Components\TextInput::make('account_holder_name')->required(),
                Forms\Components\TextInput::make('account_number')->required(),
                Forms\Components\Select::make('account_type')
                    ->label('Account Type')
                    ->options([
                        'Saving' => 'Savings Account',
                        'Current' => 'Current Account',
                    ])
                    ->default('Current')
                    ->required(),
                Forms\Components\TextInput::make('ifsc_code')->required(),
                Forms\Components\Textarea::make('bank_address')->label('Address of the Bank')->required()->columnSpanFull(),
            ])
            ->action(function (Mou $record, array $data) {
                app(MouService::class)->provisionAccounting($record, $data);
                $record->refresh();
                Notification::make()->title('Accounting Provisioned')->success()->send();
            });
    }

    public static function getResolvePartyFormSchema(): array
    {
        return [
            Placeholder::make('pan_match_notice')
                ->hiddenLabel()
                ->columnSpanFull()
                ->visible(function (?Mou $record) {
                    if (! $record) {
                        return false;
                    }
                    $pan = $record->getResolvedPanNumber();

                    return $pan && app(IdentityResolutionService::class)->findPartyByPan($pan);
                })
                ->content(function (?Mou $record) {
                    $pan = $record?->getResolvedPanNumber();
                    $party = $pan ? app(IdentityResolutionService::class)->findPartyByPan($pan) : null;
                    if (! $party) {
                        return '';
                    }

                    return new HtmlString(
                        '<div style="background-color: rgba(30, 58, 138, 0.06); border: 1px solid rgba(37, 99, 235, 0.25); border-left: 4px solid #2563eb; padding: 12px 16px; border-radius: 8px; margin-bottom: 8px; font-size: 13px; color: #1e3a8a; display: flex; align-items: flex-start; gap: 10px;">'.
                        '<span style="font-size: 18px; line-height: 1;">🔍</span>'.
                        '<div>'.
                        '<strong style="font-size: 14px; display: block; margin-bottom: 2px;">Existing Party Matched by PAN</strong>'.
                        '<span>Found an existing party <strong>'.e($party->display_name).'</strong> matching PAN <strong>'.e($pan).'</strong> from the saved MOU KYC documents. The party has been automatically pre-selected below.</span>'.
                        '</div>'.
                        '</div>'
                    );
                }),

            Forms\Components\Radio::make('action_type')
                ->label('Action')
                ->options([
                    'select_existing' => 'Select Existing Party',
                    'create_new' => 'Create New Party',
                ])
                ->default('select_existing')
                ->live()
                ->required(),

            Forms\Components\Select::make('existing_party_id')
                ->label('Existing Party')
                ->options(function () {
                    return Party::all()->mapWithKeys(function ($party) {
                        $phone = $party->phone ? " ({$party->phone})" : '';

                        return [$party->id => $party->display_name.$phone];
                    });
                })
                ->searchable()
                ->visible(fn (Get $get) => $get('action_type') === 'select_existing')
                ->required(fn (Get $get) => $get('action_type') === 'select_existing'),

            Forms\Components\Radio::make('party_type')
                ->label('Entity Type')
                ->options([
                    'individual' => 'Individual',
                    'organization' => 'Company',
                ])
                ->visible(fn (Get $get) => $get('action_type') === 'create_new')
                ->required(fn (Get $get) => $get('action_type') === 'create_new')
                ->live(),

            // --- INDIVIDUAL FIELDS ---
            Forms\Components\TextInput::make('name')
                ->label('Full Name')
                ->default(fn (?Mou $record) => $record?->opportunity?->owner_name ?? ($record?->owner_details['name'] ?? null))
                ->visible(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual')
                ->required(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
            Forms\Components\TextInput::make('parent_name')
                ->label('S/o or D/o (Parent/Guardian Name)')
                ->visible(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual')
                ->required(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
            Forms\Components\DatePicker::make('date_of_birth')
                ->label('Date of Birth')
                ->visible(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
            Forms\Components\Select::make('gender')
                ->label('Gender')
                ->options([
                    'male' => 'Male',
                    'female' => 'Female',
                    'other' => 'Other',
                ])
                ->visible(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),

            // --- ORGANIZATION FIELDS ---
            Forms\Components\TextInput::make('legal_name')
                ->label('Company Legal Name')
                ->default(fn (?Mou $record) => $record?->opportunity?->owner_name ?? ($record?->owner_details['name'] ?? null))
                ->visible(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization')
                ->required(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('contact_person_name')
                ->label('Contact Person Name')
                ->visible(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization')
                ->required(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('contact_person_phone')
                ->label('Contact Person Phone')
                ->visible(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization')
                ->required(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),

            // --- COMMON FIELDS ---
            Forms\Components\TextInput::make('pan_number')
                ->label('PAN Number')
                ->default(fn (?Mou $record) => $record?->getResolvedPanNumber() ?? ($record?->owner_details['pan_number'] ?? null))
                ->readOnly(fn (?Mou $record) => (bool) $record?->getResolvedPanNumber())
                ->dehydrated(true)
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null)
                ->regex('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i')
                ->validationMessages(['regex' => 'Please enter a valid 10-character PAN (e.g. ABCDE1234F).'])
                ->helperText(fn (?Mou $record) => $record?->getResolvedPanNumber() ? 'Pre-filled and locked from saved MOU KYC documents.' : 'Enter 10-character PAN (e.g. ABCDE1234F)')
                ->visible(fn (Get $get) => $get('action_type') === 'create_new')
                ->required(fn (Get $get) => $get('action_type') === 'create_new'),
            Forms\Components\TextInput::make('gst_number')
                ->label('GST Number')
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null)
                ->regex('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i')
                ->validationMessages(['regex' => 'Please enter a valid 15-character GSTIN (e.g. 29ABCDE1234F1Z5).'])
                ->visible(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('aadhar_number')
                ->label('Aadhaar Number')
                ->default(fn (?Mou $record) => $record?->getResolvedAadhaarNumber() ?? ($record?->owner_details['aadhar_number'] ?? null))
                ->readOnly(fn (?Mou $record) => (bool) $record?->getResolvedAadhaarNumber())
                ->dehydrated(true)
                ->helperText(fn (?Mou $record) => $record?->getResolvedAadhaarNumber() ? 'Pre-filled and locked from saved MOU KYC documents.' : null)
                ->visible(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual')
                ->required(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
            Forms\Components\TextInput::make('voter_id')
                ->label('Voter ID')
                ->default(fn (?Mou $record) => $record?->getResolvedVoterId() ?? ($record?->owner_details['voter_id'] ?? null))
                ->visible(fn (Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
            Forms\Components\TextInput::make('phone')
                ->label('Phone Number')
                ->default(fn (?Mou $record) => $record?->opportunity?->owner_phone ?? ($record?->owner_details['phone'] ?? null))
                ->visible(fn (Get $get) => $get('action_type') === 'create_new')
                ->required(fn (Get $get) => $get('action_type') === 'create_new'),
            Forms\Components\TextInput::make('email')
                ->label('Email Address')
                ->default(fn (?Mou $record) => $record?->opportunity?->owner_email ?? ($record?->owner_details['email'] ?? null))
                ->visible(fn (Get $get) => $get('action_type') === 'create_new')
                ->required(fn (Get $get) => $get('action_type') === 'create_new'),
            Forms\Components\Select::make('state_id')
                ->label('State (Accounting)')
                ->options(fn () => State::pluck('name', 'id'))
                ->searchable()
                ->visible(fn (Get $get) => $get('action_type') === 'create_new')
                ->required(fn (Get $get) => $get('action_type') === 'create_new'),
            Forms\Components\Textarea::make('address')
                ->label(fn (Get $get) => $get('party_type') === 'organization' ? 'Registered Address' : 'Personal Address')
                ->default(fn (?Mou $record) => $record?->opportunity?->address ?? ($record?->owner_details['address'] ?? null))
                ->visible(fn (Get $get) => $get('action_type') === 'create_new')
                ->required(fn (Get $get) => $get('action_type') === 'create_new')
                ->columnSpanFull(),
        ];
    }

    public static function getUpdatePartyFormSchema(): array
    {
        return [
            Forms\Components\Radio::make('action_type')
                ->label('Action')
                ->options(function (?Mou $record) {
                    $partyName = $record?->party?->display_name ?? 'Resolved Party';

                    return [
                        'update_current' => "Update Current Party ({$partyName})",
                        'select_existing' => 'Switch to Different Existing Party',
                        'create_new' => 'Create & Switch to New Party',
                    ];
                })
                ->default('update_current')
                ->live()
                ->required(),

            Forms\Components\Select::make('existing_party_id')
                ->label('Select Existing Party')
                ->options(function () {
                    return Party::all()->mapWithKeys(function ($party) {
                        $phone = $party->phone ? " ({$party->phone})" : '';

                        return [$party->id => $party->display_name.$phone];
                    });
                })
                ->searchable()
                ->visible(fn (Get $get) => $get('action_type') === 'select_existing')
                ->required(fn (Get $get) => $get('action_type') === 'select_existing'),

            Forms\Components\Radio::make('party_type')
                ->label('Entity Type')
                ->options([
                    'individual' => 'Individual',
                    'organization' => 'Company',
                ])
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->live(),

            // --- INDIVIDUAL FIELDS ---
            Forms\Components\TextInput::make('name')
                ->label('Full Name')
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual')
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),
            Forms\Components\TextInput::make('parent_name')
                ->label('S/o or D/o (Parent/Guardian Name)')
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual')
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),
            Forms\Components\DatePicker::make('date_of_birth')
                ->label('Date of Birth')
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),
            Forms\Components\Select::make('gender')
                ->label('Gender')
                ->options([
                    'male' => 'Male',
                    'female' => 'Female',
                    'other' => 'Other',
                ])
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),

            // --- ORGANIZATION FIELDS ---
            Forms\Components\TextInput::make('legal_name')
                ->label('Company Legal Name')
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization')
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('contact_person_name')
                ->label('Contact Person Name')
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization')
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('contact_person_phone')
                ->label('Contact Person Phone')
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization')
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization'),

            // --- COMMON FIELDS ---
            Forms\Components\TextInput::make('pan_number')
                ->label('PAN Number')
                ->readOnly(fn (?Mou $record) => (bool) $record?->getResolvedPanNumber())
                ->dehydrated(true)
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null)
                ->regex('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i')
                ->validationMessages(['regex' => 'Please enter a valid 10-character PAN (e.g. ABCDE1234F).'])
                ->helperText(fn (?Mou $record) => $record?->getResolvedPanNumber() ? 'Pre-filled and locked from saved MOU KYC documents.' : 'Enter 10-character PAN (e.g. ABCDE1234F)')
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new'])),
            Forms\Components\TextInput::make('gst_number')
                ->label('GST Number')
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null)
                ->regex('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i')
                ->validationMessages(['regex' => 'Please enter a valid 15-character GSTIN (e.g. 29ABCDE1234F1Z5).'])
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('aadhar_number')
                ->label('Aadhaar Number')
                ->readOnly(fn (?Mou $record) => (bool) $record?->getResolvedAadhaarNumber())
                ->dehydrated(true)
                ->helperText(fn (?Mou $record) => $record?->getResolvedAadhaarNumber() ? 'Pre-filled and locked from saved MOU KYC documents.' : null)
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual')
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),
            Forms\Components\TextInput::make('voter_id')
                ->label('Voter ID')
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),
            Forms\Components\TextInput::make('phone')
                ->label('Phone Number')
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new'])),
            Forms\Components\TextInput::make('email')
                ->label('Email Address')
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new'])),
            Forms\Components\Select::make('state_id')
                ->label('State (Accounting)')
                ->options(fn () => State::pluck('name', 'id'))
                ->searchable()
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new'])),
            Forms\Components\Textarea::make('address')
                ->label(fn (Get $get) => $get('party_type') === 'organization' ? 'Registered Address' : 'Personal Address')
                ->visible(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->columnSpanFull(),
        ];
    }
}
