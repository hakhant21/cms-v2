<?php

namespace App\Blocks\Types;

use App\Blocks\AbstractBlockType;
use App\Blocks\PageLinks;
use App\Models\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/** Gathers news posts, projects or any other page kind into a list. */
final class PostCollection extends AbstractBlockType
{
    public function key(): string
    {
        return 'post_collection';
    }

    public function label(): string
    {
        return 'Post Collection';
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
            TextInput::make('title')->label('Section Title')->placeholder('Heading above the posts or news'),
            Select::make('source')
                ->label('Content Type')
                ->options([
                    Page::KIND_NEWS => 'News',
                    Page::KIND_PROJECT => 'Next Projects',
                ])
                ->default(Page::KIND_NEWS)
                ->required()
                ->helperText('Which kind of page this section collects.'),
            TextInput::make('limit')
                ->label('Number of Posts')
                ->helperText('Maximum number of posts to display.')
                ->numeric()
                ->default(3)
                ->minValue(1)
                ->maxValue(24),
            // The address behind "Browse all". It used to be hard-wired to
            // `/news`, which pointed a project list at a news page and broke
            // whenever that page was unpublished — now the editor aims the
            // link at a page of their choice, or leaves it empty to drop the
            // link altogether.
            Select::make('url')
                ->label('Browse All Link')
                ->options(fn (Get $get): array => PageLinks::options($get->string('url', isNullable: true)))
                ->searchable()
                ->placeholder('No link')
                ->helperText('The page "Browse all" opens. Leave it empty to hide the link.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return ['title' => 'Latest News', 'source' => Page::KIND_NEWS, 'limit' => 3, 'url' => null];
    }
}
