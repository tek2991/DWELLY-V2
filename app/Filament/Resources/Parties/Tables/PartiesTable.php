<?php

namespace App\Filament\Resources\Parties\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class PartiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('display_name')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('party_type')
                    ->badge()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('vendorProfile.trade.name')
                    ->label('Vendor Trade')
                    ->badge()
                    ->placeholder('N/A')
                    ->toggleable(),
                \Filament\Tables\Columns\TextColumn::make('vendorProfile.onboarding_status')
                    ->label('Vendor Status')
                    ->badge()
                    ->placeholder('N/A')
                    ->toggleable(),
                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'owner' => 'Owner',
                        'tenant' => 'Tenant',
                        'vendor' => 'Vendor',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'vendor') {
                            $query->whereHas('vendorProfile');
                        } elseif ($data['value'] === 'owner') {
                            $query->whereHas('ownerProfile');
                        } elseif ($data['value'] === 'tenant') {
                            $query->whereHas('tenantProfile');
                        }
                    }),
                \Filament\Tables\Filters\SelectFilter::make('vendor_status')
                    ->label('Vendor Onboarding Status')
                    ->options(\App\Domain\Party\Enums\VendorOnboardingStatus::class)
                    ->query(function ($query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('vendorProfile', fn ($q) => $q->where('onboarding_status', $data['value']));
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
