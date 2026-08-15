<?php

namespace App\Filament\Resources\Billing\MaintenanceQuotationResource\Schemas;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use App\Domain\Party\Enums\VendorOnboardingStatus;
use App\Domain\Party\Models\Party;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class MaintenanceQuotationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // 📍 Top Context Section
            Section::make('🛠 Source Maintenance Ticket Context')
                ->schema([
                    Select::make('maintenance_request_id')
                        ->label('Linked Maintenance Ticket')
                        ->relationship('maintenanceRequest', 'ticket_number')
                        ->options(function () {
                            return MaintenanceRequest::where('is_direct_vendor', false)
                                ->with(['property'])
                                ->get()
                                ->mapWithKeys(fn ($r) => [
                                    $r->id => "{$r->ticket_number} - {$r->title} (" . ($r->property?->building_name ?? 'Property') . ")"
                                ]);
                        })
                        ->default(fn () => request()->query('maintenance_request_id'))
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->helperText('This quotation is permanently attached to this maintenance ticket.'),

                    Placeholder::make('ticket_summary')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function (Get $get, $record) {
                            $ticketId = $get('maintenance_request_id') ?? $record?->maintenance_request_id;
                            if (!$ticketId) {
                                return new HtmlString('<div style="font-size: 13px; color: gray; font-style: italic;">Select a maintenance ticket above to view property and operational context.</div>');
                            }

                            $ticket = MaintenanceRequest::with(['property', 'owner', 'tenant', 'items.itemable'])->find($ticketId);
                            if (!$ticket) return '';

                            $propName = e($ticket->property?->building_name ?? 'Property');
                            $ownerName = e($ticket->owner?->display_name ?? 'N/A');
                            $tenantName = e($ticket->tenant?->display_name ?? 'Vacant / None');
                            $payerLabel = e($ticket->payer_type?->getLabel() ?? ucfirst((string)$ticket->payer_type));
                            $itemsCount = $ticket->items->count();

                            try {
                                $ticketUrl = \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $ticket]);
                            } catch (\Throwable $e) {
                                $ticketUrl = url("/operations/maintenance-requests/{$ticket->id}/edit");
                            }

                            return new HtmlString(
                                '<div style="background-color: rgba(37, 99, 235, 0.04); border: 1px solid rgba(37, 99, 235, 0.15); border-radius: 8px; padding: 16px; font-size: 13px; color: inherit;">' .
                                '<div style="font-weight: 700; font-size: 14px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">' .
                                '<span>📍 Ticket #' . e($ticket->ticket_number) . ': ' . e($ticket->title) . '</span>' .
                                '<a href="' . e($ticketUrl) . '" target="_blank" style="font-size: 12px; font-weight: 600; color: #2563eb; text-decoration: none;">View Operational Ticket &rarr;</a>' .
                                '</div>' .
                                '<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px;">' .
                                '<div><span style="color: gray; font-size: 11px;">Property</span><br><strong>' . $propName . '</strong></div>' .
                                '<div><span style="color: gray; font-size: 11px;">Owner</span><br><strong>' . $ownerName . '</strong></div>' .
                                '<div><span style="color: gray; font-size: 11px;">Tenant</span><br><strong>' . $tenantName . '</strong></div>' .
                                '<div><span style="color: gray; font-size: 11px;">Paying Party</span><br><strong style="color: #2563eb;">' . $payerLabel . '</strong></div>' .
                                '</div>' .
                                '<div style="margin-top: 10px; font-size: 12px; color: gray;"><strong>Reported Defect Items (' . $itemsCount . '):</strong> ' . e($ticket->description) . '</div>' .
                                '</div>'
                            );
                        }),
                ]),

            Tabs::make('FinancialTabs')
                ->columnSpanFull()
                ->tabs([
                    // 📋 Tab 1: Collect Vendor Quotes & Estimates
                    Tab::make('Vendor Quotes & Sourcing')
                        ->icon('heroicon-o-document-currency-rupee')
                        ->schema([
                            Section::make('📋 Multi-Vendor Bids & Trade Estimates')
                                ->description('Collect, compare, and upload estimates from trade contractors for the ticket defect items.')
                                ->schema([
                                    Repeater::make('vendorQuotes')
                                        ->relationship('vendorQuotes')
                                        ->columns(3)
                                        ->defaultItems(0)
                                        ->disabled(fn ($record) => $record?->status === 'approved')
                                        ->addable(fn ($record) => $record?->status !== 'approved')
                                        ->deletable(fn ($record) => $record?->status !== 'approved')
                                        ->reorderable(fn ($record) => $record?->status !== 'approved')
                                        ->schema([
                                            Select::make('maintenance_request_item_ids')
                                                ->label('Target Defect Items')
                                                ->placeholder('Select Defect Items from Ticket')
                                                ->multiple()
                                                ->required()
                                                ->minItems(1)
                                                ->options(function (Get $get, $record, $livewire) {
                                                    $ticketId = $get('../../maintenance_request_id')
                                                        ?? $get('../maintenance_request_id')
                                                        ?? $get('maintenance_request_id')
                                                        ?? $record?->maintenance_request_id
                                                        ?? $livewire->record?->maintenance_request_id;

                                                    if (!$ticketId) {
                                                        return [];
                                                    }

                                                    $items = \App\Domain\Maintenance\Models\MaintenanceRequestItem::where('maintenance_request_id', $ticketId)
                                                        ->with(['itemable'])
                                                        ->get();

                                                    return $items->mapWithKeys(function ($item) {
                                                        $name = '';
                                                        if ($item->itemable instanceof \App\Domain\Property\Models\PropertyRoom) {
                                                            $name = '🚪 ' . ($item->itemable->custom_name ?: ($item->itemable->roomDefinition?->name ?? "Room #{$item->itemable->id}"));
                                                        } elseif ($item->itemable instanceof \App\Domain\Property\Models\PropertyInventory) {
                                                            $name = '📦 ' . ($item->itemable->inventoryType?->name ?? "Item #{$item->itemable->id}");
                                                        } elseif ($item->itemable instanceof \App\Domain\Property\Models\PropertyUtility) {
                                                            $name = '⚡ ' . ($item->itemable->utilityType?->name ?? "Utility #{$item->itemable->id}");
                                                        } else {
                                                            $name = '🛠 General Area';
                                                        }

                                                        $desc = $item->issue_description ? " - " . \Illuminate\Support\Str::limit($item->issue_description, 35) : '';
                                                        $action = $item->repair_action ? " [{$item->repair_action}]" : '';
                                                        $cost = $item->actual_cost > 0 ? " (Est: ₹" . number_format($item->actual_cost, 0) . ")" : '';

                                                        return [$item->id => "{$name}{$desc}{$action}{$cost}"];
                                                    });
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                    $ids = is_array($state) ? $state : (filled($state) ? [$state] : []);
                                                    if (!empty($ids)) {
                                                        $selectedItems = \App\Domain\Maintenance\Models\MaintenanceRequestItem::whereIn('id', $ids)->get();

                                                        if (blank($get('trade_title')) && $selectedItems->isNotEmpty()) {
                                                            $first = $selectedItems->first();
                                                            $title = $first->repair_action ? "{$first->repair_action}: " : 'Repair: ';
                                                            $title .= \Illuminate\Support\Str::limit($first->issue_description, 30);
                                                            if ($selectedItems->count() > 1) {
                                                                $title .= ' (+' . ($selectedItems->count() - 1) . ' items)';
                                                            }
                                                            $set('trade_title', $title);
                                                        }

                                                        if (blank($get('quoted_cost')) || (float)$get('quoted_cost') === 0.0) {
                                                            $totalEst = $selectedItems->sum('actual_cost');
                                                            if ($totalEst > 0) {
                                                                $set('quoted_cost', $totalEst);
                                                            }
                                                        }
                                                    }
                                                })
                                                ->columnSpan(1),

                                            TextInput::make('trade_title')
                                                ->label('Trade Work Title')
                                                ->placeholder('e.g. Wall Masonry & Plastering')
                                                ->required()
                                                ->columnSpan(1),

                                            Select::make('vendor_party_id')
                                                ->label('Assigned Service Vendor')
                                                ->options(function () {
                                                    return Party::whereHas('vendorProfile', function ($q) {
                                                        $q->where('onboarding_status', VendorOnboardingStatus::VERIFIED->value)
                                                          ->orWhere('onboarding_status', VendorOnboardingStatus::VERIFIED);
                                                    })
                                                        ->with(['vendorProfile.trade'])
                                                        ->get()
                                                        ->mapWithKeys(function ($party) {
                                                            $tradeName = $party->vendorProfile?->trade?->name ?? 'General';
                                                            return [$party->id => "{$party->display_name} ({$tradeName})"];
                                                        });
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->columnSpan(1),

                                            TextInput::make('vendor_quote_number')
                                                ->label('Vendor Quote / Estimate Ref #')
                                                ->placeholder('e.g. EST-2026-081')
                                                ->required()
                                                ->columnSpan(1),

                                            DatePicker::make('vendor_quote_date')
                                                ->label('Quotation Date')
                                                ->default(now())
                                                ->required()
                                                ->columnSpan(1),

                                            TextInput::make('quoted_cost')
                                                ->label('Vendor Quoted Cost (₹)')
                                                ->numeric()
                                                ->prefix('₹')
                                                ->required()
                                                ->columnSpan(1),

                                            Textarea::make('scope_of_work')
                                                ->label('Scope of Work / Material Specifications')
                                                ->rows(2)
                                                ->placeholder('Detailed specifications of parts, labor hours, and warranty terms...')
                                                ->required()
                                                ->columnSpanFull(),

                                            SpatieMediaLibraryFileUpload::make('vendor_quote_files')
                                                ->collection('vendor_quote_files')
                                                ->multiple()
                                                ->required()
                                                ->minFiles(1)
                                                ->label('Vendor Official Quotation PDF / Estimate Sheet')
                                                ->columnSpanFull(),
                                        ]),
                                ]),
                        ]),

                    // 💵 Tab 2: Client Quotation Builder & Pricing
                    Tab::make('Client Quotation & Markup')
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Section::make('💵 Itemized Client Quotation')
                                ->description('Prepare the formal quotation presented to the Owner/Tenant with line items, materials, and Dwelly service fees.')
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make('quote_number')
                                            ->label('Quote Number')
                                            ->disabled()
                                            ->placeholder('Generated automatically (e.g. QTE-2026-00001)'),

                                        Placeholder::make('quote_status_badge')
                                            ->label('Quotation Status')
                                            ->content(function ($record) {
                                                $status = $record?->status ?? 'draft';
                                                $color = match($status) {
                                                    'approved' => '#16a34a',
                                                    'rejected' => '#dc2626',
                                                    'pending_approval' => '#d97706',
                                                    default => '#6b7280',
                                                };
                                                return new HtmlString("<span style=\"padding: 4px 10px; border-radius: 6px; font-weight: 600; text-transform: uppercase; font-size: 11px; background-color: {$color}; color: #ffffff;\">" . strtoupper($status) . "</span>");
                                            }),

                                        TextInput::make('total_amount')
                                            ->label('Total Quoted Amount (₹)')
                                            ->numeric()
                                            ->prefix('₹')
                                            ->default(0.00)
                                            ->disabled(fn ($record) => $record?->status === 'approved')
                                            ->required(),
                                    ]),

                                    Repeater::make('items')
                                        ->relationship('items')
                                        ->columns(4)
                                        ->defaultItems(1)
                                        ->disabled(fn ($record) => $record?->status === 'approved')
                                        ->addable(fn ($record) => $record?->status !== 'approved')
                                        ->deletable(fn ($record) => $record?->status !== 'approved')
                                        ->reorderable(fn ($record) => $record?->status !== 'approved')
                                        ->required()
                                        ->minItems(1)
                                        ->schema([
                                            Select::make('vendor_quote_id')
                                                ->label('Linked Trade Quote')
                                                ->placeholder('Select Trade Quote')
                                                ->options(function (Get $get, $record, $livewire) {
                                                    $ticketId = $get('../../maintenance_request_id')
                                                        ?? $get('../maintenance_request_id')
                                                        ?? $get('maintenance_request_id')
                                                        ?? $record?->maintenance_request_id
                                                        ?? $livewire->record?->maintenance_request_id;

                                                    if (!$ticketId) {
                                                        return [];
                                                    }

                                                    return \App\Domain\Maintenance\Models\MaintenanceVendorQuote::where('maintenance_request_id', $ticketId)
                                                        ->get()
                                                        ->mapWithKeys(fn ($q) => [
                                                            $q->id => ($q->trade_title ?: 'Trade Quote') . " (₹" . number_format($q->quoted_cost, 0) . ")"
                                                        ]);
                                                })
                                                ->nullable()
                                                ->searchable()
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                    if ($state && blank($get('description'))) {
                                                        $quote = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::find($state);
                                                        if ($quote) {
                                                            $set('description', $quote->trade_title);
                                                            if (blank($get('unit_price')) || (float)$get('unit_price') === 0.0) {
                                                                $set('unit_price', $quote->quoted_cost);
                                                                $set('total_price', $quote->quoted_cost);
                                                            }
                                                        }
                                                    }
                                                })
                                                ->columnSpan(1),

                                            TextInput::make('description')
                                                ->label('Item Scope / Description')
                                                ->placeholder('e.g. Wall Plastering & Waterproof Coating')
                                                ->required()
                                                ->columnSpan(2),

                                            TextInput::make('quantity')
                                                ->label('Qty')
                                                ->numeric()
                                                ->default(1)
                                                ->required()
                                                ->reactive()
                                                ->afterStateUpdated(function (Get $get, Set $set) {
                                                    $qty = (float)($get('quantity') ?: 1);
                                                    $price = (float)($get('unit_price') ?: 0);
                                                    $set('total_price', $qty * $price);
                                                })
                                                ->columnSpan(1),

                                            TextInput::make('unit_price')
                                                ->label('Unit Price (₹)')
                                                ->numeric()
                                                ->prefix('₹')
                                                ->required()
                                                ->reactive()
                                                ->afterStateUpdated(function (Get $get, Set $set) {
                                                    $qty = (float)($get('quantity') ?: 1);
                                                    $price = (float)($get('unit_price') ?: 0);
                                                    $set('total_price', $qty * $price);
                                                })
                                                ->columnSpan(1),

                                            TextInput::make('total_price')
                                                ->label('Total Price (₹)')
                                                ->numeric()
                                                ->prefix('₹')
                                                ->disabled()
                                                ->dehydrated()
                                                ->required()
                                                ->helperText('Auto-calculated (Qty × Unit Price)')
                                                ->columnSpan(1),
                                        ]),

                                    Section::make('📄 Generated Official Quotation Document & Revision History')
                                        ->description('System-generated PDF proposal with automatic revision control.')
                                        ->headerActions([
                                            Action::make('generatePdfInTab')
                                                ->label(fn ($record) => $record?->hasMedia('generated_quote_pdf') ? 'Regenerate Quotation PDF' : 'Generate Quotation PDF')
                                                ->icon('heroicon-o-document-arrow-down')
                                                ->color('warning')
                                                ->button()
                                                ->size('sm')
                                                ->visible(fn ($record) => $record && in_array($record->status, ['draft', 'pending_approval']))
                                                ->requiresConfirmation(fn ($record) => (bool)$record?->hasMedia('generated_quote_pdf'))
                                                ->modalHeading(fn ($record) => $record?->hasMedia('generated_quote_pdf') ? 'Regenerate Quotation PDF' : 'Generate Quotation PDF')
                                                ->modalDescription(fn ($record) => $record?->hasMedia('generated_quote_pdf')
                                                    ? 'Regenerating will compile a new revision (v' . ($record->version + 1) . ') with current line item prices, while retaining previous versions in the history.'
                                                    : 'This will compile and generate the official PDF quotation document.')
                                                ->modalSubmitActionLabel('Compile & Generate PDF')
                                                ->action(function ($record, $livewire) {
                                                    if (!$record || $record->items()->count() === 0) {
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Line Items Required')
                                                            ->body('Please save at least one line item in the quotation before generating the PDF document.')
                                                            ->warning()
                                                            ->send();
                                                        return;
                                                    }

                                                    try {
                                                        $pdfService = app(\App\Domain\Maintenance\Services\MaintenanceQuotationPdfService::class);
                                                        $pdfService->generatePdf($record);
                                                        $record->refresh();

                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Quotation PDF Generated')
                                                            ->body("Quotation #{$record->quote_number} (v{$record->version}) compiled successfully.")
                                                            ->success()
                                                            ->send();

                                                        if (method_exists($livewire, 'refreshFormData')) {
                                                            $livewire->refreshFormData([]);
                                                        }
                                                        $livewire->dispatch('$refresh');
                                                    } catch (\Throwable $e) {
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Failed to Generate PDF')
                                                            ->body($e->getMessage())
                                                            ->danger()
                                                            ->send();
                                                    }
                                                }),
                                        ])
                                        ->schema([
                                            Placeholder::make('generated_pdf_status')
                                                ->label('')
                                                ->columnSpanFull()
                                                ->content(function ($record) {
                                                    if (!$record) return '';

                                                    $latestMedia = $record->getMedia('generated_quote_pdf')->last();
                                                    $allMedia = $record->getMedia('generated_quote_pdf')->reverse();

                                                    if (!$latestMedia) {
                                                        return new HtmlString(
                                                            '<div style="background: rgba(217, 119, 6, 0.06); border: 1px dashed rgba(217, 119, 6, 0.3); border-radius: 8px; padding: 18px; text-align: center;">' .
                                                            '<div style="font-size: 14px; font-weight: 700; color: #b45309; margin-bottom: 4px;">No Quotation PDF Generated Yet</div>' .
                                                            '<div style="font-size: 12px; color: #92400e;">Click the <strong>"Generate Quotation PDF"</strong> button above to compile the official customer proposal.</div>' .
                                                            '</div>'
                                                        );
                                                    }

                                                    $vNumber = $latestMedia->getCustomProperty('version', $record->version ?? 1);
                                                    $genDate = $latestMedia->created_at ? $latestMedia->created_at->format('d M Y, H:i') : 'Recently';
                                                    $genBy = $latestMedia->getCustomProperty('generated_by_name', 'Staff');
                                                    $fileSize = number_format($latestMedia->size / 1024, 1) . ' KB';

                                                    $historyHtml = '';
                                                    if ($allMedia->count() > 1) {
                                                        $historyHtml .= '<div style="margin-top: 16px; border-top: 1px solid rgba(128,128,128,0.2); padding-top: 12px;">' .
                                                            '<div style="font-size: 12px; font-weight: 700; color: gray; margin-bottom: 8px; text-transform: uppercase;">Revision History (' . $allMedia->count() . ' versions)</div>' .
                                                            '<div style="display: flex; flex-direction: column; gap: 6px;">';

                                                        foreach ($allMedia as $m) {
                                                            $mVer = $m->getCustomProperty('version', 1);
                                                            $mDate = $m->created_at ? $m->created_at->format('d M Y, H:i') : '';
                                                            $isLatest = ($m->id === $latestMedia->id);
                                                            $titleStr = addslashes('Quotation #' . $record->quote_number . ' (v' . $mVer . ')');

                                                            $historyHtml .= '<div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px; padding: 8px 12px; background: rgba(128,128,128,0.04); border-radius: 6px;">' .
                                                                '<div>' .
                                                                '<strong>Version ' . $mVer . '</strong>' . ($isLatest ? ' <span style="background: #16a34a; color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 3px; font-weight: bold;">CURRENT</span>' : '') .
                                                                '<span style="color: gray; margin-left: 8px;">' . $mDate . '</span>' .
                                                                '</div>' .
                                                                '<button type="button" wire:click.prevent="mountAction(\'viewHistoryPdf\', { mediaId: ' . $m->id . ', title: \'' . $titleStr . '\' })" style="color: #2563eb; font-weight: 600; background: none; border: none; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; text-decoration: underline;">' .
                                                                '<svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>' .
                                                                'View PDF (v' . $mVer . ')</button>' .
                                                                '</div>';
                                                        }

                                                        $historyHtml .= '</div></div>';
                                                    }

                                                    $mainTitle = addslashes('Quotation #' . $record->quote_number . ' (v' . $vNumber . ')');

                                                    return new HtmlString(
                                                        '<div style="background: rgba(37, 99, 235, 0.03); border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 8px; padding: 16px;">' .
                                                        '<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">' .
                                                        '<div style="display: flex; align-items: center; gap: 12px;">' .
                                                        '<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 20px;">📄</div>' .
                                                        '<div>' .
                                                        '<div style="font-weight: 700; font-size: 14px;">Quotation #' . e($record->quote_number) . ' <span style="background: #2563eb; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 4px; font-weight: bold;">v' . $vNumber . '</span></div>' .
                                                        '<div style="font-size: 12px; color: gray; margin-top: 2px;">Generated ' . $genDate . ' by ' . e($genBy) . ' &bull; ' . $fileSize . '</div>' .
                                                        '</div>' .
                                                        '</div>' .
                                                        '<div style="display: flex; gap: 8px;">' .
                                                        '<button type="button" wire:click.prevent="mountAction(\'viewHistoryPdf\', { mediaId: ' . $latestMedia->id . ', title: \'' . $mainTitle . '\' })" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; background: #2563eb; color: #ffffff; font-weight: 600; font-size: 13px; border-radius: 6px; cursor: pointer; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.08); transition: background-color 0.15s ease;">' .
                                                        '<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>' .
                                                        'View / Download PDF</button>' .
                                                        '</div>' .
                                                        '</div>' .
                                                        $historyHtml .
                                                        '</div>'
                                                    );
                                                }),
                                        ]),
                                ]),
                        ]),

                    // ✅ Tab 3: Client Approval & Decision
                    Tab::make('Client Approval & Proof')
                        ->icon('heroicon-o-check-badge')
                        ->schema([
                            Section::make('✅ Client Approval & Decision (Mandatory for Work Authorization)')
                                ->description('Enter customer authorization remarks and upload approval proof files directly below, then confirm approval.')
                                ->headerActions([
                                    Action::make('approveQuoteInTab')
                                        ->label('Confirm Quotation Approval')
                                        ->icon('heroicon-o-check-circle')
                                        ->color('success')
                                        ->button()
                                        ->size('sm')
                                        ->visible(fn ($record) => $record && in_array($record->status, ['draft', 'pending_approval']))
                                        ->requiresConfirmation()
                                        ->modalHeading('Approve Client Quotation')
                                        ->modalDescription('Are you sure you want to approve this quotation? Please ensure you have entered the approval remarks and uploaded the proof files in this tab.')
                                        ->modalSubmitActionLabel('Confirm Approval')
                                        ->action(function ($record, $livewire) {
                                            $notes = $livewire->data['approval_notes'] ?? $record->approval_notes;

                                            if (blank($notes) || !$record->hasMedia('approval_proof_files')) {
                                                Notification::make()
                                                    ->title('Approval Details Required')
                                                    ->body('Please enter the Approval Confirmation Remarks and upload at least one Approval Proof document in this tab before confirming approval.')
                                                    ->warning()
                                                    ->persistent()
                                                    ->send();
                                                return;
                                            }

                                            $record->update([
                                                'status' => 'approved',
                                                'approved_at' => now(),
                                                'approval_notes' => $notes,
                                            ]);

                                            if ($record->maintenanceRequest) {
                                                $record->maintenanceRequest->update([
                                                    'quotation_status' => 'approved',
                                                    'quotation_approved_at' => now(),
                                                    'quotation_approval_notes' => $notes,
                                                    'status' => MaintenanceStatus::QUOTATION_APPROVED,
                                                ]);
                                                $record->maintenanceRequest->syncQuotationTotals();
                                            }

                                            Notification::make()
                                                ->title('Quotation Approved')
                                                ->body("Quotation {$record->quote_number} has been approved. Operations ticket is now authorized for repair.")
                                                ->success()
                                                ->send();

                                            if (method_exists($livewire, 'refreshFormData')) {
                                                $livewire->refreshFormData([]);
                                            }
                                            $livewire->dispatch('$refresh');
                                        }),

                                    Action::make('revertToDirectRepairInTab')
                                        ->label('Client Rejected: Revert to Direct')
                                        ->icon('heroicon-o-arrow-path-rounded-square')
                                        ->color('danger')
                                        ->button()
                                        ->size('sm')
                                        ->visible(fn ($record) => $record && in_array($record->status, ['draft', 'pending_approval', 'approved']))
                                        ->requiresConfirmation()
                                        ->modalHeading('Revert Ticket to Direct Repair')
                                        ->modalDescription('If the client declined Dwelly\'s quote and prefers to handle the repair directly, Dwelly will track the repair and conduct the verification audit upon completion.')
                                        ->form([
                                            Textarea::make('rejection_reason')
                                                ->label('Reason for Reverting to Direct Repair')
                                                ->placeholder('e.g. Client declined quotation; will hire own vendor directly.')
                                                ->required()
                                                ->columnSpanFull(),
                                        ])
                                        ->modalSubmitActionLabel('Confirm Direct Repair Mode')
                                        ->action(function ($record, array $data, $livewire) {
                                            $reason = $data['rejection_reason'] ?? 'Client requested direct execution';

                                            $record->update([
                                                'status' => 'rejected',
                                                'rejection_reason' => $reason,
                                                'rejection_action' => 'revert_to_direct',
                                            ]);

                                            if ($record->maintenanceRequest) {
                                                $record->maintenanceRequest->update([
                                                    'is_direct_vendor' => true,
                                                    'quotation_status' => 'rejected',
                                                    'is_dwelly_involved' => false,
                                                    'status' => MaintenanceStatus::IN_PROGRESS,
                                                    'direct_payment_notes' => "Reverted to Direct Repair: {$reason}",
                                                ]);
                                                $record->maintenanceRequest->syncQuotationTotals();
                                            }

                                            Notification::make()
                                                ->title('Reverted to Direct Repair')
                                                ->body("Ticket #{$record->maintenanceRequest?->ticket_number} is now in Direct Repair mode.")
                                                ->warning()
                                                ->send();

                                            if (method_exists($livewire, 'refreshFormData')) {
                                                $livewire->refreshFormData([]);
                                            }
                                            $livewire->dispatch('$refresh');
                                        }),
                                ])
                                ->columns(2)
                                ->schema([
                                    Placeholder::make('approval_status_banner')
                                        ->label('')
                                        ->columnSpanFull()
                                        ->content(function ($record) {
                                            if (!$record) return '';
                                            if ($record->status === 'approved') {
                                                $date = $record->approved_at ? $record->approved_at->format('d M Y, H:i') : '';
                                                return new HtmlString(
                                                    '<div style="background: rgba(22, 163, 74, 0.08); border: 1px solid rgba(22, 163, 74, 0.3); border-radius: 8px; padding: 14px; display: flex; align-items: center; justify-content: space-between;">' .
                                                    '<div><strong style="color: #16a34a; font-size: 14px;">✅ Quotation Approved & Authorized</strong>' .
                                                    '<div style="font-size: 12px; color: gray; margin-top: 2px;">Approved on ' . $date . ' &bull; Work authorization granted</div></div>' .
                                                    '<span style="background: #16a34a; color: white; padding: 3px 10px; border-radius: 6px; font-weight: 700; font-size: 11px;">APPROVED</span>' .
                                                    '</div>'
                                                );
                                            } elseif ($record->status === 'rejected') {
                                                return new HtmlString(
                                                    '<div style="background: rgba(220, 38, 38, 0.08); border: 1px solid rgba(220, 38, 38, 0.3); border-radius: 8px; padding: 14px; display: flex; align-items: center; justify-content: space-between;">' .
                                                    '<div><strong style="color: #dc2626; font-size: 14px;">❌ Quotation Declined (Reverted to Direct Repair)</strong>' .
                                                    '<div style="font-size: 12px; color: gray; margin-top: 2px;">Reason: ' . e($record->rejection_reason ?? 'Client declined quotation') . '</div></div>' .
                                                    '<span style="background: #dc2626; color: white; padding: 3px 10px; border-radius: 6px; font-weight: 700; font-size: 11px;">REJECTED</span>' .
                                                    '</div>'
                                                );
                                            }

                                            return new HtmlString(
                                                '<div style="background: rgba(217, 119, 6, 0.08); border: 1px dashed rgba(217, 119, 6, 0.3); border-radius: 8px; padding: 14px;">' .
                                                '<strong style="color: #b45309; font-size: 13px;">⏳ Quotation Approval Pending</strong>' .
                                                '<div style="font-size: 12px; color: #92400e; margin-top: 2px;">Upload approval proof documents and remarks below, then click <strong>"Confirm Quotation Approval"</strong> above.</div>' .
                                                '</div>'
                                            );
                                        }),

                                    Textarea::make('approval_notes')
                                        ->label('Approval Confirmation Remarks')
                                        ->placeholder('e.g. Approved by Owner via WhatsApp on 15 Aug')
                                        ->rows(2)
                                        ->markAsRequired()
                                        ->disabled(fn ($record) => $record?->status === 'approved')
                                        ->columnSpanFull(),

                                    SpatieMediaLibraryFileUpload::make('approval_proof_files')
                                        ->collection('approval_proof_files')
                                        ->multiple()
                                        ->markAsRequired()
                                        ->minFiles(1)
                                        ->disabled(fn ($record) => $record?->status === 'approved')
                                        ->label('Quotation Approval Proof (WhatsApp Screenshot / Email / Signed PDF)')
                                        ->helperText('Upload proof of customer acceptance before confirming approval.')
                                        ->columnSpanFull(),

                                    Textarea::make('rejection_reason')
                                        ->label('Rejection Reason (If Client Declined)')
                                        ->rows(2)
                                        ->disabled()
                                        ->visible(fn ($record) => $record?->status === 'rejected')
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    // 🛠 Tab 4: Work Orders & Vendor Awarding
                    Tab::make('Work Orders & Execution')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->schema([
                            Section::make('🛠 Contractor Work Orders & Awarding')
                                ->description('Select winning contractor quotations below and click "Issue Work Order(s)" to authorize on-site repair work.')
                                ->headerActions([
                                    Action::make('issueWorkOrderInTab')
                                        ->label('Issue Work Order(s)')
                                        ->icon('heroicon-o-document-check')
                                        ->color('primary')
                                        ->button()
                                        ->size('sm')
                                        ->visible(fn ($record) => $record && $record->status === 'approved' && empty($record->awarded_vendor_quote_ids))
                                        ->requiresConfirmation()
                                        ->modalHeading('Issue Contractor Work Order(s)')
                                        ->modalDescription('Confirm issuance of official Work Orders for the contractor quotation(s) selected in this tab. This will generate work order reference numbers and authorize technicians for on-site work.')
                                        ->modalSubmitActionLabel('Confirm & Issue Work Orders')
                                        ->action(function ($record, $livewire) {
                                            $selectedIds = $livewire->data['awarded_vendor_quote_ids'] ?? $record->awarded_vendor_quote_ids ?? [];
                                            $selectedIds = (array)$selectedIds;

                                            if (empty($selectedIds)) {
                                                Notification::make()
                                                    ->title('Vendor Quotation Required')
                                                    ->body('Please check at least one vendor quotation in the checklist below before issuing work orders.')
                                                    ->warning()
                                                    ->persistent()
                                                    ->send();
                                                return;
                                            }

                                            $ticketId = $record->maintenance_request_id;
                                            $allQuotes = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::where('maintenance_request_id', $ticketId)->get();
                                            $pdfService = app(\App\Domain\Maintenance\Services\MaintenanceWorkOrderPdfService::class);

                                            $issuedCount = 0;
                                            foreach ($allQuotes as $q) {
                                                if (in_array($q->id, $selectedIds)) {
                                                    $woSuffix = strtoupper(substr($record->quote_number ?: uniqid(), -5)) . '-' . substr($q->id, -4);
                                                    $woNumber = $q->work_order_number ?: "WO-{$woSuffix}";
                                                    $q->update([
                                                        'is_awarded' => true,
                                                        'work_order_number' => $woNumber,
                                                        'work_order_issued_at' => $q->work_order_issued_at ?: now(),
                                                        'status' => 'awarded',
                                                    ]);

                                                    try {
                                                        $pdfService->generatePdf($q, $record);
                                                    } catch (\Throwable $e) {
                                                        // Continue even if PDF generation has minor error
                                                    }

                                                    $issuedCount++;
                                                } else {
                                                    $q->update([
                                                        'is_awarded' => false,
                                                        'status' => 'rejected',
                                                    ]);
                                                }
                                            }

                                            $record->update([
                                                'awarded_vendor_quote_ids' => $selectedIds,
                                            ]);

                                            Notification::make()
                                                ->title('Work Order(s) Issued')
                                                ->body("Successfully issued {$issuedCount} Work Order(s) and generated contractor commencement letters.")
                                                ->success()
                                                ->send();

                                            if (method_exists($livewire, 'refreshFormData')) {
                                                $livewire->refreshFormData([]);
                                            }
                                            $livewire->dispatch('$refresh');
                                        }),

                                    Action::make('startRepairAndOpenTicket')
                                        ->label('Proceed with Repair & Open Ticket')
                                        ->icon('heroicon-o-play')
                                        ->color('success')
                                        ->button()
                                        ->size('sm')
                                        ->visible(function ($record) {
                                            if (!$record || empty($record->awarded_vendor_quote_ids)) {
                                                return false;
                                            }
                                            $ticket = $record->maintenanceRequest;
                                            if (!$ticket) return false;

                                            return !in_array($ticket->status, [
                                                MaintenanceStatus::IN_PROGRESS,
                                                MaintenanceStatus::WORK_COMPLETED,
                                                MaintenanceStatus::AUDIT_PENDING,
                                                MaintenanceStatus::CLOSED,
                                                MaintenanceStatus::CANCELLED,
                                            ]);
                                        })
                                        ->requiresConfirmation()
                                        ->modalHeading('Authorize & Start On-Site Repairs')
                                        ->modalDescription(function ($record) {
                                            $ticket = $record->maintenanceRequest;
                                            return "Confirm that technicians are authorized to commence on-site physical repairs for ticket #{$ticket?->ticket_number}. This will mark the ticket as In Progress and open it directly.";
                                        })
                                        ->modalSubmitActionLabel('Confirm & Open Ticket')
                                        ->action(function ($record) {
                                            $ticket = $record->maintenanceRequest;
                                            if (!$ticket) return;

                                            $ticket->update([
                                                'status' => MaintenanceStatus::IN_PROGRESS,
                                            ]);

                                            Notification::make()
                                                ->title('Repairs In Progress')
                                                ->body("Ticket #{$ticket->ticket_number} marked as In Progress.")
                                                ->success()
                                                ->send();

                                            return redirect(\App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $ticket]));
                                        }),

                                    Action::make('viewTicketInTab')
                                        ->label('Open Operational Ticket')
                                        ->icon('heroicon-o-arrow-top-right-on-square')
                                        ->color('gray')
                                        ->button()
                                        ->size('sm')
                                        ->visible(function ($record) {
                                            if (!$record || empty($record->awarded_vendor_quote_ids)) {
                                                return false;
                                            }
                                            $ticket = $record->maintenanceRequest;
                                            if (!$ticket) return false;

                                            return in_array($ticket->status, [
                                                MaintenanceStatus::IN_PROGRESS,
                                                MaintenanceStatus::WORK_COMPLETED,
                                                MaintenanceStatus::AUDIT_PENDING,
                                                MaintenanceStatus::CLOSED,
                                            ]);
                                        })
                                        ->url(fn ($record) => \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $record->maintenanceRequest]))
                                        ->openUrlInNewTab(),
                                ])
                                ->schema([
                                    CheckboxList::make('awarded_vendor_quote_ids')
                                        ->label('Select Winning Vendor Quotations to Award')
                                        ->options(function (Get $get, $record, $livewire) {
                                            $ticketId = $get('maintenance_request_id') ?? $record?->maintenance_request_id ?? $livewire->record?->maintenance_request_id;
                                            if (!$ticketId) return [];

                                            return \App\Domain\Maintenance\Models\MaintenanceVendorQuote::where('maintenance_request_id', $ticketId)
                                                ->with(['vendor'])
                                                ->get()
                                                ->mapWithKeys(function ($q) {
                                                    $vendorName = $q->vendor?->display_name ?? 'Vendor';
                                                    $tradeName = $q->vendor?->vendorProfile?->trade?->name ?? 'General';
                                                    $ref = $q->vendor_quote_number ? " [Quote #{$q->vendor_quote_number}]" : '';
                                                    $date = $q->vendor_quote_date ? " (Dated {$q->vendor_quote_date->format('d M Y')})" : '';
                                                    $cost = number_format((float)$q->quoted_cost, 2);
                                                    return [$q->id => "🛠 {$vendorName} ({$tradeName}) — {$q->trade_title}{$ref}{$date} — ₹{$cost}"];
                                                });
                                        })
                                        ->columns(1)
                                        ->bulkToggleable()
                                        ->reactive()
                                        ->disabled(fn ($record) => !empty($record?->awarded_vendor_quote_ids))
                                        ->helperText(fn ($record) => !empty($record?->awarded_vendor_quote_ids)
                                            ? 'Work Orders have been officially issued for the selected contractor(s). The assignments are now locked.'
                                            : 'Check the winning contractor quotation(s) above, then click the "Issue Work Order(s)" button in the section header.')
                                        ->columnSpanFull(),

                                    Placeholder::make('awarded_work_orders_summary')
                                        ->label('')
                                        ->columnSpanFull()
                                        ->content(function ($record) {
                                            if (!$record) return '';

                                            if ($record->status !== 'approved') {
                                                return new HtmlString(
                                                    '<div style="padding: 18px; background: rgba(234, 179, 8, 0.06); border: 1px dashed rgba(234, 179, 8, 0.3); border-radius: 8px; font-size: 13px; color: #a16207; text-align: center;">' .
                                                    '<div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">⚠️ Client Quotation Approval Pending</div>' .
                                                    '<div>Work orders cannot be issued until the client quotation is approved in Tab 3 (Client Approval & Proof).</div>' .
                                                    '</div>'
                                                );
                                            }

                                            $awardedIds = $record->awarded_vendor_quote_ids ?? [];
                                            if (empty($awardedIds)) {
                                                return new HtmlString(
                                                    '<div style="padding: 18px; background: rgba(37, 99, 235, 0.05); border: 1px dashed rgba(37, 99, 235, 0.3); border-radius: 8px; font-size: 13px; color: #1e40af; text-align: center;">' .
                                                    '<div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">🛠 Ready to Issue Work Orders</div>' .
                                                    '<div>Quotation is officially approved! Check winning quotes above and click <strong>"Issue Work Order(s)"</strong> to authorize contractors and generate official commencement letters.</div>' .
                                                    '</div>'
                                                );
                                            }

                                            $awarded = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::whereIn('id', (array)$awardedIds)
                                                ->with(['vendor', 'vendorTrade'])
                                                ->get();

                                            if ($awarded->isEmpty()) {
                                                return new HtmlString('<div style="padding: 14px; background: rgba(128,128,128,0.05); border: 1px dashed rgba(128,128,128,0.3); border-radius: 8px; font-size: 13px; color: gray;">No awarded vendor quotes found.</div>');
                                            }

                                            $cards = '<div style="display: flex; flex-direction: column; gap: 14px;">';
                                            foreach ($awarded as $q) {
                                                $vName = e($q->vendor?->display_name ?? 'Vendor');
                                                $vPhone = e($q->vendor?->phone ?? 'N/A');
                                                $cost = number_format((float)$q->quoted_cost, 2);
                                                $title = e($q->trade_title);
                                                $scope = e($q->scope_of_work ?: 'Standard maintenance repair scope');
                                                $woRef = e($q->work_order_number ?: ('WO-' . substr($q->id, -6)));
                                                $quoteRef = e($q->vendor_quote_number ?: 'Not specified');
                                                $quoteDate = $q->vendor_quote_date ? $q->vendor_quote_date->format('d M Y') : 'N/A';
                                                $issuedAt = $q->work_order_issued_at ? $q->work_order_issued_at->format('d M Y, H:i') : ($q->updated_at ? $q->updated_at->format('d M Y, H:i') : 'Recently');

                                                // Fetch or generate Work Order PDF letter
                                                $woMedia = $q->getMedia('work_order_letter_pdf')->last();
                                                if (!$woMedia) {
                                                    try {
                                                        $woMedia = app(\App\Domain\Maintenance\Services\MaintenanceWorkOrderPdfService::class)->generatePdf($q, $record);
                                                    } catch (\Throwable $e) {
                                                        $woMedia = null;
                                                    }
                                                }

                                                $letterActionHtml = '';
                                                if ($woMedia) {
                                                    $modalTitle = addslashes("Work Order #{$woRef} — {$vName}");
                                                    $letterActionHtml = '<div style="margin-top: 12px; display: flex; justify-content: flex-end;">' .
                                                        '<button type="button" wire:click.prevent="mountAction(\'viewHistoryPdf\', { mediaId: ' . $woMedia->id . ', title: \'' . $modalTitle . '\' })" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #2563eb; color: #ffffff; font-weight: 600; font-size: 12px; border-radius: 6px; cursor: pointer; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.08); transition: background-color 0.15s ease;">' .
                                                        '<svg style="width: 15px; height: 15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>' .
                                                        'View / Download Work Order Letter</button>' .
                                                        '</div>';
                                                }

                                                $cards .= '<div style="background: rgba(37,99,235,0.03); border: 1px solid rgba(37,99,235,0.2); border-radius: 8px; padding: 18px; font-size: 13px;">' .
                                                    '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid rgba(37,99,235,0.15); padding-bottom: 10px;">' .
                                                    '<div>' .
                                                    '<strong style="font-size: 15px; color: #1d4ed8;">🛠 Work Order #' . $woRef . '</strong>' .
                                                    '<div style="font-size: 11px; color: gray; margin-top: 2px;">Issued on ' . $issuedAt . '</div>' .
                                                    '</div>' .
                                                    '<span style="background: #16a34a; color: white; padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px;">ISSUED / ACTIVE</span>' .
                                                    '</div>' .
                                                    '<div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 12px;">' .
                                                    '<div><span style="color: gray; font-size: 11px;">Awarded Contractor</span><br><strong>' . $vName . '</strong><br><span style="font-size: 11px; color: gray;">📞 ' . $vPhone . '</span></div>' .
                                                    '<div><span style="color: gray; font-size: 11px;">Vendor Quotation Ref</span><br><strong>' . $quoteRef . '</strong><br><span style="font-size: 11px; color: gray;">📅 Dated: ' . $quoteDate . '</span></div>' .
                                                    '<div><span style="color: gray; font-size: 11px;">Agreed Contract Price</span><br><strong style="font-size: 16px; color: #16a34a;">₹' . $cost . '</strong></div>' .
                                                    '</div>' .
                                                    '<div style="background: rgba(128,128,128,0.04); border-radius: 6px; padding: 10px; font-size: 12px;">' .
                                                    '<strong>Scope of Work (' . $title . '):</strong> ' . $scope .
                                                    '</div>' .
                                                    $letterActionHtml .
                                                    '</div>';
                                            }
                                            $cards .= '</div>';

                                            return new HtmlString($cards);
                                        }),
                                ]),
                        ]),

                    // 💳 Tab 5: Accounting Settlement
                    Tab::make('Accounting Settlement')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Section::make('📊 Accounting Bridge (Vendor Bills & Client Invoices)')
                                ->description('Generate and sync accounting documents (Vendor Bills and Client Invoices) with the financial ledger.')
                                ->headerActions([
                                    Action::make('viewAuditInTab')
                                        ->label(fn ($record) => $record?->maintenanceRequest?->triggeredAudit ? ('View Audit #' . $record->maintenanceRequest->triggeredAudit->audit_number) : 'View Verification Audit')
                                        ->icon('heroicon-o-clipboard-document-check')
                                        ->color('info')
                                        ->button()
                                        ->size('sm')
                                        ->visible(fn ($record) => filled($record?->maintenanceRequest?->triggered_audit_id) && (bool)$record?->maintenanceRequest?->triggeredAudit)
                                        ->url(fn ($record) => $record?->maintenanceRequest?->triggeredAudit ? \App\Filament\Resources\Operations\AuditResource::getUrl('inspect', ['record' => $record->maintenanceRequest->triggeredAudit]) : null)
                                        ->openUrlInNewTab(),

                                    Action::make('viewTicketInTab5')
                                        ->label(fn ($record) => $record?->maintenanceRequest ? ('View Ticket #' . $record->maintenanceRequest->ticket_number) : 'View Ticket')
                                        ->icon('heroicon-o-arrow-top-right-on-square')
                                        ->color('gray')
                                        ->button()
                                        ->size('sm')
                                        ->visible(fn ($record) => filled($record?->maintenance_request_id))
                                        ->url(fn ($record) => $record?->maintenanceRequest ? \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $record->maintenanceRequest]) : null)
                                        ->openUrlInNewTab(),

                                    Action::make('generateAccountingDocumentsInTab')
                                        ->label('Generate Bills & Invoices')
                                        ->icon('heroicon-o-document-currency-rupee')
                                        ->color('primary')
                                        ->button()
                                        ->size('sm')
                                        ->visible(fn ($record) => $record && $record->status === 'approved')
                                        ->disabled(function ($record) {
                                            if (!$record || $record->status !== 'approved') return true;
                                            $request = $record->maintenanceRequest;
                                            if (!$request) return true;

                                            $audit = $request->triggeredAudit;
                                            if (!$audit) return true;

                                            $isAuditApprovedOrLocked = in_array($audit->status?->value ?? (string)$audit->status, ['approved', 'completed']) || (bool)$audit->is_locked;
                                            if (!$isAuditApprovedOrLocked) return true;

                                            $isTicketClosed = in_array($request->status, [MaintenanceStatus::CLOSED, MaintenanceStatus::INVOICED]);
                                            if (!$isTicketClosed) return true;

                                            return false;
                                        })
                                        ->tooltip(function ($record) {
                                            if (!$record || $record->status !== 'approved') {
                                                return 'Quotation Approval Required: Client quotation must be approved first.';
                                            }
                                            $request = $record->maintenanceRequest;
                                            if (!$request) {
                                                return 'Operational ticket reference is missing.';
                                            }
                                            $audit = $request->triggeredAudit;
                                            if (!$audit) {
                                                return 'Verification Audit Required: Post-repair verification audit has not been triggered on ticket #' . $request->ticket_number . '.';
                                            }
                                            $isAuditApprovedOrLocked = in_array($audit->status?->value ?? (string)$audit->status, ['approved', 'completed']) || (bool)$audit->is_locked;
                                            if (!$isAuditApprovedOrLocked) {
                                                return 'Audit Approval Required: Post-repair audit #' . $audit->audit_number . ' is not yet approved/locked.';
                                            }
                                            $isTicketClosed = in_array($request->status, [MaintenanceStatus::CLOSED, MaintenanceStatus::INVOICED]);
                                            if (!$isTicketClosed) {
                                                return 'Ticket Closure Required: Maintenance ticket #' . $request->ticket_number . ' must be verified and closed before generating final accounting documents.';
                                            }
                                            return 'Compile vendor bills and client invoices in the financial ledger.';
                                        })
                                        ->requiresConfirmation()
                                        ->modalHeading('Generate Accounting Documents')
                                        ->modalDescription('This will create Vendor Bills in the Accounting module for each vendor trade quote and create Invoices for the paying party (Owner / Tenant).')
                                        ->modalSubmitActionLabel('Confirm Generation')
                                        ->action(function ($record, $livewire) {
                                            $request = $record->maintenanceRequest;
                                            if (!$request) return;

                                            // Check audit approval & ticket closure
                                            $audit = $request->triggeredAudit;
                                            $isAuditApproved = $audit && (in_array($audit->status?->value ?? (string)$audit->status, ['approved', 'completed']) || (bool)$audit->is_locked);
                                            $isTicketClosed = in_array($request->status, [MaintenanceStatus::CLOSED, MaintenanceStatus::INVOICED]);

                                            if (!$isAuditApproved || !$isTicketClosed) {
                                                Notification::make()
                                                    ->title('Prerequisites Incomplete')
                                                    ->body('Post-repair verification audit must be approved/locked AND ticket must be closed before generating final accounting documents.')
                                                    ->warning()
                                                    ->persistent()
                                                    ->send();
                                                return;
                                            }

                                            $billingService = app(MaintenanceBillingService::class);

                                            // 1. Generate Vendor Bills for all trades
                                            $bills = $billingService->createAllVendorBillsForRequest($request);

                                            // 2. Generate Client Invoices
                                            $payer = $request->payer_type?->value ?? (string) $request->payer_type;
                                            if ($payer === 'tenant' || $payer === 'dwelly_invoice_tenant') {
                                                $billingService->createMaintenanceInvoice($request, 'tenant_invoice');
                                            } elseif ($payer === 'owner' || $payer === 'dwelly_invoice_owner') {
                                                $billingService->createMaintenanceInvoice($request, 'owner_invoice');
                                            } elseif ($payer === 'split' || $payer === 'dwelly_invoice_split') {
                                                if ($record->owner_amount > 0) {
                                                    $billingService->createMaintenanceInvoice($request, 'owner_invoice');
                                                }
                                                if ($record->tenant_amount > 0) {
                                                    $billingService->createMaintenanceInvoice($request, 'tenant_invoice');
                                                }
                                            }

                                            $request->update([
                                                'status' => MaintenanceStatus::INVOICED,
                                            ]);

                                            Notification::make()
                                                ->title('Accounting Documents Generated')
                                                ->body("Generated " . count($bills) . " Vendor Bill(s) and Client Invoices successfully in the Accounting module.")
                                                ->success()
                                                ->send();

                                            if (method_exists($livewire, 'refreshFormData')) {
                                                $livewire->refreshFormData([]);
                                            }
                                            $livewire->dispatch('$refresh');
                                        }),
                                ])
                                ->schema([
                                    Placeholder::make('settlement_info')
                                        ->label('')
                                        ->columnSpanFull()
                                        ->content(function ($record) {
                                            if (!$record) return '';

                                            $request = $record->maintenanceRequest;
                                            if (!$request) return '';

                                            $billId = $request->bill_id ? "#{$request->bill_id}" : 'Not Generated';
                                            $ownerInv = $request->owner_invoice_id ? "#{$request->owner_invoice_id}" : 'Not Generated';
                                            $tenantInv = $request->tenant_invoice_id ? "#{$request->tenant_invoice_id}" : 'Not Generated';

                                            $totalVendor = number_format((float)$request->total_vendor_cost, 2);
                                            $totalClient = number_format((float)$record->total_amount, 2);
                                            $margin = number_format((float)($record->total_amount - $request->total_vendor_cost), 2);

                                            // Audit & Ticket Prerequisite Banner
                                            $audit = $request->triggeredAudit;
                                            $isAuditApproved = $audit && (in_array($audit->status?->value ?? (string)$audit->status, ['approved', 'completed']) || (bool)$audit->is_locked);
                                            $isTicketClosed = in_array($request->status, [MaintenanceStatus::CLOSED, MaintenanceStatus::INVOICED]);

                                            $ticketUrl = \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $request]);

                                            if (!$audit) {
                                                $prereqBanner = '<div style="background: rgba(234, 179, 8, 0.08); border: 1px dashed rgba(234, 179, 8, 0.35); border-radius: 8px; padding: 14px 18px; margin-bottom: 16px; font-size: 13px; color: #a16207; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">' .
                                                    '<div><strong>⚠️ Audit Required:</strong> Post-repair verification audit has not been initiated yet. Complete repairs in ticket #' . e($request->ticket_number) . ' to trigger audit.</div>' .
                                                    '<a href="' . e($ticketUrl) . '" target="_blank" style="padding: 5px 12px; background: #a16207; color: white; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 12px;">Open Ticket #' . e($request->ticket_number) . ' &rarr;</a>' .
                                                    '</div>';
                                            } elseif (!$isAuditApproved) {
                                                $auditUrl = \App\Filament\Resources\Operations\AuditResource::getUrl('inspect', ['record' => $audit]);
                                                $statusName = e($audit->status?->getLabel() ?? ucfirst((string)$audit->status));
                                                $prereqBanner = '<div style="background: rgba(37, 99, 235, 0.06); border: 1px dashed rgba(37, 99, 235, 0.35); border-radius: 8px; padding: 14px 18px; margin-bottom: 16px; font-size: 13px; color: #1e40af; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">' .
                                                    '<div><strong>⏳ Audit Inspection Pending:</strong> Audit #' . e($audit->audit_number) . ' is currently <strong>' . $statusName . '</strong>. Inspector approval is required.</div>' .
                                                    '<a href="' . e($auditUrl) . '" target="_blank" style="padding: 5px 12px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 12px;">Inspect Audit #' . e($audit->audit_number) . ' &rarr;</a>' .
                                                    '</div>';
                                            } elseif (!$isTicketClosed) {
                                                $prereqBanner = '<div style="background: rgba(234, 179, 8, 0.08); border: 1px dashed rgba(234, 179, 8, 0.35); border-radius: 8px; padding: 14px 18px; margin-bottom: 16px; font-size: 13px; color: #a16207; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">' .
                                                    '<div><strong>🔒 Ticket Closure Required:</strong> Audit #' . e($audit->audit_number) . ' is approved! Please close ticket #' . e($request->ticket_number) . ' to lock the audit and unlock billing.</div>' .
                                                    '<a href="' . e($ticketUrl) . '" target="_blank" style="padding: 5px 12px; background: #a16207; color: white; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 12px;">Close Ticket #' . e($request->ticket_number) . ' &rarr;</a>' .
                                                    '</div>';
                                            } else {
                                                $auditUrl = \App\Filament\Resources\Operations\AuditResource::getUrl('inspect', ['record' => $audit]);
                                                $prereqBanner = '<div style="background: rgba(22, 163, 74, 0.06); border: 1px solid rgba(22, 163, 74, 0.25); border-radius: 8px; padding: 14px 18px; margin-bottom: 16px; font-size: 13px; color: #15803d; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">' .
                                                    '<div><strong>✅ Audit Approved & Ticket Closed:</strong> Audit #' . e($audit->audit_number) . ' is permanently locked. Billing & Invoicing is fully authorized.</div>' .
                                                    '<a href="' . e($auditUrl) . '" target="_blank" style="padding: 5px 12px; background: #16a34a; color: white; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 12px;">View Locked Audit &rarr;</a>' .
                                                    '</div>';
                                            }

                                            return new HtmlString(
                                                $prereqBanner .
                                                '<div style="background-color: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.2); padding: 18px; border-radius: 8px; font-size: 13px;">' .
                                                '<div style="font-weight: 700; font-size: 15px; margin-bottom: 12px;">📊 Project Financial Ledger Summary</div>' .
                                                '<div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px;">' .
                                                '<div><span style="color: gray;">Vendor Payables (Cost):</span><br><strong style="font-size: 16px;">₹' . $totalVendor . '</strong></div>' .
                                                '<div><span style="color: gray;">Client Receivables (Price):</span><br><strong style="font-size: 16px; color: #2563eb;">₹' . $totalClient . '</strong></div>' .
                                                '<div><span style="color: gray;">Dwelly Margin / Fee:</span><br><strong style="font-size: 16px; color: #16a34a;">₹' . $margin . '</strong></div>' .
                                                '</div>' .
                                                '<hr style="margin: 16px 0; border: 0; border-top: 1px solid rgba(128,128,128,0.2);">' .
                                                '<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">' .
                                                '<div><span style="color: gray;">Generated Vendor Bill:</span><br><strong>' . e($billId) . '</strong></div>' .
                                                '<div><span style="color: gray;">Generated Client Invoice:</span><br><strong>' . e($ownerInv ?: $tenantInv) . '</strong></div>' .
                                                '</div>' .
                                                '</div>'
                                            );
                                        }),
                                ]),
                        ]),
                ]),
        ]);
    }
}
