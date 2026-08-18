<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;

class TenancyAgreementsRelationManager extends RelationManager
{
    protected static string $relationship = 'agreements';

    protected static ?string $title = 'Tenancy Agreements';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-text';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Agreement Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn ($record) => TenancyAgreementResource::getUrl('edit', ['record' => $record->id])),

                TextColumn::make('primaryTenant.party.display_name')
                    ->label('Tenant Name')
                    ->default(fn ($record) => $record->tenants()->first()?->display_name ?? 'N/A')
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('rent_amount')
                    ->label('Monthly Rent (₹)')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('security_deposit')
                    ->label('Security Deposit (₹)')
                    ->money('INR'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'deboarding_initiated' => 'warning',
                        'vacated', 'terminated' => 'danger',
                        'draft' => 'gray',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (?string $state): string => ucfirst(str_replace('_', ' ', $state ?? 'Draft'))),
            ])
            ->headerActions([
                Action::make('createTenancyAgreement')
                    ->label('Create Tenancy Agreement')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->button()
                    ->visible(fn () => strtolower((string)$this->getOwnerRecord()->status) === 'vacant')
                    ->url(fn () => TenancyAgreementResource::getUrl('create', ['property_id' => $this->getOwnerRecord()->id])),
            ])
            ->actions([
                Action::make('manage')
                    ->label('Manage Agreement')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn ($record) => TenancyAgreementResource::getUrl('edit', ['record' => $record->id])),
            ]);
    }
}
