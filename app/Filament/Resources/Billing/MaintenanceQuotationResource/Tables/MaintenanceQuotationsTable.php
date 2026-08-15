<?php

namespace App\Filament\Resources\Billing\MaintenanceQuotationResource\Tables;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaintenanceQuotationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quote_number')
                    ->label('Quote #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('maintenanceRequest.ticket_number')
                    ->label('Ticket #')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->maintenanceRequest ? \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $record->maintenanceRequest]) : null)
                    ->openUrlInNewTab(),

                TextColumn::make('maintenanceRequest.property.building_name')
                    ->label('Property')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('maintenanceRequest.payer_type')
                    ->label('Payer')
                    ->badge()
                    ->sortable(),

                TextColumn::make('maintenanceRequest.total_vendor_cost')
                    ->label('Vendor Cost (₹)')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Client Quoted (₹)')
                    ->money('INR')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Quote Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'pending_approval' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('settlement_status')
                    ->label('Billing Status')
                    ->badge()
                    ->state(function ($record) {
                        $req = $record->maintenanceRequest;
                        if (!$req) return 'N/A';
                        if ($req->bill_id && ($req->owner_invoice_id || $req->tenant_invoice_id)) {
                            return 'Invoiced & Billed';
                        }
                        if ($req->bill_id || $req->owner_invoice_id || $req->tenant_invoice_id) {
                            return 'Partially Invoiced';
                        }
                        return 'Pending Settlement';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Invoiced & Billed' => 'success',
                        'Partially Invoiced' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending_approval' => 'Pending Client Approval',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('approveQuote')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['draft', 'pending_approval']))
                    ->form([
                        Textarea::make('approval_notes')
                            ->label('Approval Confirmation Remarks')
                            ->placeholder('e.g. Approved via WhatsApp / Email confirmation')
                            ->required(),

                        SpatieMediaLibraryFileUpload::make('approval_proof_files')
                            ->collection('approval_proof_files')
                            ->multiple()
                            ->required()
                            ->label('Upload Approval Proof (Screenshot / Signature)'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'approved',
                            'approved_at' => now(),
                            'approval_notes' => $data['approval_notes'] ?? null,
                        ]);

                        if ($record->maintenanceRequest) {
                            $record->maintenanceRequest->update([
                                'quotation_status' => 'approved',
                                'quotation_approved_at' => now(),
                                'quotation_approval_notes' => $data['approval_notes'] ?? null,
                                'status' => MaintenanceStatus::QUOTATION_APPROVED,
                            ]);
                            $record->maintenanceRequest->syncQuotationTotals();
                        }

                        Notification::make()
                            ->title('Quotation Approved')
                            ->body("Quotation {$record->quote_number} is approved. Operations ticket has been authorized for repair.")
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
