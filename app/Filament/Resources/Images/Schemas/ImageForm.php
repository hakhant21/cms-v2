<?php

namespace App\Filament\Resources\Images\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** The media library: upload once, reuse from any page or section. */
class ImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Image')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('path')
                            ->label('File')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'])
                            ->disk('public')
                            ->directory('library')
                            ->visibility('public')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('alt')
                            ->label('Alt text')
                            ->helperText('Describe the image for screen readers and search engines.')
                            ->maxLength(255),
                        TextInput::make('disk')
                            ->label('Storage disk')
                            ->default('public')
                            ->disabled()
                            ->dehydrated(),
                    ]),
            ]);
    }
}
