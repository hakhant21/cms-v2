<?php

namespace App\Blocks\Types;

use App\Blocks\AbstractBlockType;
use Filament\Forms\Components\TextInput;

/** Renders the Livewire contact form wherever the editor places it. */
final class ContactForm extends AbstractBlockType
{
    public function key(): string
    {
        return 'contact_form';
    }

    public function label(): string
    {
        return 'Contact Form';
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
            TextInput::make('title')->label('Section Title')->placeholder('Heading above the contact form'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return ['title' => 'Send a Message'];
    }
}
