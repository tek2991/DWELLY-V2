<?php

namespace App\Filament\Resources\Operations\MaintenanceRequestResource\Tables;

use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Services\MaintenanceAuditTriggerService;
use App\Domain\Maintenance\Services\MaintenanceSettlementService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class MaintenanceRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('Ticket #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('property.building_name')
                    ->label('Property')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->property?->code ? "{$record->property->code} - {$record->property->building_name}" : ($record->property?->building_name ?? 'Property')),

                TextColumn::make('title')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('priority')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('payer_type')
                    ->label('Payer')
                    ->badge()
                    ->sortable(),

                IconColumn::make('is_direct_vendor')
                    ->label('Direct Vendor?')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->sortable(),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('vendor.display_name')
                    ->label('Vendor')
                    ->placeholder('Unassigned')
                    ->searchable(),

                TextColumn::make('assignedInspector.name')
                    ->label('Inspector')
                    ->placeholder('Unassigned')
                    ->searchable(),

                TextColumn::make('triggeredAudit.audit_number')
                    ->label('Triggered Audit')
                    ->placeholder('No Audit')
                    ->url(fn ($record) => $record->triggeredAudit ? \App\Filament\Resources\Operations\AuditResource::getUrl('edit', ['record' => $record->triggeredAudit]) : null)
                    ->openUrlInNewTab(),

                TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options(MaintenanceStatus::class),
                \Filament\Tables\Filters\SelectFilter::make('priority')
                    ->options(MaintenancePriority::class),
                \Filament\Tables\Filters\SelectFilter::make('payer_type')
                    ->options(PayerType::class),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('triggerAudit')
                    ->label('Trigger Audit')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Trigger Maintenance Verification Audit')
                    ->modalDescription('This will generate a post-repair Audit containing all selected rooms, inventory items, and utilities for inspection.')
                    ->hidden(fn ($record) => filled($record->triggered_audit_id))
                    ->action(function ($record) {
                        $service = app(MaintenanceAuditTriggerService::class);
                        $errors = $service->validateForAuditTrigger($record);

                        if (!empty($errors)) {
                            $bulletList = implode("<br>&bull; ", $errors);
                            Notification::make()
                                ->title('Cannot Trigger Verification Audit')
                                ->body(new HtmlString("Please complete all mandatory information across the tabs:<br>&bull; {$bulletList}"))
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        $audit = $service->triggerAudit($record);
                        Notification::make()
                            ->title('Audit Triggered Successfully')
                            ->body("Audit #{$audit->audit_number} has been created for post-repair inspection.")
                            ->success()
                            ->send();
                    }),

                Action::make('raiseInvoice')
                    ->label('Raise Invoice/Bill')
                    ->icon('heroicon-o-currency-rupee')
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\Select::make('bill_type')
                            ->label('Bill Type')
                            ->options([
                                'tenant_invoice' => 'Invoice to Tenant',
                                'owner_invoice' => 'Invoice to Owner',
                                'vendor_bill' => 'Vendor Bill',
                            ])
                            ->default('tenant_invoice')
                            ->required(),

                        \Filament\Forms\Components\TextInput::make('cost')
                            ->label('Cost Amount (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->default(fn ($record) => $record->total_cost > 0 ? $record->total_cost : 0)
                            ->required(),

                        \Filament\Forms\Components\DatePicker::make('due_date')
                            ->label('Due Date')
                            ->default(now()->addDays(7)),

                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Billing Remarks'),
                    ])
                    ->action(function ($record, array $data) {
                        $service = app(\App\Domain\Maintenance\Services\MaintenanceBillingService::class);

                        if ($data['bill_type'] === 'vendor_bill') {
                            $bill = $service->createVendorBill($record, [
                                [
                                    'description' => "Vendor Work: {$record->title} ({$record->ticket_number})",
                                    'quantity' => 1,
                                    'unit_price' => (float) $data['cost'],
                                    'total' => (float) $data['cost'],
                                ]
                            ], ['notes' => $data['notes'] ?? null, 'due_date' => $data['due_date'] ?? null]);

                            Notification::make()
                                ->title('Vendor Bill Created')
                                ->body("Vendor Bill {$bill->bill_number} generated for Ticket #{$record->ticket_number}")
                                ->success()
                                ->send();
                        } else {
                            $invoice = $service->createMaintenanceInvoice($record, $data['bill_type'], [
                                [
                                    'description' => "Maintenance Service: {$record->title} ({$record->ticket_number})",
                                    'quantity' => 1,
                                    'unit_price' => (float) $data['cost'],
                                    'total' => (float) $data['cost'],
                                ]
                            ], ['notes' => $data['notes'] ?? null, 'due_date' => $data['due_date'] ?? null]);

                            Notification::make()
                                ->title('Maintenance Invoice Raised')
                                ->body("Invoice {$invoice->invoice_number} raised for Ticket #{$record->ticket_number}")
                                ->success()
                                ->send();
                        }
                    }),

                Action::make('closeTicket')
                    ->label('Close Ticket')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Close Maintenance Request')
                    ->modalDescription('Are you sure you want to close this maintenance ticket?')
                    ->visible(fn ($record) => !in_array($record->status, [MaintenanceStatus::CLOSED, MaintenanceStatus::CANCELLED]))
                    ->action(function ($record) {
                        $service = app(MaintenanceAuditTriggerService::class);
                        $errors = $service->validateForAuditTrigger($record);

                        if (!empty($errors)) {
                            $bulletList = implode("<br>&bull; ", $errors);
                            Notification::make()
                                ->title('Cannot Close Maintenance Request')
                                ->body(new HtmlString("Please complete all mandatory information before closing the ticket:<br>&bull; {$bulletList}"))
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        if ($record->triggered_audit_id && $record->triggeredAudit) {
                            $auditStatus = $record->triggeredAudit->status;
                            $statusVal = $auditStatus instanceof \App\Domain\Audit\Enums\AuditStatus ? $auditStatus->value : (string) $auditStatus;
                            if (!in_array($statusVal, ['approved', 'completed'])) {
                                Notification::make()
                                    ->title('Cannot Close Maintenance Request')
                                    ->body('The linked post-repair verification audit is still pending approval. Please approve the audit first.')
                                    ->warning()
                                    ->persistent()
                                    ->send();
                                return;
                            }
                        }

                        $record->update([
                            'status' => MaintenanceStatus::CLOSED,
                            'resolved_at' => now(),
                            'completed_at' => $record->completed_at ?? now(),
                        ]);

                        if ($record->triggered_audit_id && $record->triggeredAudit) {
                            app(\App\Domain\Audit\Services\AuditReviewService::class)->lockAudit($record->triggeredAudit, auth()->user());
                        }

                        Notification::make()
                            ->title('Maintenance Request Closed')
                            ->body("Ticket #{$record->ticket_number} has been closed successfully.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
