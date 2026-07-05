<?php

namespace App\Filament\Widgets;

use App\Domain\Opportunity\Models\Opportunity;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Domain\Opportunity\Enums\OpportunityStatus;

class RecentOpportunities extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Opportunity::query()
                    ->where('assigned_user_id', auth()->id())
                    ->orWhere('status', OpportunityStatus::NEW)
                    ->latest('updated_at')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('owner_name')
                    ->label('Owner'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Activity')
                    ->since(),
            ])
            ->actions([
                \Filament\Actions\Action::make('open')
                    ->label('View')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Opportunity $record): string => \App\Filament\Resources\Operations\OpportunityResource::getUrl('view', ['record' => $record])),
            ])
            ->heading('Opportunities Requiring Attention');
    }
}
