<?php

namespace Tek2991\Accounting\Filament\Resources\Settings\FiscalPeriods;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Filament\Resources\Settings\FiscalPeriods\Pages;
use Tek2991\Accounting\Services\PeriodLockService;
use Tek2991\Accounting\Enums\FiscalPeriodStatus;
use Illuminate\Support\HtmlString;

class FiscalPeriodResource extends Resource
{
    protected static ?string $model = FiscalPeriod::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                DatePicker::make('start_date')
                    ->required(),
                DatePicker::make('end_date')
                    ->required()
                    ->afterOrEqual('start_date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (FiscalPeriodStatus $state): string => match ($state) {
                        FiscalPeriodStatus::Open => 'success',
                        FiscalPeriodStatus::SoftClosed => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => $state->getLabel()),
                Tables\Columns\TextColumn::make('closing_profit_loss')
                    ->label('Closing P&L')
                    ->money(fn() => \Tek2991\Accounting\Facades\Accounting::getCurrency())
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('closedBy.name')
                    ->label('Closed By')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('closed_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->visible(fn (FiscalPeriod $record) => !$record->isSoftClosed()),
                \Filament\Actions\Action::make('softClose')
                    ->label('Soft Close')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (FiscalPeriod $record) => !$record->isSoftClosed())
                    ->form(function (FiscalPeriod $record) {
                        $preview = app(PeriodLockService::class)->previewClose($record);
                        $errors = $preview['result']->getErrors();
                        $profit = $preview['projected_profit_loss'];
                        
                        $components = [
                            \Filament\Forms\Components\Placeholder::make('projected_profit')
                                ->label('Projected Closing Profit/Loss')
                                ->content(number_format($profit / 100, 2)),
                        ];

                        if (!empty($errors)) {
                            $errorList = "<ul><li class='mb-2'>" . implode("</li><li class='mb-2'>", $errors) . "</li></ul>";
                            $components[] = \Filament\Forms\Components\Placeholder::make('errors')
                                ->label('Validation Errors')
                                ->content(new HtmlString("<div class='text-danger-600'>{$errorList}</div>"));
                        } else {
                            $components[] = \Filament\Forms\Components\Placeholder::make('checks_passed')
                                ->label('Validation')
                                ->content(new HtmlString("<div class='text-success-600'>✓ All checks passed</div>"));
                        }

                        return $components;
                    })
                    ->action(function (FiscalPeriod $record, array $data, \Filament\Actions\Action $action) {
                        try {
                            app(PeriodLockService::class)->closePeriod($record, auth()->user());
                            \Filament\Notifications\Notification::make()->title('Period Closed')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Close Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('WARNING: Reopening this period will allow new postings. An audit log will be created.')
                    ->visible(fn (FiscalPeriod $record) => $record->isSoftClosed())
                    ->action(function (FiscalPeriod $record) {
                        app(PeriodLockService::class)->reopenPeriod($record, auth()->user());
                        \Filament\Notifications\Notification::make()->title('Period Reopened')->success()->send();
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFiscalPeriods::route('/'),
        ];
    }
}
