<?php

namespace App\Blocks\Types;

use App\Blocks\AbstractBlockType;
use Filament\Forms\Components\TextInput;

/**
 * Phone, email and address, pulled live from the site settings so the team
 * updates them in one place instead of editing every page.
 */
final class ContactDetails extends AbstractBlockType
{
    public function key(): string
    {
        return 'contact_details';
    }

    public function label(): string
    {
        return 'Contact Details';
    }

    public function kind(): string
    {
        return 'collection';
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            TextInput::make('title')->label('Section Title')->placeholder('Heading above the contact details'),
        ];
    }
}
