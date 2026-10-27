<?php

namespace App\Filament\Resources\TenancyAgreements;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Pages\CreateTenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Pages\DeboardTenancy;
use App\Filament\Resources\TenancyAgreements\Pages\EditAgreementTerms;
use App\Filament\Resources\TenancyAgreements\Pages\EditTenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Pages\ListTenancyAgreements;
use App\Filament\Resources\TenancyAgreements\Pages\ManageAgreementActivation;
use App\Filament\Resources\TenancyAgreements\Pages\ManageAgreementDocuments;
use App\Filament\Resources\TenancyAgreements\Pages\ManageSecondaryTenants;
use App\Filament\Resources\TenancyAgreements\Pages\ManageTenantKyc;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use App\Filament\Resources\TenancyAgreements\Tables\TenancyAgreementsTable;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

use App\Domain\Agreement\Services\TenancyAgreementDocxService;
use App\Domain\Agreement\Services\TenancyAgreementPdfService;
use App\Domain\Agreement\Services\TenancyAgreementTemplateService;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

class TenancyAgreementResource extends Resource
{
    protected static ?string $model = TenancyAgreement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static \UnitEnum|string|null $navigationGroup = 'Properties & Leasing';

    protected static ?int $navigationSort = 4;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getDraftAgreementAction(string $name = 'draftAgreement'): Action
    {
        return Action::make($name)
            ->label(fn (?TenancyAgreement $record) => $record?->agreement_content ? 'Edit Agreement Draft' : 'Customize Agreement Draft')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->size('sm')
            ->slideOver()
            ->modalWidth('7xl')
            ->modalHeading(fn (?TenancyAgreement $record) => "Leave & License Agreement Customizer — #{$record?->code}")
            ->modalDescription('Review and customize agreement clauses below. Verified identity parameters (names, identification numbers, addresses, rent, and bank details) are locked to maintain legal integrity with verified records.')
            ->fillForm(function (?TenancyAgreement $record): array {
                if (! $record) {
                    return [];
                }

                $content = $record->agreement_content;
                if (blank($content)) {
                    $content = app(TenancyAgreementTemplateService::class)->getDefaultContent($record, true);
                }

                return [
                    'agreement_content' => $content,
                ];
            })
            ->form([
                Placeholder::make('editor_guidance')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->content(fn (?TenancyAgreement $record) => new HtmlString(
                        '<div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; margin-bottom: 6px;">'.
                        '<div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">'.
                        '<div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: #166534; line-height: 1.4;">'.
                        '<span style="font-size: 16px;">💡</span>'.
                        '<span><strong>Auto-Save Active:</strong> Changes are saved automatically as draft. Sensitive legal values are displayed as <span style="background: #f0f9ff; border: 1px solid #bae6fd; color: #0369a1; padding: 2px 7px; border-radius: 6px; font-weight: 500;">🔒 locked tokens: <strong style="font-weight: 700; color: #0c4a6e;">Value</strong></span> and cannot be modified inside the editor to protect verified records.</span>'.
                        '</div>'.
                        '<div x-data="{ status: \''.e($record?->agreement_customized_at ? '✓ Last customized: '.$record->agreement_customized_at->diffForHumans() : '✓ Auto-save active').'\', saving: false }" '.
                        '@tenancy-saving.window="saving = true" '.
                        '@tenancy-autosaved.window="saving = false; status = \'✓ Last customized: \' + ($event.detail?.timestamp || \'Just now\')" '.
                        'style="font-size: 12px; font-weight: 600; white-space: nowrap; display: flex; align-items: center; gap: 6px;" '.
                        ':style="{ color: saving ? \'#0284c7\' : \'#15803d\' }">'.
                        '<span x-show="saving" style="display: inline-flex; align-items: center; gap: 5px;">'.
                        '<svg style="width: 13px; height: 13px; animation: tenancy-spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">'.
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
                        '    function formatTenancyTokens() {'.
                        '        const root = document.querySelector(\'.fi-modal-window\') || document;'.
                        '        root.querySelectorAll(\'.tenancy-rich-editor-wrapper span[data-type="mergeTag"], .fi-fo-rich-editor-merge-tags-list span[data-type="mergeTag"], .mou-locked-param\').forEach(function(el) {'.
                        '            if (!el.dataset.tenancyFormatted && el.textContent.includes(\': \')) {'.
                        '                el.dataset.tenancyFormatted = \'true\';'.
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
                        '        const pm = document.querySelector(\'.tenancy-rich-editor-wrapper .ProseMirror\');'.
                        '        if (pm && !pm.dataset.tenancyBlurBound) {'.
                        '            pm.dataset.tenancyBlurBound = \'true\';'.
                        '            pm.addEventListener(\'blur\', function() {'.
                        '                window.dispatchEvent(new CustomEvent(\'tenancy-saving\'));'.
                        '            });'.
                        '        }'.
                        '    }'.
                        '    formatTenancyTokens();'.
                        '    if (!window._tenancyScriptInitialized) {'.
                        '        window._tenancyScriptInitialized = true;'.
                        '        const obs = new MutationObserver(function() { formatTenancyTokens(); });'.
                        '        obs.observe(document.body, { childList: true, subtree: true });'.
                        '        window.addEventListener(\'keydown\', function(e) {'.
                        '            if (e.key === \'Escape\') {'.
                        '                const closeBtn = document.querySelector(\'.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn button\');'.
                        '                if (closeBtn) { closeBtn.click(); }'.
                        '            }'.
                        '        });'.
                        '        if (window.Livewire) {'.
                        '            Livewire.hook(\'request\', function(context) {'.
                        '                if (context && context.succeed) {'.
                        '                    context.succeed(function() {'.
                        '                        window.dispatchEvent(new CustomEvent(\'tenancy-autosaved\', { detail: { timestamp: \'Just now\' } }));'.
                        '                    });'.
                        '                }'.
                        '            });'.
                        '        }'.
                        '    }'.
                        '})();'.
                        '</script>'.
                        '<style>'.
                        '@keyframes tenancy-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }'.
                        '.tenancy-rich-editor-wrapper { border: 1px solid #cbd5e1 !important; border-radius: 10px !important; background: #ffffff !important; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.05) !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper { border-color: #334155 !important; background: #0f172a !important; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.3) !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-toolbar { position: sticky !important; top: 0 !important; z-index: 30 !important; background: #ffffff !important; border-bottom: 1px solid #e2e8f0 !important; border-top-left-radius: 9px !important; border-top-right-radius: 9px !important; padding: 6px 10px !important; box-shadow: 0 2px 4px -1px rgba(0,0,0,0.05) !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-toolbar { background: #1e293b !important; border-color: #334155 !important; box-shadow: 0 2px 4px -1px rgba(0,0,0,0.4) !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-main { position: relative !important; background: #ffffff !important; border-bottom-left-radius: 9px !important; border-bottom-right-radius: 9px !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-main { background: #0f172a !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-content { height: calc(100vh - 300px) !important; min-height: 480px !important; max-height: calc(100vh - 260px) !important; overflow-y: auto !important; padding: 24px 32px !important; scroll-behavior: smooth !important; background: #ffffff !important; border-bottom-left-radius: 9px !important; border-bottom-right-radius: 9px !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-content { background: #0f172a !important; color: #f1f5f9 !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-content .ProseMirror { min-height: 100% !important; outline: none !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-content::-webkit-scrollbar { width: 8px; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-content::-webkit-scrollbar-track { background: #f8fafc; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-content::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-content::-webkit-scrollbar-track { background: #0f172a; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-content::-webkit-scrollbar-thumb { background: #334155; }'.
                        '.tenancy-rich-editor-wrapper span[data-type="mergeTag"]::before, .tenancy-rich-editor-wrapper span[data-type="mergeTag"]::after { display: none !important; content: "" !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-content span[data-type="mergeTag"], .mou-locked-param { display: inline-block !important; vertical-align: -2px !important; white-space: nowrap !important; background-color: #f0f9ff !important; border: 1px solid #bae6fd !important; color: #0369a1 !important; border-radius: 6px !important; padding: 2px 7px !important; margin: 0 2px !important; font-size: 13px !important; line-height: normal !important; box-sizing: border-box !important; user-select: all !important; cursor: default !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-content span[data-type="mergeTag"], :is(.dark) .mou-locked-param { background-color: #0c2d48 !important; border-color: #0284c7 !important; color: #bae6fd !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-content span[data-type="mergeTag"] strong, .mou-locked-param strong { font-weight: 700 !important; color: #0c4a6e !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-content span[data-type="mergeTag"] strong, :is(.dark) .mou-locked-param strong { color: #38bdf8 !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panels { position: absolute !important; top: 8px !important; right: 12px !important; width: 520px !important; max-width: calc(100% - 24px) !important; max-height: min(480px, calc(100vh - 320px)) !important; overflow-x: hidden !important; overflow-y: auto !important; z-index: 50 !important; border-radius: 12px !important; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.18), 0 10px 10px -5px rgba(0,0,0,0.06) !important; border: 1px solid #cbd5e1 !important; background: #ffffff !important; box-sizing: border-box !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-panels { background: #1e293b !important; border-color: #475569 !important; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.6) !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panels[style*="display: none"], .tenancy-rich-editor-wrapper .fi-fo-rich-editor-panels[style*="display:none"], .tenancy-rich-editor-wrapper .fi-fo-rich-editor-panels[x-cloak], .tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel[style*="display: none"], .tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel[style*="display:none"], .tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel[x-cloak] { display: none !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel { width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel-header { position: sticky !important; top: 0 !important; z-index: 10 !important; display: flex !important; align-items: center !important; justify-content: space-between !important; padding: 12px 16px !important; border-bottom: 1px solid #e2e8f0 !important; background: #f8fafc !important; flex-shrink: 0 !important; width: 100% !important; box-sizing: border-box !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel-header { background: #1e293b !important; border-color: #334155 !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel[x-show*="mergeTags"] .fi-fo-rich-editor-panel-heading { font-size: 0 !important; line-height: 0 !important; margin: 0 !important; color: transparent !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel[x-show*="mergeTags"] .fi-fo-rich-editor-panel-heading::after { content: "Insert Locked Field" !important; font-size: 14px !important; font-weight: 600 !important; line-height: 1.4 !important; color: #0f172a !important; letter-spacing: -0.01em !important; display: inline-block !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel[x-show*="mergeTags"] .fi-fo-rich-editor-panel-heading::after { color: #f8fafc !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn { margin-left: auto !important; flex-shrink: 0 !important; display: flex !important; align-items: center !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn button { display: flex !important; align-items: center !important; justify-content: center !important; width: 28px !important; height: 28px !important; border-radius: 6px !important; color: #64748b !important; border: none !important; background: transparent !important; transition: all 0.15s ease !important; cursor: pointer !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn button:hover { background-color: #e2e8f0 !important; color: #0f172a !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn button:hover { background-color: #334155 !important; color: #f8fafc !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panel-close-btn-ctn button svg { width: 18px !important; height: 18px !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-merge-tags-list { display: flex !important; flex-direction: column !important; gap: 8px !important; padding: 12px 14px !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panels::-webkit-scrollbar { width: 6px !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-panels::-webkit-scrollbar-thumb { background: #cbd5e1 !important; border-radius: 3px !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-panels::-webkit-scrollbar-thumb { background: #475569 !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-merge-tag-btn { display: block !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; padding: 0 !important; border: none !important; background: transparent !important; cursor: pointer !important; text-align: left !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-merge-tags-list span[data-type="mergeTag"] { display: block !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; white-space: normal !important; word-break: break-word !important; overflow-wrap: break-word !important; background-color: #f0f9ff !important; border: 1px solid #bae6fd !important; color: #0369a1 !important; border-radius: 8px !important; padding: 8px 12px !important; font-size: 13px !important; line-height: 1.45 !important; cursor: pointer !important; transition: all 0.15s ease-in-out !important; box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-merge-tag-btn:hover span[data-type="mergeTag"] { background-color: #e0f2fe !important; border-color: #7dd3fc !important; box-shadow: 0 2px 5px rgba(3, 105, 161, 0.15) !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-merge-tags-list span[data-type="mergeTag"] { background-color: #0c2d48 !important; border-color: #0284c7 !important; color: #bae6fd !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-merge-tag-btn:hover span[data-type="mergeTag"] { background-color: #0c385a !important; border-color: #38bdf8 !important; }'.
                        '.tenancy-rich-editor-wrapper .fi-fo-rich-editor-merge-tags-list span[data-type="mergeTag"] strong { font-weight: 700 !important; color: #0c4a6e !important; word-break: break-word !important; }'.
                        ':is(.dark) .tenancy-rich-editor-wrapper .fi-fo-rich-editor-merge-tags-list span[data-type="mergeTag"] strong { color: #38bdf8 !important; }'.
                        '</style>'
                    )),

                RichEditor::make('agreement_content')
                    ->hiddenLabel()
                    ->extraAttributes([
                        'class' => 'tenancy-rich-editor-wrapper',
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
                        RichEditorTool::make('mergeTags')
                            ->label('Insert Locked Field')
                            ->icon('heroicon-o-lock-closed')
                            ->jsHandler('togglePanel(\'mergeTags\')')
                            ->activeJsExpression('isPanelActive(\'mergeTags\')'),
                    ])
                    ->mergeTags(fn (?TenancyAgreement $record) => app(TenancyAgreementTemplateService::class)->getMergeTags($record))
                    ->noMergeTagSearchResultsMessage('No locked legal fields found matching your search.')
                    ->extraInputAttributes([
                        'style' => 'font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6;',
                    ])
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, ?TenancyAgreement $record, $livewire) {
                        if ($record && ! blank($state)) {
                            $record->update([
                                'agreement_content' => $state,
                                'agreement_customized_at' => now(),
                            ]);
                            if ($livewire && method_exists($livewire, 'dispatch')) {
                                $livewire->dispatch('tenancy-autosaved', timestamp: 'Just now');
                            }
                        }
                    })
                    ->columnSpanFull(),
            ])
            ->modalSubmitActionLabel(fn (?TenancyAgreement $record) => $record?->hasMedia('draft_pdf') ? 'Recompile & Generate Drafts' : 'Compile & Generate Drafts')
            ->modalSubmitAction(fn (Action $action) => $action->icon('heroicon-o-document-arrow-down')->color('success'))
            ->action(function (array $data, TenancyAgreement $record) {
                try {
                    $record->update([
                        'agreement_content' => $data['agreement_content'] ?? null,
                        'agreement_customized_at' => now(),
                    ]);

                    if ($record->hasMedia('draft_pdf') || $record->hasMedia('draft_word')) {
                        $record->increment('version');
                        $record->refresh();
                    }

                    app(TenancyAgreementPdfService::class)->saveDraftPdf($record);
                    app(TenancyAgreementDocxService::class)->saveDraftDocx($record);
                    $record->refresh();

                    Notification::make()
                        ->title('Draft Documents Generated')
                        ->body("Leave & License Agreement PDF and Word drafts (version v{$record->version}) compiled and updated.")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Cannot Generate Drafts')
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
                    ->action(function (array $data, TenancyAgreement $record, Action $action) {
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
                    ->modalDescription('Are you sure you want to discard your custom edits? The document will be reset to Dwelly’s standard agreement template using current verified form values.')
                    ->action(function (TenancyAgreement $record, Set $set) {
                        $record->update([
                            'agreement_content' => null,
                            'agreement_customized_at' => null,
                        ]);

                        $default = app(TenancyAgreementTemplateService::class)->getDefaultContent($record, true);
                        $set('agreement_content', $default);

                        Notification::make()
                            ->title('Template Reset')
                            ->body('The agreement has been reset to the standard template.')
                            ->info()
                            ->send();
                    }),
            ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', TenancyAgreement::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', TenancyAgreement::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return TenancyAgreementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenancyAgreementsTable::configure($table);
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditTenancyAgreement::class,
            EditAgreementTerms::class,
            ManageSecondaryTenants::class,
            ManageAgreementDocuments::class,
            ManageAgreementActivation::class,
            DeboardTenancy::class,
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenancyAgreements::route('/'),
            'create' => CreateTenancyAgreement::route('/create'),
            'edit' => EditTenancyAgreement::route('/{record}/edit'),
            'terms' => EditAgreementTerms::route('/{record}/terms'),
            'secondary-tenants' => ManageSecondaryTenants::route('/{record}/secondary-tenants'),
            'kyc' => ManageTenantKyc::route('/{record}/kyc'),
            'documents' => ManageAgreementDocuments::route('/{record}/documents'),
            'activation' => ManageAgreementActivation::route('/{record}/activation'),
            'deboard' => DeboardTenancy::route('/{record}/deboard'),
        ];
    }
}
