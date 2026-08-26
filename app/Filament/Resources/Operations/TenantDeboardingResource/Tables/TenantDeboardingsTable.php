<?php

namespace App\Filament\Resources\Operations\TenantDeboardingResource\Tables;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Audit\Enums\AuditStatus;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TenantDeboardingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Deboarding Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Deboarding code copied'),

                TextColumn::make('property.code')
                    ->label('Property / Building')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (TenantDeboarding $record) => $record->property?->building_name ?? $record->property?->address_line_1),

                TextColumn::make('tenant.display_name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->description(fn (TenantDeboarding $record) => $record->tenant?->phone ? '📞 ' . $record->tenant->phone : null),

                TextColumn::make('status')
                    ->label('Workflow Stage')
                    ->badge()
                    ->sortable(),

                TextColumn::make('moveOutAudit.status')
                    ->label('Exit Audit')
                    ->badge()
                    ->formatStateUsing(function ($state, TenantDeboarding $record) {
                        if (! $record->move_out_audit_id) {
                            return 'Not Triggered';
                        }
                        $status = $record->moveOutAudit?->status;
                        return $status instanceof AuditStatus ? $status->getLabel() : (string) $status;
                    })
                    ->color(function ($state, TenantDeboarding $record) {
                        if (! $record->move_out_audit_id) {
                            return 'gray';
                        }
                        $status = $record->moveOutAudit?->status;
                        $statusVal = $status instanceof AuditStatus ? $status->value : (string) $status;
                        return in_array($statusVal, ['approved', 'completed']) ? 'success' : 'warning';
                    }),

                TextColumn::make('target_vacating_date')
                    ->label('Target Vacating')
                    ->date('d M Y')
                    ->sortable(),

                IconColumn::make('keys_returned')
                    ->label('Keys Back')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('net_deposit_refund')
                    ->label('Net Refund')
                    ->money('INR')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (TenantDeboarding $record) => (float) $record->net_deposit_refund > 0 ? 'success' : 'gray'),

                TextColumn::make('settlement_status')
                    ->label('Settlement')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', (string) $state)))
                    ->color(fn ($state) => match ((string) $state) {
                        'settled', 'refunded' => 'success',
                        'balance_due' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(DeboardingStatus::class),

                SelectFilter::make('settlement_status')
                    ->options([
                        'pending' => 'Pending Settlement',
                        'refunded' => 'Refunded to Tenant',
                        'balance_due' => 'Balance Due from Tenant',
                        'settled' => 'Fully Settled',
                    ]),

                TernaryFilter::make('keys_returned')
                    ->label('Keys Returned Status'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Manage Deboarding')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary'),
            ]);
    }
}
