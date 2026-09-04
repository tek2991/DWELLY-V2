<?php

namespace App\Filament\Resources\Operations\TaskResource\Schemas;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Task\Enums\TaskCategory;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\TaskTemplate;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                // Header / Overview Section
                Section::make('Task Information & Property Context')
                    ->description('Select property and specify task details or load from a standardized operational template.')
                    ->columnSpan(8)
                    ->columns(12)
                    ->schema([
                        Select::make('template_id')
                            ->label('Standard Task Template (Optional)')
                            ->options(TaskTemplate::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->columnSpan(12)
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (! $state) {
                                    return;
                                }
                                $template = TaskTemplate::with('items')->find($state);
                                if ($template) {
                                    $set('category', $template->category->value ?? $template->category);
                                    $set('title', $template->name);
                                    $set('description', $template->description);
                                    $set('priority', $template->default_priority->value ?? $template->default_priority);
                                    $set('sla_hours', $template->default_sla_hours);
                                    if ($template->default_sla_hours) {
                                        $set('due_date', now()->addHours($template->default_sla_hours)->toDateTimeString());
                                    }

                                    // Populate checklist repeater
                                    $items = $template->items->map(fn ($item) => [
                                        'title' => $item->title,
                                        'is_mandatory' => $item->is_mandatory,
                                        'is_completed' => false,
                                    ])->toArray();
                                    $set('checklistItems', $items);
                                }
                            }),

                        Select::make('property_id')
                            ->label('Property')
                            ->relationship('property', 'code')
                            ->getOptionLabelFromRecordUsing(fn (Property $p) => "{$p->code} — {$p->building_name} ({$p->city})")
                            ->searchable(['code', 'building_name', 'address_line_1', 'city'])
                            ->preload()
                            ->required()
                            ->live()
                            ->columnSpan(7),

                        Select::make('category')
                            ->label('Category')
                            ->options(TaskCategory::class)
                            ->required()
                            ->default(TaskCategory::FIELD_WORK->value)
                            ->columnSpan(5),

                        TextInput::make('title')
                            ->label('Task Title')
                            ->placeholder('e.g. Tenant Police Verification Submission')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(12),

                        Textarea::make('description')
                            ->label('Description & Scope of Work')
                            ->rows(3)
                            ->placeholder('Provide specific instructions for field staff...')
                            ->columnSpan(12),

                        Grid::make(12)
                            ->columnSpan(12)
                            ->schema([
                                Select::make('taskable_type')
                                    ->label('Associated Record Type')
                                    ->options([
                                        TenancyAgreement::class => 'Tenancy Agreement',
                                        TenantDeboarding::class => 'Tenant Deboarding',
                                        MaintenanceRequest::class => 'Maintenance Request',
                                        Party::class => 'Contact / Party',
                                    ])
                                    ->placeholder('Standalone Property Task')
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('taskable_id', null))
                                    ->columnSpan(6),

                                Select::make('taskable_id')
                                    ->label('Select Associated Record')
                                    ->options(function (Get $get) {
                                        $type = $get('taskable_type');
                                        $propertyId = $get('property_id');
                                        if (! $type) {
                                            return [];
                                        }

                                        if ($type === TenancyAgreement::class) {
                                            $q = TenancyAgreement::query();
                                            if ($propertyId) {
                                                $q->where('property_id', $propertyId);
                                            }
                                            return $q->latest()->limit(20)->get()->mapWithKeys(fn ($a) => [$a->id => "Agreement: {$a->code}"]);
                                        }

                                        if ($type === TenantDeboarding::class) {
                                            $q = TenantDeboarding::query();
                                            if ($propertyId) {
                                                $q->where('property_id', $propertyId);
                                            }
                                            return $q->latest()->limit(20)->get()->mapWithKeys(fn ($d) => [$d->id => "Deboarding: {$d->code}"]);
                                        }

                                        if ($type === MaintenanceRequest::class) {
                                            $q = MaintenanceRequest::query();
                                            if ($propertyId) {
                                                $q->where('property_id', $propertyId);
                                            }
                                            return $q->latest()->limit(20)->get()->mapWithKeys(fn ($m) => [$m->id => "Ticket #{$m->ticket_number} — {$m->title}"]);
                                        }

                                        if ($type === Party::class) {
                                            return Party::latest()->limit(30)->get()->mapWithKeys(fn ($p) => [$p->id => "{$p->display_name} ({$p->phone})"]);
                                        }

                                        return [];
                                    })
                                    ->searchable()
                                    ->visible(fn (Get $get) => filled($get('taskable_type')))
                                    ->columnSpan(6),
                            ]),
                    ]),

                // Assignment & Status Sidebar Section
                Section::make('Assignment, SLA & Status')
                    ->columnSpan(4)
                    ->columns(1)
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(TaskStatus::class)
                            ->required()
                            ->default(TaskStatus::PENDING->value),

                        Select::make('priority')
                            ->label('Priority')
                            ->options(TaskPriority::class)
                            ->required()
                            ->default(TaskPriority::MEDIUM->value),

                        Select::make('assigned_to_id')
                            ->label('Assigned To (Staff / Exec)')
                            ->relationship('assignedTo', 'name')
                            ->searchable()
                            ->preload(),

                        DateTimePicker::make('scheduled_at')
                            ->label('Scheduled Execution Date')
                            ->native(false),

                        DateTimePicker::make('due_date')
                            ->label('Due Date / SLA Deadline')
                            ->native(false)
                            ->helperText(function (Get $get) {
                                $due = $get('due_date');
                                if ($due && \Carbon\Carbon::parse($due)->isPast() && $get('status') !== TaskStatus::COMPLETED->value) {
                                    return new HtmlString('<span class="text-rose-600 font-semibold">⚠️ Task is Overdue!</span>');
                                }
                                return null;
                            }),

                        TextInput::make('sla_hours')
                            ->label('Target SLA Duration')
                            ->numeric()
                            ->suffix('Hours'),
                    ]),

                // Checklist Subtasks Section
                Section::make('Actionable Checklist / Subtasks')
                    ->description('Operational items required for complete task verification. Mandatory items block completion until checked.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('checklistItems')
                            ->relationship('checklistItems')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Checklist Item Title')
                                    ->placeholder('e.g. Collect signed acknowledgment')
                                    ->required()
                                    ->columnSpan(6),

                                Toggle::make('is_mandatory')
                                    ->label('Mandatory')
                                    ->default(true)
                                    ->inline(false)
                                    ->columnSpan(2),

                                Toggle::make('is_completed')
                                    ->label('Completed')
                                    ->default(false)
                                    ->inline(false)
                                    ->columnSpan(2),

                                TextInput::make('description')
                                    ->label('Notes / Findings')
                                    ->placeholder('Optional findings...')
                                    ->columnSpan(2),
                            ])
                            ->columns(12)
                            ->reorderableWithButtons()
                            ->orderColumn('sort_order')
                            ->defaultItems(0)
                            ->addActionLabel('Add Checklist Item'),
                    ]),

                // Proof & Media Uploads
                Section::make('Evidence & Verification Proofs')
                    ->description('Upload on-site inspection photos, signed challans, or verification documents.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('proof_photos')
                            ->collection('proof_photos')
                            ->label('Field & Inspection Photos')
                            ->multiple()
                            ->image()
                            ->maxFiles(10)
                            ->reorderable()
                            ->downloadable()
                            ->openable(),

                        SpatieMediaLibraryFileUpload::make('completion_proofs')
                            ->collection('completion_proofs')
                            ->label('Signed Challans, NOCs & Documents')
                            ->multiple()
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->downloadable()
                            ->openable(),
                    ]),

                // Resolution & Closure Notes
                Section::make('Resolution Notes & Closure')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('resolution_notes')
                            ->label('Resolution Summary / Field Notes')
                            ->rows(3)
                            ->placeholder('Describe what was done, key observations, or notes for the team...'),

                        Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->rows(2)
                            ->visible(fn (Get $get) => $get('status') === TaskStatus::CANCELLED->value || $get('status') === 'cancelled')
                            ->required(fn (Get $get) => $get('status') === TaskStatus::CANCELLED->value || $get('status') === 'cancelled'),
                    ]),
            ]);
    }
}
