<?php

namespace App\Filament\Widgets;

use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UrgentMaintenanceWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MaintenanceRequest::query()
                    ->whereNotIn('status', [
                        MaintenanceStatus::RESOLVED,
                        MaintenanceStatus::CLOSED,
                        MaintenanceStatus::CANCELLED,
                    ])
                    ->with(['property'])
                    ->orderByRaw("CASE 
                        WHEN priority = 'emergency' THEN 1 
                        WHEN priority = 'high' THEN 2 
                        WHEN priority = 'medium' THEN 3 
                        ELSE 4 
                    END")
                    ->latest('updated_at')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->label('Ticket #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Issue')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('property.title')
                    ->label('Property')
                    ->placeholder('N/A')
                    ->searchable(),

                Tables\Columns\TextColumn::make('priority')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reported')
                    ->since(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Manage')
                    ->icon('heroicon-m-wrench-screwdriver')
                    ->url(fn (MaintenanceRequest $record): string => MaintenanceRequestResource::getUrl('edit', ['record' => $record])),
            ])
            ->heading('Urgent & Active Maintenance Requests')
            ->emptyStateHeading('No Active Maintenance Issues')
            ->emptyStateDescription('All maintenance tickets are resolved or closed.');
    }
}
