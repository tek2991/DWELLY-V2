<?php

namespace App\Filament\Resources\Parties\Tables;

use App\Domain\Party\Enums\BusinessRole;
use App\Domain\Party\Enums\VendorOnboardingStatus;
use App\Domain\Party\Services\VendorOnboardingService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PartiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('party_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('vendorProfile.trade.name')
                    ->label('Vendor Trade')
                    ->badge()
                    ->placeholder('N/A')
                    ->toggleable(),
                TextColumn::make('vendorProfile.onboarding_status')
                    ->label('Vendor Status')
                    ->badge()
                    ->placeholder('N/A')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
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
                SelectFilter::make('vendor_status')
                    ->label('Vendor Onboarding Status')
                    ->options(VendorOnboardingStatus::class)
                    ->query(function ($query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('vendorProfile', fn ($q) => $q->where('onboarding_status', $data['value']));
                        }
                    }),
            ])
            ->recordActions([
                Action::make('verifyVendor')
                    ->label('Verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => $record->hasRole(BusinessRole::VENDOR) && $record->vendorProfile?->onboarding_status !== VendorOnboardingStatus::VERIFIED)
                    ->requiresConfirmation()
                    ->modalHeading('Verify & Approve Vendor')
                    ->modalDescription(fn ($record) => "Approve {$record->display_name} as an active, verified vendor for maintenance assignments.")
                    ->form([
                        Textarea::make('verification_notes')
                            ->label('Verification Notes / Remarks')
                            ->placeholder('e.g. Verified license, identity proof, and tax details.')
                            ->default(fn ($record) => $record->vendorProfile?->verification_notes),
                    ])
                    ->action(function ($record, array $data) {
                        $profile = $record->vendorProfile;
                        if (!$profile) return;

                        app(VendorOnboardingService::class)->verifyVendor(
                            profile: $profile,
                            verifier: auth()->user(),
                            notes: $data['verification_notes'] ?? null
                        );

                        Notification::make()
                            ->title('Vendor Verified & Approved')
                            ->body("{$record->display_name} is now approved and available for maintenance work orders.")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
