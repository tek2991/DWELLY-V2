<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Services\MaintenanceAuditTriggerService;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenanceRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceRequests';

    protected static ?string $title = 'Maintenance Requests';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        if ($pageClass === \App\Filament\Resources\Properties\Pages\OnboardingDashboard::class) {
            return false;
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return MaintenanceRequestResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('Ticket #')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable(),

                TextColumn::make('priority')
                    ->badge(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('payer_type')
                    ->label('Payer')
                    ->badge(),

                TextColumn::make('total_cost')
                    ->money('INR'),

                TextColumn::make('vendor.display_name')
                    ->label('Vendor')
                    ->placeholder('Unassigned'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
