<?php

namespace App\Filament\Resources\NotificationTemplates\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;

class NotificationTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Details')->schema([
                    TextInput::make('event_name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Internal Event Name (e.g. TenancyActivated)'),
                    Select::make('channel')
                        ->options([
                            'email' => 'Email',
                            'whatsapp' => 'WhatsApp',
                            'sms' => 'SMS',
                        ])
                        ->required(),
                    Toggle::make('is_active')
                        ->default(true),
                ])->columns(3),
                
                Section::make('Content')->schema([
                    TextInput::make('subject')
                        ->helperText('Required for Email. Supports {{name}} variables.')
                        ->columnSpanFull(),
                    Textarea::make('body')
                        ->required()
                        ->rows(5)
                        ->helperText('Supports variables like {{name}}, {{property}}, etc.')
                        ->columnSpanFull(),
                ])
            ]);
    }
}
