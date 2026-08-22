<?php

namespace App\Filament\Resources\Operations\MaintenanceRequestResource\Tables;

use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Services\MaintenanceAuditTriggerService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
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

                TextColumn::make('property.code')
                    ->label('Property')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => $state ?: ($record->property?->building_name ?? '—'))
                    ->tooltip(function ($record) {
                        $property = $record->property;
                        if (!$property) {
                            return null;
                        }

                        $parts = array_filter([
                            $property->building_name,
                            $property->locality ?? $property->area,
                            $property->city,
                        ]);

                        return !empty($parts) ? implode(', ', $parts) : ($property->building_name ?: "Property #{$property->id}");
                    }),

                TextColumn::make('title')
                    ->searchable()
                    ->limit(28),

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

                TextColumn::make('route')
                    ->label('Execution Route')
                    ->state(fn ($record) => $record->is_direct_vendor ? 'Direct' : 'Dwelly Coordinated')
                    ->badge()
                    ->color(fn ($record) => $record->is_direct_vendor ? 'warning' : 'info'),

                TextColumn::make('vendor_info')
                    ->label('Vendor / Trades')
                    ->state(function ($record) {
                        if ($record->is_direct_vendor) {
                            return 'Client Direct';
                        }
                        $count = $record->vendorQuotes()->count();
                        if ($count > 0) {
                            return "{$count} Trade(s)";
                        }
                        return $record->vendor?->display_name ?? 'Unassigned';
                    }),

                TextColumn::make('total_client_cost')
                    ->label('Quoted (₹)')
                    ->money('INR')
                    ->state(fn ($record) => $record->total_client_cost > 0 ? $record->total_client_cost : $record->quotation_amount)
                    ->sortable(),

                TextColumn::make('assignedInspector.name')
                    ->label('Inspector')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                \Filament\Tables\Filters\TernaryFilter::make('is_direct_vendor')
                    ->label('Execution Route')
                    ->trueLabel('Direct Repair')
                    ->falseLabel('Dwelly Coordinated'),
            ])
            ->recordActions([
                Action::make('closeTicket')
                    ->label('Close Ticket')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Close Maintenance Request')
                    ->modalDescription('Are you sure you want to close this maintenance ticket?')
                    ->visible(fn ($record) => !in_array($record->status, [MaintenanceStatus::CLOSED, MaintenanceStatus::CANCELLED]))
                    ->action(function ($record) {
                        if ($record->triggered_audit_id && $record->triggeredAudit) {
                            $auditStatus = $record->triggeredAudit->status;
                            $statusVal = $auditStatus instanceof \App\Domain\Audit\Enums\AuditStatus ? $auditStatus->value : (string) $auditStatus;
                            if (!in_array($statusVal, ['approved', 'completed']) && !$record->triggeredAudit->is_locked) {
                                Notification::make()
                                    ->title('Cannot Close Maintenance Request')
                                    ->body('The linked post-repair verification audit is currently in progress. Please approve or complete the audit first.')
                                    ->warning()
                                    ->persistent()
                                    ->send();
                                return;
                            }
                        }

                        $record->update([
                            'status' => MaintenanceStatus::CLOSED,
                            'resolved_at' => $record->resolved_at ?? now(),
                            'completed_at' => $record->completed_at ?? now(),
                        ]);

                        if ($record->triggered_audit_id && $record->triggeredAudit && !$record->triggeredAudit->is_locked) {
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
                BulkActionGroup::make([]),
            ]);
    }
}
