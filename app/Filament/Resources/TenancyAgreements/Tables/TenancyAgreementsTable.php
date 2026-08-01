<?php

namespace App\Filament\Resources\TenancyAgreements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Services\TenancyAgreementPdfService;
use App\Domain\Agreement\Services\TenancyAgreementDocxService;

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

                TextColumn::make('property.code')
                    ->label('Property')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('audit.audit_number')
                    ->label('Linked Audit')
                    ->placeholder('None')
                    ->sortable(),

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
                        $statusState = strtolower((string)$state);
                        $statusLabel = ucfirst($statusState);

                        $statusStyle = match ($statusState) {
                            'draft' => 'background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;',
                            'signed' => 'background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;',
                            'active' => 'background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;',
                            'terminated' => 'background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;',
                            default => 'background-color: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe;',
                        };

                        $baseStyle = 'display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; line-height: 1.25;';

                        $statusBadge = '<span style="' . $baseStyle . ' ' . $statusStyle . '">' . e($statusLabel) . '</span>';

                        if ($record->keys_handed_over) {
                            $keyStyle = 'background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; gap: 4px;';
                            $keyBadge = '<span style="' . $baseStyle . ' ' . $keyStyle . '" title="Keys Handed Over"><svg style="width: 0.75rem; height: 0.75rem; color: #15803d;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>Keys Handed Over</span>';
                        } else {
                            $keyStyle = 'background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; gap: 4px;';
                            $keyBadge = '<span style="' . $baseStyle . ' ' . $keyStyle . '" title="Keys Pending"><svg style="width: 0.75rem; height: 0.75rem; color: #b45309;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>Keys Pending</span>';
                        }

                        return '<div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px;">' .
                                    $statusBadge .
                                    $keyBadge .
                               '</div>';
                    }),

                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deboardTenancy')
                    ->label('Deboarding')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('warning')
                    ->url(fn ($record) => \App\Filament\Resources\TenancyAgreements\TenancyAgreementResource::getUrl('deboard', ['record' => $record->id]))
                    ->visible(fn ($record) => in_array($record->status, ['active', 'deboarding_initiated', 'vacated'])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
