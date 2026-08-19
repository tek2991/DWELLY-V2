<?php

namespace App\Filament\Resources\TenancyAgreements\Tables;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenancyAgreementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Agreement Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('property.building_name')
                    ->label('Property')
                    ->description(fn (TenancyAgreement $record) => $record->property?->code)
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('tenants.display_name')
                    ->label('Tenant')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('audit.audit_number')
                    ->label('Move-In Audit')
                    ->placeholder('None')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rent_amount')
                    ->label('Rent (₹)')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('security_deposit')
                    ->label('Deposit (₹)')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status & Keys')
                    ->html()
                    ->formatStateUsing(function ($state, TenancyAgreement $record) {
                        $statusState = strtolower((string) $state);
                        $statusLabel = ucfirst(str_replace('_', ' ', $statusState));

                        $statusStyle = match ($statusState) {
                            'draft' => 'background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;',
                            'signed' => 'background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;',
                            'active' => 'background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;',
                            'deboarding_initiated' => 'background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a;',
                            'vacated', 'terminated' => 'background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;',
                            'archived' => 'background-color: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db;',
                            default => 'background-color: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe;',
                        };

                        $baseStyle = 'display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; line-height: 1.25;';

                        $statusBadge = '<span style="'.$baseStyle.' '.$statusStyle.'">'.e($statusLabel).'</span>';

                        if ($record->keys_handed_over) {
                            $keyStyle = 'background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; gap: 4px;';
                            $keyBadge = '<span style="'.$baseStyle.' '.$keyStyle.'" title="Keys Handed Over"><svg style="width: 0.75rem; height: 0.75rem; color: #15803d;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>Keys Handed Over</span>';
                        } else {
                            $keyStyle = 'background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; gap: 4px;';
                            $keyBadge = '<span style="'.$baseStyle.' '.$keyStyle.'" title="Keys Pending"><svg style="width: 0.75rem; height: 0.75rem; color: #b45309;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>Keys Pending</span>';
                        }

                        return '<div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px;">'.
                                    $statusBadge.
                                    $keyBadge.
                               '</div>';
                    }),

                TextColumn::make('start_date')
                    ->label('Lease Period')
                    ->formatStateUsing(function ($state, TenancyAgreement $record) {
                        $start = $record->start_date ? $record->start_date->format('d M Y') : '—';
                        $end = $record->end_date ? $record->end_date->format('d M Y') : '—';

                        return "{$start} &ndash; {$end}";
                    })
                    ->html()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'signed' => 'Signed',
                        'active' => 'Active',
                        'deboarding_initiated' => 'Deboarding Initiated',
                        'vacated' => 'Vacated',
                        'terminated' => 'Terminated',
                        'archived' => 'Archived',
                    ])
                    ->multiple(),

                SelectFilter::make('property_id')
                    ->label('Property')
                    ->relationship('property', 'building_name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('keys_handed_over')
                    ->label('Key Handover Status')
                    ->trueLabel('Keys Handed Over')
                    ->falseLabel('Keys Pending'),

                TernaryFilter::make('signed_by_tenant')
                    ->label('Execution Status')
                    ->trueLabel('Executed & Signed')
                    ->falseLabel('Draft / Unsigned'),

                Filter::make('start_date')
                    ->form([
                        DatePicker::make('start_from')->label('Start Date From'),
                        DatePicker::make('start_until')->label('Start Date Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['start_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('start_date', '>=', $date),
                            )
                            ->when(
                                $data['start_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('start_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deboardTenancy')
                    ->label('Deboarding')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('warning')
                    ->url(fn ($record) => TenancyAgreementResource::getUrl('deboard', ['record' => $record->id]))
                    ->visible(fn ($record) => in_array($record->status, ['active', 'deboarding_initiated', 'vacated'])),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
