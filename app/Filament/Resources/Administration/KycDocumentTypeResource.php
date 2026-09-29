<?php

namespace App\Filament\Resources\Administration;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Shared\Enums\KycRequirementLevel;
use App\Domain\Shared\Models\KycDocumentType;
use App\Filament\Clusters\AdministrationCluster;
use App\Filament\Resources\Administration\KycDocumentTypeResource\Pages\ManageKycDocumentTypes;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class KycDocumentTypeResource extends Resource
{
    protected static ?string $model = KycDocumentType::class;

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $cluster = AdministrationCluster::class;

    protected static ?string $navigationLabel = 'KYC & Documents';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        return $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER, 'admin', 'Super Admin'])
            || $user->can('admin.kyc_documents.manage');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(?Model $record = null): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Document Identity & Details')
                    ->description('Define the identification document name, code, visual icon, and helper text.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Document Name')
                            ->placeholder('e.g. Aadhaar Card')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set) {
                                if ($operation === 'create' && filled($state)) {
                                    $set('code', Str::slug($state, '_'));
                                }
                            }),

                        Select::make('icon')
                            ->label('Document Icon')
                            ->options(static::getIconOptions())
                            ->searchable()
                            ->preload()
                            ->default('heroicon-m-document-text')
                            ->prefixIcon(fn (Get $get) => $get('icon') ?: 'heroicon-m-document-text')
                            ->live()
                            ->helperText('Visual icon displayed on document cards across forms.'),

                        TextInput::make('code')
                            ->label('System Code / Slug')
                            ->placeholder('e.g. aadhaar')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->disabled(fn (string $operation) => $operation === 'edit')
                            ->helperText(function (string $operation) {
                                return $operation === 'edit'
                                    ? 'Permanent identifier used for collections & JSON bindings (locked).'
                                    : 'Unique lowercase identifier used for file collections & field binding. Locked once created.';
                            }),

                        TextInput::make('sort_order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Textarea::make('description')
                            ->label('Upload Instructions / Description')
                            ->placeholder('e.g. Front & Back image or PDF of Government issued card')
                            ->rows(2)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Is Active')
                            ->default(true)
                            ->helperText('Inactive documents are hidden from all workflow forms.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Identifier / Number Configuration')
                    ->description('Optionally request a document number (e.g. Aadhaar number, PAN number) alongside file uploads.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('has_number')
                            ->label('Capture Document Number / ID')
                            ->helperText('Enable if this document requires a text number input alongside the attachment.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('number_label')
                            ->label('Number Input Label')
                            ->placeholder('e.g. Aadhaar Number')
                            ->visible(fn (Get $get) => (bool) $get('has_number'))
                            ->required(fn (Get $get) => (bool) $get('has_number')),

                        TextInput::make('number_regex')
                            ->label('Validation Regex (Optional)')
                            ->placeholder('e.g. ^[0-9]{12}$')
                            ->visible(fn (Get $get) => (bool) $get('has_number')),
                    ]),

                Section::make('Workflow Requirements')
                    ->description('Configure whether this document is disabled, optional, or mandatory across business workflows.')
                    ->columns(2)
                    ->schema([
                        Select::make('mou_owner_requirement')
                            ->label('MOU - Property Owner')
                            ->options(KycRequirementLevel::class)
                            ->default(KycRequirementLevel::DISABLED)
                            ->required(),

                        Select::make('mou_signatory_requirement')
                            ->label('MOU - Signing Authority (POA)')
                            ->options(KycRequirementLevel::class)
                            ->default(KycRequirementLevel::DISABLED)
                            ->required(),

                        Select::make('tenancy_primary_requirement')
                            ->label('Tenancy - Primary Tenant')
                            ->options(KycRequirementLevel::class)
                            ->default(KycRequirementLevel::DISABLED)
                            ->required(),

                        Select::make('tenancy_secondary_requirement')
                            ->label('Tenancy - Secondary Tenant')
                            ->options(KycRequirementLevel::class)
                            ->default(KycRequirementLevel::DISABLED)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('name')
                    ->label('Document')
                    ->searchable(['name', 'code'])
                    ->sortable()
                    ->weight('bold')
                    ->icon(fn (KycDocumentType $record) => $record->icon ?: 'heroicon-m-document-text')
                    ->tooltip(function (KycDocumentType $record): HtmlString {
                        $code = e($record->code);
                        $desc = filled($record->description) ? e($record->description) : null;

                        $html = '<div style="max-width: 280px; line-height: 1.4; text-align: left;">';
                        if ($desc) {
                            $html .= '<div style="margin-bottom: 6px; font-size: 12px; font-weight: 400;">' . $desc . '</div>';
                        }
                        $html .= '<div style="text-align: right; font-size: 10px; font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; letter-spacing: 0.03em;">' . $code . '</div>';
                        $html .= '</div>';

                        return new HtmlString($html);
                    }),

                IconColumn::make('has_number')
                    ->label('Has ID #')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('mou_owner_requirement')
                    ->label('MOU Owner')
                    ->badge()
                    ->sortable(),

                TextColumn::make('mou_signatory_requirement')
                    ->label('MOU Signatory')
                    ->badge()
                    ->sortable(),

                TextColumn::make('tenancy_primary_requirement')
                    ->label('Tenant Primary')
                    ->badge()
                    ->sortable(),

                TextColumn::make('tenancy_secondary_requirement')
                    ->label('Tenant Secondary')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('mou_owner_requirement')
                    ->label('MOU Owner Requirement')
                    ->options(KycRequirementLevel::class),

                SelectFilter::make('is_active')
                    ->label('Active Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth('3xl'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getIconOptions(): array
    {
        return [
            'heroicon-m-identification' => 'Identification / ID Card',
            'heroicon-m-credit-card' => 'Credit Card / PAN Card',
            'heroicon-m-banknotes' => 'Banknotes / Cheque / Financial Proof',
            'heroicon-m-bolt' => 'Bolt / Electricity / Utility',
            'heroicon-m-scale' => 'Scale / Legal / Power of Attorney',
            'heroicon-m-document-text' => 'Document Text / Agreement',
            'heroicon-m-document-check' => 'Document Check / Verified Certificate',
            'heroicon-m-globe-alt' => 'Globe / Passport / International',
            'heroicon-m-truck' => 'Truck / Driving License / Vehicle',
            'heroicon-m-home' => 'Home / Property Deed / Ownership',
            'heroicon-m-building-office' => 'Building / Organization / Commercial',
            'heroicon-m-user' => 'User / Person Profile',
            'heroicon-m-user-circle' => 'User Circle / Representative',
            'heroicon-m-users' => 'Users / Group / Multiple Parties',
            'heroicon-m-shield-check' => 'Shield Check / Police Clearance / Verified',
            'heroicon-m-receipt-percent' => 'Receipt / Tax / GST Certificate',
            'heroicon-m-briefcase' => 'Briefcase / Business / Employment Proof',
            'heroicon-m-clipboard-document-check' => 'Clipboard Check / Inspection Report',
            'heroicon-m-photo' => 'Photo / Photograph',
            'heroicon-m-finger-print' => 'Fingerprint / Biometrics',
            'heroicon-m-key' => 'Key / Possession / Access Document',
            'heroicon-m-map-pin' => 'Map Pin / Address Proof',
            'heroicon-m-newspaper' => 'Newspaper / Public Notice',
            'heroicon-m-phone' => 'Phone / Contact Verification',
            'heroicon-m-envelope' => 'Envelope / Correspondence / Letter',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKycDocumentTypes::route('/'),
        ];
    }
}
