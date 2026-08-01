<?php

namespace App\Filament\Widgets;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Models\Audit;
use App\Filament\Resources\Operations\AuditResource;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingAuditsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Audit::query()
                    ->whereIn('status', [
                        AuditStatus::PENDING_REVIEW,
                        AuditStatus::IN_REVIEW,
                        AuditStatus::PARTIALLY_APPROVED,
                    ])
                    ->with(['property', 'inspector', 'reviewer'])
                    ->latest('updated_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('audit_number')
                    ->label('Audit #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('property.title')
                    ->label('Property')
                    ->placeholder('N/A')
                    ->searchable(),

                Tables\Columns\TextColumn::make('audit_type')
                    ->label('Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('inspector.name')
                    ->label('Inspector')
                    ->placeholder('Unassigned'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Activity')
                    ->since(),
            ])
            ->actions([
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Audit $record): string => AuditResource::getUrl('review', ['record' => $record])),
            ])
            ->heading('Audits Requiring Review & Approval')
            ->emptyStateHeading('No Audits Pending Review')
            ->emptyStateDescription('All submitted audits have been reviewed and processed.');
    }
}
