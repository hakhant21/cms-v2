<?php

namespace App\Blocks\Types;

use App\Blocks\AbstractBlockType;
use App\Support\VideoEmbed;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * One embedded video, anywhere on a page.
 *
 * The editor pastes the address they copied from YouTube or Vimeo — the watch
 * page, the share link, a Shorts address — and {@see VideoEmbed} turns it into
 * a player URL. Storing the original link keeps the CMS honest about what was
 * entered, and means a future provider can be supported without re-saving every
 * page.
 */
final class Video extends AbstractBlockType
{
    public function key(): string
    {
        return 'video';
    }

    public function label(): string
    {
        return 'Video — YouTube or Vimeo embed';
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            TextInput::make('url')
                ->label('Video link')
                ->placeholder('https://www.youtube.com/watch?v=...')
                ->helperText('Paste a YouTube or Vimeo link. Watch, share and Shorts addresses all work.')
                ->required()
                ->maxLength(500)
                ->columnSpanFull()
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (! VideoEmbed::isEmbeddable(is_string($value) ? $value : null)) {
                            $fail('That link is not a YouTube or Vimeo video that can be embedded.');
                        }
                    },
                ]),
            TextInput::make('title')
                ->label('Section title')
                ->placeholder('Heading above the video')
                ->maxLength(255),
            Select::make('aspect')
                ->label('Shape')
                ->options([
                    'landscape' => 'Landscape — normal video (16:9)',
                    'portrait' => 'Portrait — Shorts and Reels (9:16)',
                ])
                ->default('landscape')
                ->selectablePlaceholder(false),
            TextInput::make('caption')
                ->label('Caption')
                ->placeholder('Shown under the video')
                ->maxLength(255)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'aspect' => 'landscape',
        ];
    }
}
