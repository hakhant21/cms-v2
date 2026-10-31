<?php

namespace App\Blocks\Types;

use App\Blocks\AbstractBlockType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/** One farmer's trial profile: quote, crop, dates and measured results. */
final class FeedbackProfile extends AbstractBlockType
{
    public function key(): string
    {
        return 'feedback_profile';
    }

    public function label(): string
    {
        return 'Feedback Profile';
    }

    public function inPicker(): bool
    {
        return false;
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            TextInput::make('farmer_name')->label('Farmer Name')->maxLength(255),
            TextInput::make('location')->label('Location')->maxLength(255),
            FileUpload::make('photo')
                ->label('Photo')
                ->image()
                ->disk('public')
                ->directory('feedback')
                ->visibility('public'),
            Textarea::make('quote')->label('Quote')->rows(3)->columnSpanFull(),
            TextInput::make('crops_grown')->label('Crops Grown')->maxLength(255),
            TextInput::make('crop')->label('Crop')->maxLength(255),
            TextInput::make('crop_variety')->label('Crop Variety')->maxLength(255),
            TextInput::make('acreage')->label('Acreage')->maxLength(60),
            DatePicker::make('seed_bed_date')->label('Seed Bed Date'),
            DatePicker::make('transplanting_date')->label('Transplanting Date'),
            DatePicker::make('picking_date')->label('Picking Date'),
            Textarea::make('field_challenge')->label('Field Challenge')->rows(3)->columnSpanFull(),
            Repeater::make('proven_results')
                ->label('Proven Results')
                ->reorderable()
                ->collapsible()
                ->defaultItems(0)
                ->columnSpanFull()
                ->schema([TextInput::make('value')->label('Result')->maxLength(255)]),
            Repeater::make('key_benefits')
                ->label('Key Benefits')
                ->reorderable()
                ->collapsible()
                ->defaultItems(0)
                ->columnSpanFull()
                ->schema([TextInput::make('value')->label('Benefit')->maxLength(255)]),
            TextInput::make('previous_year_yield')->label('Previous Year Yield')->maxLength(120),
            TextInput::make('ruma_field_yield')->label('Ruma Field Yield')->maxLength(120),
            Textarea::make('other_crop_details')->label('Other Crop Details')->rows(3)->columnSpanFull(),
            Textarea::make('treatment')->label('Treatment')->rows(3)->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return ['proven_results' => [], 'key_benefits' => []];
    }
}
