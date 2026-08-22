<?php

namespace App\Filament\Resources\Operations\MaintenanceRequestResource\Schemas;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Property\Models\Property;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class MaintenanceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)
                ->columnSpanFull()
                ->schema([
                    // Main Left Column (2 Cols)
                    Grid::make(1)
                        ->columnSpan(2)
                        ->schema([
                            Placeholder::make('maintenance_request_locked_banner')
                                ->hiddenLabel()
                                ->columnSpanFull()
                                ->visible(fn ($record) => (bool) ($record && $record->isLocked()))
                                ->content(function ($record) {
                                    $quote = $record->currentClientQuote ?? $record->clientQuotes()->where('status', '!=', 'archived')->latest()->first();
                                    $quoteNumber = $quote ? " #{$quote->quote_number}" : '';

                                    return new HtmlString(
                                        '<div style="background-color: rgba(30, 58, 138, 0.06); border: 1px solid rgba(37, 99, 235, 0.25); border-left: 4px solid #2563eb; padding: 14px 18px; border-radius: 8px; margin-bottom: 8px; font-size: 13px; color: #1e3a8a; display: flex; align-items: flex-start; gap: 12px;">' .
                                        '<span style="font-size: 20px; line-height: 1;">🔒</span>' .
                                        '<div>' .
                                        '<strong style="font-size: 14px; display: block; margin-bottom: 2px;">Maintenance Request Locked</strong>' .
                                        '<span>The Maintenance Quotation' . e($quoteNumber) . ' for this ticket has been approved. Target property, issue details, defect items, and financial responsibility are permanently locked to preserve contract and billing integrity. Track ongoing repairs in the Repair Execution tab.</span>' .
                                        '</div>' .
                                        '</div>'
                                    );
                                }),

                            // 📍 Section 1: Target Property & Context
                            Section::make('📍 Target Property & Context')
                                ->schema([
                                    Select::make('property_id')
                                        ->label('Target Property')
                                        ->options(fn () => Property::all()->mapWithKeys(fn ($p) => [
                                            $p->id => ($p->code ? "{$p->code} - " : '') . ($p->building_name ?: "Property #{$p->id}")
                                        ]))
                                        ->searchable()
                                        ->required()
                                        ->live()
                                        ->disabled(fn ($record) => (bool) ($record && $record->isLocked()))
                                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                            if ($state) {
                                                $property = Property::find($state);
                                                $hasActiveTenant = $property && $property->agreements()->where('status', 'active')->whereHas('tenants')->exists();
                                                if (!$hasActiveTenant) {
                                                    if ($get('payer_type') === 'tenant') {
                                                        $set('payer_type', null);
                                                        $set('is_direct_vendor', null);
                                                    }
                                                    if ($get('reporter_type') === 'tenant') {
                                                        $set('reporter_type', 'staff');
                                                    }
                                                }
                                            }
                                        })
                                        ->helperText(function ($record) {
                                            if ($record && $record->isLocked()) {
                                                return '🔒 Locked: Target property cannot be changed because the quotation has been approved.';
                                            }
                                            return 'Select the property where maintenance is required.';
                                        }),

                                    Placeholder::make('property_summary')
                                        ->label('')
                                        ->columnSpanFull()
                                        ->content(function (Get $get) {
                                            $propertyId = $get('property_id');
                                            if (!$propertyId) {
                                                return new HtmlString(
                                                    '<div style="background-color: rgba(128, 128, 128, 0.05); border: 1px dashed rgba(128, 128, 128, 0.25); border-radius: 8px; padding: 14px; text-align: center; color: rgba(128, 128, 128, 0.8); font-size: 13px;">' .
                                                    '📍 Select a target property to view owner, tenant, and active ticket context.' .
                                                    '</div>'
                                                );
                                            }

                                            $property = Property::with(['mous.party', 'agreements.tenants', 'maintenanceRequests'])->find($propertyId);
                                            if (!$property) return '';

                                            $ownerName = $property->mous->first()?->party?->display_name ?? 'Not Specified';
                                            $latestAgreement = $property->agreements()->where('status', 'active')->first();
                                            $tenantName = $latestAgreement?->tenants->first()?->display_name ?? 'Vacant / No Active Tenant';
                                            $openTicketsCount = $property->maintenanceRequests()->whereNotIn('status', ['closed', 'cancelled'])->count();
                                            $code = $property->code ? e($property->code) . ' - ' : '';
                                            $buildingName = e($property->building_name ?: 'Property #' . $property->id);

                                            return new HtmlString(
                                                '<div style="background-color: rgba(37, 99, 235, 0.04); border: 1px solid rgba(37, 99, 235, 0.15); border-radius: 8px; padding: 16px; font-size: 13px; color: inherit;">' .
                                                '<div style="font-weight: 700; font-size: 14px; margin-bottom: 8px; color: inherit; display: flex; align-items: center; justify-content: space-between;">' .
                                                '<span>📍 Property Context Panel</span>' .
                                                '<span style="padding: 2px 8px; font-size: 10px; border-radius: 4px; background: #2563eb; color: #fff; font-weight: 600;">ACTIVE</span>' .
                                                '</div>' .
                                                '<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">' .
                                                '<div><span style="color: rgba(128, 128, 128, 0.8); font-size: 11px;">Property</span><br><strong>' . $code . $buildingName . '</strong></div>' .
                                                '<div><span style="color: rgba(128, 128, 128, 0.8); font-size: 11px;">Owner</span><br><strong>' . e($ownerName) . '</strong></div>' .
                                                '<div><span style="color: rgba(128, 128, 128, 0.8); font-size: 11px;">Tenant</span><br><strong>' . e($tenantName) . '</strong></div>' .
                                                '<div><span style="color: rgba(128, 128, 128, 0.8); font-size: 11px;">Open Maintenance</span><br><strong style="color: #d97706;">' . $openTicketsCount . ' Active Ticket(s)</strong></div>' .
                                                '</div>' .
                                                '</div>'
                                            );
                                        }),
                                ]),

                            // 🛠 Section 2: Issue Details
                            Section::make('🛠 Issue Details')
                                ->description(fn ($record) => (bool) ($record && $record->isLocked()) ? '🔒 Issue details are locked because the maintenance quotation has been approved.' : null)
                                ->columns(2)
                                ->schema([
                                    TextInput::make('title')
                                        ->label('Issue Title')
                                        ->placeholder('e.g. Kitchen Pipe Leakage / Master Bedroom AC Not Cooling')
                                        ->required()
                                        ->disabled(fn ($record) => (bool) ($record && $record->isLocked()))
                                        ->columnSpanFull(),

                                    Select::make('priority')
                                        ->label('Priority')
                                        ->options([
                                            'low' => '🟢 Low',
                                            'medium' => '🟡 Medium',
                                            'high' => '🟠 High',
                                            'emergency' => '🔴 Emergency',
                                        ])
                                        ->default('medium')
                                        ->required()
                                        ->disabled(fn ($record) => (bool) ($record && $record->isLocked())),

                                    Select::make('reporter_type')
                                        ->label('Reported By')
                                        ->options(function (Get $get, $record) {
                                            $propertyId = $get('property_id') ?? $record?->property_id;
                                            $hasActiveTenant = false;
                                            if ($propertyId) {
                                                $property = Property::find($propertyId);
                                                if ($property) {
                                                    $hasActiveTenant = $property->agreements()
                                                        ->where('status', 'active')
                                                        ->whereHas('tenants')
                                                        ->exists();
                                                }
                                            }

                                            $options = [
                                                'owner' => 'Owner',
                                                'staff' => 'Dwelly Staff',
                                            ];

                                            if ($hasActiveTenant) {
                                                $options['tenant'] = 'Tenant';
                                            }

                                            return $options;
                                        })
                                        ->default('staff')
                                        ->required()
                                        ->disabled(fn ($record) => (bool) ($record && $record->isLocked())),

                                    Textarea::make('description')
                                        ->label('Detailed Description')
                                        ->placeholder('Describe the issue, specific damage symptoms, affected areas, or emergency instructions...')
                                        ->rows(5)
                                        ->required()
                                        ->disabled(fn ($record) => (bool) ($record && $record->isLocked()))
                                        ->columnSpanFull(),
                                ]),

                            // 💰 Section 3: Repair Decision & Financial Responsibility
                            Section::make('💰 Repair Decision & Financial Responsibility')
                                ->description(function ($record) {
                                    $hasActiveQuote = (bool) ($record && ($record->current_client_quote_id || $record->clientQuotes()->where('status', '!=', 'archived')->exists()));
                                    if ($hasActiveQuote) {
                                        return '🔒 Financial responsibility and execution route are locked because an official Maintenance Quotation has been created for this ticket.';
                                    }

                                    return 'Determine financial responsibility and execution route for this maintenance request.';
                                })
                                ->headerActions([
                                    Action::make('unlockFinancialDecision')
                                        ->label('Unlock & Archive Quotation')
                                        ->icon('heroicon-o-lock-open')
                                        ->color('danger')
                                        ->size('sm')
                                        ->button()
                                        ->visible(function ($record) {
                                            if (! $record) {
                                                return false;
                                            }

                                            return (bool) ($record->current_client_quote_id || $record->clientQuotes()->where('status', '!=', 'archived')->exists());
                                        })
                                        ->disabled(function ($record) {
                                            if (! $record) {
                                                return false;
                                            }
                                            if ($record->isQuotationApproved()) {
                                                return true;
                                            }
                                            $quote = $record->currentClientQuote ?? $record->clientQuotes()->where('status', '!=', 'archived')->latest()->first();
                                            $hasWorkOrders = ($quote && ! empty($quote->awarded_vendor_quote_ids))
                                                || $record->vendorQuotes()->where('is_awarded', true)->exists()
                                                || in_array($record->status, [MaintenanceStatus::IN_PROGRESS, MaintenanceStatus::WORK_COMPLETED, MaintenanceStatus::CLOSED, MaintenanceStatus::CANCELLED]);

                                            return $hasWorkOrders;
                                        })
                                        ->tooltip(function ($record) {
                                            if (! $record) {
                                                return null;
                                            }
                                            if ($record->isQuotationApproved()) {
                                                return 'Cannot unlock financial responsibility after quotation is approved.';
                                            }
                                            $quote = $record->currentClientQuote ?? $record->clientQuotes()->where('status', '!=', 'archived')->latest()->first();
                                            $hasWorkOrders = ($quote && ! empty($quote->awarded_vendor_quote_ids))
                                                || $record->vendorQuotes()->where('is_awarded', true)->exists()
                                                || in_array($record->status, [MaintenanceStatus::IN_PROGRESS, MaintenanceStatus::WORK_COMPLETED, MaintenanceStatus::CLOSED, MaintenanceStatus::CANCELLED]);

                                            return $hasWorkOrders ? 'Cannot unlock financial responsibility after Work Orders have been issued.' : 'Archive active quotation and unlock Who Pays / Execution Route';
                                        })
                                        ->requiresConfirmation()
                                        ->modalHeading('⚠️ Unlock Financial Responsibility & Archive Quotation?')
                                        ->modalDescription(function ($record) {
                                            $quote = $record?->currentClientQuote ?? $record?->clientQuotes()->where('status', '!=', 'archived')->latest()->first();
                                            $quoteNum = $quote ? "Quotation #{$quote->quote_number}" : 'the active Quotation';

                                            return "Unlocking will archive {$quoteNum} and reset the financial decision lock on this ticket, allowing you to change Who Pays? and the Execution Route. Are you sure you want to proceed?";
                                        })
                                        ->modalSubmitActionLabel('Yes, Archive Quotation & Unlock')
                                        ->action(function ($record, $livewire) {
                                            if (! $record) {
                                                return;
                                            }

                                            try {
                                                app(\App\Domain\Maintenance\Services\MaintenanceBillingService::class)->archiveQuotationAndUnlock($record);

                                                Notification::make()
                                                    ->title('Financial Responsibility Unlocked')
                                                    ->body('Active quotation has been archived. You can now modify Who Pays? and the Execution Route.')
                                                    ->success()
                                                    ->send();

                                                $livewire->redirect(
                                                    \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $record])
                                                );
                                            } catch (\Throwable $e) {
                                                Notification::make()
                                                    ->title('Unlock Failed')
                                                    ->body($e->getMessage())
                                                    ->danger()
                                                    ->send();
                                            }
                                        }),
                                ])
                                ->columns(2)
                                ->schema([
                                    Select::make('payer_type')
                                        ->label('Who Pays?')
                                        ->placeholder('Select Financial Responsibility')
                                        ->options(function (Get $get, $record) {
                                            $propertyId = $get('property_id') ?? $record?->property_id;

                                            $hasActiveTenant = false;
                                            if ($propertyId) {
                                                $property = Property::find($propertyId);
                                                if ($property) {
                                                    $hasActiveTenant = $property->agreements()
                                                        ->where('status', 'active')
                                                        ->whereHas('tenants')
                                                        ->exists();
                                                }
                                            }

                                            $options = [
                                                'owner' => '👤 Owner Pays',
                                            ];

                                            if ($hasActiveTenant) {
                                                $options['tenant'] = '🏠 Tenant Pays';
                                            }

                                            $options['dwelly'] = '🏢 Dwelly Absorbed (Internal Expense)';

                                            return $options;
                                        })
                                        ->required()
                                        ->live()
                                        ->disabled(fn ($record) => (bool) ($record && ($record->current_client_quote_id || $record->clientQuotes()->where('status', '!=', 'archived')->exists())))
                                        ->dehydrated()
                                        ->afterStateUpdated(function ($state, Set $set) {
                                            if (in_array($state, ['dwelly', PayerType::DWELLY->value, PayerType::DWELLY_DIRECT_ABSORBED->value])) {
                                                $set('is_direct_vendor', 0);
                                            } elseif (blank($state)) {
                                                $set('is_direct_vendor', null);
                                            }
                                        })
                                        ->helperText(function (Get $get, $record) {
                                            if ($record && ($record->current_client_quote_id || $record->clientQuotes()->where('status', '!=', 'archived')->exists())) {
                                                $quote = $record->currentClientQuote ?? $record->clientQuotes()->where('status', '!=', 'archived')->latest()->first();
                                                $quoteRef = $quote ? " (#{$quote->quote_number})" : '';

                                                return "🔒 Locked: Financial responsibility cannot be changed because Maintenance Quotation{$quoteRef} is active. Use 'Unlock & Archive Quotation' above to change.";
                                            }

                                            $propertyId = $get('property_id') ?? $record?->property_id;
                                            if ($propertyId) {
                                                $property = Property::find($propertyId);
                                                $hasActiveTenant = $property && $property->agreements()->where('status', 'active')->whereHas('tenants')->exists();
                                                if (! $hasActiveTenant) {
                                                    return 'Property is currently vacant (no active tenant). Only Owner or Dwelly can be selected.';
                                                }
                                            }

                                            return 'Select who is financially responsible for this repair.';
                                        }),

                                    Radio::make('is_direct_vendor')
                                        ->label('Execution Route')
                                        ->visible(fn (Get $get) => filled($get('payer_type')))
                                        ->options(function (Get $get) {
                                            $payer = $get('payer_type');
                                            if (in_array($payer, ['dwelly', PayerType::DWELLY->value, PayerType::DWELLY_DIRECT_ABSORBED->value])) {
                                                return [
                                                    0 => 'Dwelly Coordinates (Internal Expense)',
                                                ];
                                            }

                                            return [
                                                0 => 'Dwelly Coordinates (Multi-Vendor Sourcing & Invoicing)',
                                                1 => 'Owner/Tenant Repairs Directly (Dwelly audits only)',
                                            ];
                                        })
                                        ->formatStateUsing(fn ($state) => $state === null ? 0 : ($state ? 1 : 0))
                                        ->dehydrateStateUsing(fn ($state) => (bool) $state)
                                        ->default(0)
                                        ->required()
                                        ->live()
                                        ->disabled(fn ($record) => (bool) ($record && ($record->current_client_quote_id || $record->clientQuotes()->where('status', '!=', 'archived')->exists())))
                                        ->dehydrated()
                                        ->helperText(function (Get $get, $record) {
                                            if ($record && ($record->current_client_quote_id || $record->clientQuotes()->where('status', '!=', 'archived')->exists())) {
                                                return '🔒 Locked: Execution route cannot be modified because an active Maintenance Quotation is linked.';
                                            }

                                            return 'Choose whether Dwelly manages vendors or client repairs directly.';
                                        }),

                                    Placeholder::make('decision_route_banner')
                                        ->label('')
                                        ->columnSpanFull()
                                        ->content(function (Get $get, $record) {
                                            $payer = $get('payer_type');
                                            $isDirect = $get('is_direct_vendor');
                                            $hasQuotation = (bool) ($record && ($record->current_client_quote_id || $record->clientQuotes()->where('status', '!=', 'archived')->exists()));

                                            if (blank($payer)) {
                                                return new HtmlString('<div style="font-size: 13px; color: #d97706; background-color: rgba(217, 119, 6, 0.08); border-left: 4px solid #d97706; padding: 10px 14px; border-radius: 4px;">⚠️ <strong>Decision Required:</strong> Please assign financial responsibility above before preparing quotations or starting repairs.</div>');
                                            }

                                            if ($hasQuotation) {
                                                $quote = $record->currentClientQuote ?? $record->clientQuotes()->where('status', '!=', 'archived')->latest()->first();
                                                $quoteNum = $quote ? $quote->quote_number : 'Active';

                                                return new HtmlString('<div style="font-size: 13px; color: #1e3a8a; background-color: rgba(30, 58, 138, 0.06); border-left: 4px solid #2563eb; padding: 10px 14px; border-radius: 4px;">🔒 <strong>Financial Responsibility Locked:</strong> Maintenance Quotation <strong>#' . e($quoteNum) . '</strong> is created. Financial responsibility and execution route are locked to ensure billing integrity. To reassign, click <strong>Unlock & Archive Quotation</strong> in the section header.</div>');
                                            }

                                            if ($isDirect) {
                                                return new HtmlString('<div style="font-size: 13px; color: #2563eb; background-color: rgba(37, 99, 235, 0.06); border-left: 4px solid #2563eb; padding: 10px 14px; border-radius: 4px;">🛠 <strong>Direct Repair Route Active:</strong> The paying party will hire and pay their contractor directly. Dwelly will track defect items and conduct the <strong>Post-Repair Verification Audit</strong> once completed.</div>');
                                            }

                                            return new HtmlString('<div style="font-size: 13px; color: #16a34a; background-color: rgba(22, 163, 74, 0.06); border-left: 4px solid #16a34a; padding: 10px 14px; border-radius: 4px;">🏢 <strong>Dwelly-Coordinated Route Active:</strong> Dwelly will collect multi-vendor trade estimates, prepare a formal client quotation, secure approval proof, and handle accounting settlement.</div>');
                                        }),
                                ]),

                            // 💳 Financial & Quotations Bridge Section (Visible when a Quotation exists)
                            Section::make('💳 Financial Quotations & Settlement Job')
                                ->visible(fn (Get $get, $record) => $record && !$get('is_direct_vendor') && (bool)($record->currentClientQuote ?? $record->clientQuotes()->where('status', '!=', 'archived')->first()))
                                ->schema([
                                    Placeholder::make('financial_workflow_bridge')
                                        ->label('')
                                        ->columnSpanFull()
                                        ->content(function ($record) {
                                            if (!$record) return '';

                                            $quote = $record->currentClientQuote ?? $record->clientQuotes()->latest()->first();
                                            if (!$quote) return '';

                                            $quoteUrl = \App\Filament\Resources\Billing\MaintenanceQuotationResource::getUrl('edit', ['record' => $quote]);
                                            $quoteStatus = e(ucfirst($quote->status));
                                            $color = match($quote->status) {
                                                'approved' => '#16a34a',
                                                'rejected' => '#dc2626',
                                                'pending_approval' => '#d97706',
                                                default => '#6b7280',
                                            };
                                            $totalVendor = number_format((float)$record->total_vendor_cost, 2);
                                            $totalClient = number_format((float)$quote->total_amount, 2);
                                            $vendorQuotesCount = $record->vendorQuotes()->count();

                                            $pendingAlert = '';
                                            if ($quote->status !== 'approved') {
                                                $pendingAlert = '<div style="background-color: rgba(217, 119, 6, 0.08); border-left: 4px solid #d97706; padding: 10px 14px; border-radius: 4px; margin-bottom: 12px; font-size: 13px; color: #b45309;">' .
                                                    '<strong>⏳ Quotation Approval Pending:</strong> Physical repairs are locked until client approval proof is recorded in the Billing & Finance module.' .
                                                    '</div>';
                                            }

                                            return new HtmlString(
                                                '<div style="background-color: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.2); padding: 18px; border-radius: 8px;">' .
                                                $pendingAlert .
                                                '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">' .
                                                '<div><strong style="font-size: 15px;">Quotation #' . e($quote->quote_number) . '</strong> <span style="margin-left: 8px; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background-color: ' . $color . '; color: #fff; text-transform: uppercase;">' . $quoteStatus . '</span></div>' .
                                                '<a href="' . e($quoteUrl) . '" target="_blank" style="display: inline-flex; align-items: center; padding: 6px 14px; background-color: #2563eb; color: #fff; font-weight: 600; font-size: 12px; border-radius: 6px; text-decoration: none;">Open Financial Workflow &rarr;</a>' .
                                                '</div>' .
                                                '<div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; font-size: 13px;">' .
                                                '<div><span style="color: gray;">Vendor Estimates (' . $vendorQuotesCount . ' trades):</span><br><strong style="font-size: 15px;">₹' . $totalVendor . '</strong></div>' .
                                                '<div><span style="color: gray;">Client Quoted Price:</span><br><strong style="font-size: 15px; color: #2563eb;">₹' . $totalClient . '</strong></div>' .
                                                '<div><span style="color: gray;">Payer Share (Owner / Tenant):</span><br><strong>₹' . number_format((float)$quote->owner_amount, 2) . ' / ₹' . number_format((float)$quote->tenant_amount, 2) . '</strong></div>' .
                                                '</div>' .
                                                '</div>'
                                            );
                                        }),
                                ]),

                            // 🔍 Verification Audit Card (Visible only when an audit has been triggered)
                            Section::make('🔍 Post-Repair Verification Audit')
                                ->visible(fn ($record) => $record && filled($record->triggered_audit_id))
                                ->schema([
                                    Placeholder::make('linked_audit')
                                        ->label('')
                                        ->columnSpanFull()
                                        ->content(function ($record) {
                                            if (!$record || !$record->triggered_audit_id || !$record->triggeredAudit) {
                                                return new HtmlString(
                                                    '<div style="background-color: rgba(128, 128, 128, 0.03); border: 1px dashed rgba(128, 128, 128, 0.25); padding: 16px; border-radius: 8px; color: inherit; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">' .
                                                        '<div>' .
                                                        '<div style="font-weight: 700; font-size: 14px; color: inherit;">No Verification Audit Triggered Yet</div>' .
                                                        '<div style="font-size: 13px; color: rgba(128, 128, 128, 0.85); margin-top: 2px;">Trigger post-repair audit after repairs are completed to conduct quality inspection.</div>' .
                                                        '</div>' .
                                                        '</div>'
                                                );
                                            }

                                            $audit = $record->triggeredAudit;
                                            $statusLabel = e($audit->status?->getLabel() ?? (is_string($audit->status) ? ucfirst($audit->status) : ($audit->status?->value ?? 'Pending')));
                                            $auditNumber = e($audit->audit_number);
                                            $typeLabel = e($audit->audit_type?->getLabel() ?? 'Maintenance Verification');
                                            $inspectorName = e($audit->inspector?->name ?? $record->assignedInspector?->name ?? 'Unassigned');

                                            try {
                                                $inspectUrl = \App\Filament\Resources\Operations\AuditResource::getUrl('inspect', ['record' => $audit]);
                                            } catch (\Throwable $e) {
                                                $inspectUrl = url("/operations/audits/{$audit->id}/inspect");
                                            }

                                            try {
                                                $editUrl = \App\Filament\Resources\Operations\AuditResource::getUrl('edit', ['record' => $audit]);
                                            } catch (\Throwable $e) {
                                                $editUrl = url("/operations/audits/{$audit->id}/edit");
                                            }

                                            return new HtmlString(
                                                '<div style="background-color: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.2); padding: 16px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; color: inherit; flex-wrap: wrap; gap: 12px;">' .
                                                    '<div>' .
                                                    '<div style="font-weight: 700; font-size: 15px; color: inherit;">Audit #' . $auditNumber . '</div>' .
                                                    '<div style="font-size: 13px; color: rgba(128, 128, 128, 0.85); margin-top: 4px;">Type: <strong>' . $typeLabel . '</strong> | Status: <span style="padding: 2px 8px; font-size: 11px; border-radius: 4px; font-weight: 600; background-color: #dbeafe; color: #1e40af; text-transform: uppercase;">' . $statusLabel . '</span></div>' .
                                                    '<div style="font-size: 12px; color: rgba(128, 128, 128, 0.7); margin-top: 4px;">Assigned Inspector: <strong>' . $inspectorName . '</strong></div>' .
                                                    '</div>' .
                                                    '<div style="display: flex; gap: 8px; align-items: center;">' .
                                                    '<a href="' . e($editUrl) . '" target="_blank" style="display: inline-flex; align-items: center; padding: 6px 12px; background-color: rgba(37, 99, 235, 0.1); color: #2563eb; font-weight: 600; font-size: 12px; border-radius: 6px; text-decoration: none;">View Audit &rarr;</a>' .
                                                    '<a href="' . e($inspectUrl) . '" target="_blank" style="display: inline-flex; align-items: center; padding: 6px 14px; background-color: #2563eb; color: #ffffff; font-weight: 600; font-size: 12px; border-radius: 6px; text-decoration: none;">Inspect &rarr;</a>' .
                                                    '</div>' .
                                                    '</div>'
                                            );
                                        }),
                                ]),

                            // 🧾 Direct Repair Settlement (Visible when Direct)
                            Section::make('Direct Repair Settlement')
                                ->visible(fn (Get $get) => (bool) $get('is_direct_vendor'))
                                ->columns(2)
                                ->schema([
                                    Placeholder::make('direct_settlement_notice')
                                        ->columnSpanFull()
                                        ->content(new HtmlString('<div style="background-color: rgba(37, 99, 235, 0.05); border-left: 4px solid #2563eb; padding: 10px 14px; border-radius: 4px; font-size: 13px;"><strong>Direct Repair Mode:</strong> Client pays vendor directly. Track payment proof below and ensure post-repair audit is completed.</div>')),

                                    TextInput::make('direct_payment_reference')
                                        ->label('Direct Payment / Vendor Ref')
                                        ->placeholder('e.g. UPI Ref / Receipt # / Vendor Contact'),

                                    Textarea::make('direct_payment_notes')
                                        ->label('Settlement Notes')
                                        ->rows(2)
                                        ->columnSpanFull(),

                                    SpatieMediaLibraryFileUpload::make('direct_payment_receipts')
                                        ->collection('direct_payment_receipts')
                                        ->multiple()
                                        ->panelLayout('grid')
                                        ->imagePreviewHeight('140')
                                        ->reorderable()
                                        ->openable()
                                        ->downloadable()
                                        ->previewable()
                                        ->label('Direct Payment Receipts / Invoices')
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    // Right Sidebar (1 Col)
                    Grid::make(1)
                        ->columnSpan(1)
                        ->schema([
                            // 👤 Internal Assignment Section
                            Section::make('👤 Ticket Status & Assignment')
                                ->schema([
                                    Placeholder::make('status_header_badge')
                                        ->label('Operational Status')
                                        ->content(function ($record) {
                                            $status = $record?->status ?? MaintenanceStatus::SUBMITTED;
                                            $label = e($status->getLabel());
                                            $lockedBadge = ($record && $record->isLocked())
                                                ? ' <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">🔒 Locked</span>'
                                                : '';

                                            return new HtmlString("<div class=\"flex items-center gap-1.5 flex-wrap\"><span class=\"inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200\">{$label}</span>{$lockedBadge}</div>");
                                        }),

                                    Placeholder::make('quotation_status_badge')
                                        ->label('Quotation Status')
                                        ->visible(fn (Get $get, $record) => $record && !$get('is_direct_vendor'))
                                        ->content(function ($record) {
                                            $quote = $record->currentClientQuote ?? $record->clientQuotes()->latest()->first();
                                            if (!$quote) {
                                                return new HtmlString('<span class="text-xs text-gray-500 font-medium">Not Created Yet</span>');
                                            }
                                            if ($quote->status === 'approved') {
                                                return new HtmlString('<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200">✅ Approved</span>');
                                            }
                                            if ($quote->status === 'rejected') {
                                                return new HtmlString('<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200">❌ Rejected</span>');
                                            }
                                            return new HtmlString('<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 animate-pulse">⏳ Pending Approval</span>');
                                        }),

                                    Placeholder::make('ticket_info')
                                        ->label('')
                                        ->content(function ($record) {
                                            $ticketNum = $record?->ticket_number ?? 'Generated after creation';
                                            return new HtmlString("<div class=\"text-xs text-gray-500\">Ticket #: <strong>{$ticketNum}</strong></div>");
                                        }),

                                    Select::make('assigned_inspector_id')
                                        ->label('Assigned Inspector / Staff')
                                        ->options(fn () => \App\Models\User::pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->nullable()
                                        ->default(auth()->id())
                                        ->helperText('Field staff responsible for coordinating and inspecting this ticket.'),
                                ]),

                            // ✍️ Client Repair Acceptance Summary & Proof
                            Section::make('✍️ Client Repair Acceptance')
                                ->visible(fn ($record) => (bool) ($record && ($record->hasClientAcceptance() || $record->isWorkCompleted())))
                                ->schema([
                                    View::make('filament.forms.components.client-acceptance-summary-card'),
                                ]),
                        ]),
                ]),
        ]);
    }
}
