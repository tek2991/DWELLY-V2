<?php

namespace App\Filament\Resources\Settings;

use App\Domain\Task\Enums\TaskCategory;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Models\TaskTemplate;
use App\Filament\Clusters\ReferenceData\ReferenceDataCluster;
use App\Filament\Resources\Settings\TaskTemplateResource\Pages\CreateTaskTemplate;
use App\Filament\Resources\Settings\TaskTemplateResource\Pages\EditTaskTemplate;
use App\Filament\Resources\Settings\TaskTemplateResource\Pages\ListTaskTemplates;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaskTemplateResource extends Resource
{
    protected static ?string $model = TaskTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = ReferenceDataCluster::class;

    protected static ?string $navigationLabel = 'Task Templates';

    protected static ?int $navigationSort = 16;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Template Details')
                    ->columns(12)
                    ->schema([
                        TextInput::make('name')
                            ->label('Template Name')
                            ->placeholder('e.g. Tenant Police Verification')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(8),

                        TextInput::make('code')
                            ->label('Unique Identifier Code')
                            ->placeholder('e.g. POLICE_VERIFICATION')
                            ->unique(ignoreRecord: true)
                            ->columnSpan(4),

                        Select::make('category')
                            ->label('Category')
                            ->options(TaskCategory::class)
                            ->required()
                            ->default(TaskCategory::FIELD_WORK->value)
                            ->columnSpan(4),

                        Select::make('default_priority')
                            ->label('Default Priority')
                            ->options(TaskPriority::class)
                            ->required()
                            ->default(TaskPriority::MEDIUM->value)
                            ->columnSpan(4),

                        TextInput::make('default_sla_hours')
                            ->label('Default Target SLA')
                            ->numeric()
                            ->suffix('Hours')
                            ->placeholder('e.g. 72')
                            ->columnSpan(4),

                        Textarea::make('description')
                            ->label('Description & Scope Guidelines')
                            ->rows(2)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active & Available for Task Creation')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Default Checklist Items / Subtasks')
                    ->description('These checklist items will be pre-populated whenever a new task is created using this template.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Checklist Item Title')
                                    ->required()
                                    ->columnSpan(8),

                                Toggle::make('is_mandatory')
                                    ->label('Mandatory')
                                    ->default(true)
                                    ->inline(false)
                                    ->columnSpan(4),
                            ])
                            ->columns(12)
                            ->reorderableWithButtons()
                            ->orderColumn('sort_order')
                            ->addActionLabel('Add Checklist Item'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->searchable()
                    ->color('gray'),

                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->sortable(),

                TextColumn::make('default_priority')
                    ->label('Default Priority')
                    ->badge(),

                TextColumn::make('default_sla_hours')
                    ->label('SLA')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} hrs" : '—')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Checklist Items')
                    ->badge()
                    ->color('info'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaskTemplates::route('/'),
            'create' => CreateTaskTemplate::route('/create'),
            'edit' => EditTaskTemplate::route('/{record}/edit'),
        ];
    }
}
