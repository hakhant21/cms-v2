<?php

namespace App\Filament\Resources\ContactMessages\Schemas;

use App\Models\ContactMessage;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The inbox record. Everything a visitor typed is editable here so a message
 * can be corrected, and `status` lets the team track what happened next.
 */
class ContactMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Message')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('subject')
                            ->label('Subject')
                            ->maxLength(255),
                        Textarea::make('message')
                            ->label('Message')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull(),
                    ]),
                Section::make('Handling')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(ContactMessage::statusOptions())
                            ->default(ContactMessage::STATUS_NEW)
                            ->required()
                            ->native(false),
                        TextInput::make('locale')
                            ->label('Written in')
                            ->maxLength(8)
                            ->helperText('The language the visitor was browsing when they wrote.'),
                    ]),
            ]);
    }
}
