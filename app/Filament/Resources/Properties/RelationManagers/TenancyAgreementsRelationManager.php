<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Domain\Agreement\Actions\RenewTenancyAgreementAction;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->description(fn (TenancyAgreement $record) => $record->is_renewal ? 'Renewal' : 'Fresh Lease')
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
                        'renewed' => 'purple',
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
                    ->visible(fn () => strtolower((string) $this->getOwnerRecord()->status) === 'vacant' && (auth()->user()?->can('create', TenancyAgreement::class) ?? false))
                    ->url(fn () => TenancyAgreementResource::getUrl('create', ['property_id' => $this->getOwnerRecord()->id])),
            ])
            ->actions([
                Action::make('manage')
                    ->label('Manage Agreement')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn ($record) => TenancyAgreementResource::getUrl('edit', ['record' => $record->id])),

                Action::make('renewAgreement')
                    ->label('Renew')
                    ->icon('heroicon-o-arrow-path')
                    ->color('purple')
                    ->visible(fn (TenancyAgreement $record) => $record->status === 'active' && (auth()->user()?->can('renew', $record) ?? false))
                    ->modalHeading('Renew Tenancy Agreement & Draft 11-Month Lease')
                    ->modalDescription('Carries forward tenant KYC, inventory audit references, and security deposit, establishing a renewed 11-month lease term with updated commercial terms.')
                    ->modalSubmitActionLabel('Draft Renewal Agreement')
                    ->form(fn (TenancyAgreement $record) => TenancyAgreementForm::getRenewalFormSchema($record))
                    ->action(function (TenancyAgreement $record, array $data) {
                        $action = app(RenewTenancyAgreementAction::class);
                        $renewal = $action->execute($record, $data, auth()->user());

                        Notification::make()
                            ->title('Renewal Agreement Drafted')
                            ->body("Agreement {$renewal->code} has been drafted with carried-over KYC and audit records.")
                            ->success()
                            ->send();

                        return redirect(TenancyAgreementResource::getUrl('edit', ['record' => $renewal]));
                    }),
            ]);
    }
}
