<?php

namespace App\Blocks\Types;

use App\Blocks\AbstractBlockType;
use App\Blocks\PageLinks;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;

/**
 * A page section the editor composes themselves.
 *
 * There are no fixed section templates: an admin adds a section and drops in
 * the pieces it should contain — a title here, an image there, a button under
 * a paragraph — in any order. An optional layout hint tells the renderer how
 * to arrange those pieces: `plain`, `hero`, `grid` and `media` frame the list,
 * while `company_profile`, `farm_journey`, `banner`, `soil_*` and `cta`
 * hand the fields to the site's signature animated scenes.
 */
final class Section extends AbstractBlockType
{
    /**
     * The field palette offered to editors.
     *
     * @var array<string, string>
     */
    public const FIELD_TYPES = [
        'eyebrow' => 'Eyebrow — small label above the title',
        'title' => 'Title',
        'subtitle' => 'Subtitle',
        'paragraph' => 'Paragraph',
        'rich_text' => 'Rich Text',
        'image' => 'Image',
        'gallery' => 'Image Gallery',
        'video' => 'Video',
        'globe' => 'Globe Caption — optional caption for the hero globe',
        'profile' => 'Profile — who the company is (company profile layout)',
        'mission' => 'Mission — what the company sets out to do (company profile layout)',
        'vision' => 'Vision — what the company is building toward (company profile layout)',
        'milestones' => 'Milestones — year and text rows (farm journey layout)',
        'button' => 'Button',
        'list' => 'List of Items',
        'checklist' => 'Checklist — green-tick items (soil dimension layout)',
        'callout' => 'Callout — titled highlight card with bullets (soil dimension layout)',
        'pills' => 'Pills — labelled anchor links (soil intro layout)',
        'stats' => 'Stats — value/label pairs (soil dimension infographic)',
    ];

    /**
     * Optional arrangement hints for the renderer.
     *
     * @var array<string, string>
     */
    public const LAYOUTS = [
        'plain' => 'Plain — fields stacked top to bottom',
        'hero' => 'Hero — opener with the animated Russia–Myanmar globe',
        'grid' => 'Grid — list fields shown as cards',
        'media' => 'Media — text beside an image or video',
        'company_profile' => 'Company profile — glowing 01/02/03 statement cards',
        'farm_journey' => 'Farm journey — animated field scene with milestones',
        'cta' => 'Call to action — full width band with a button',
        'banner' => 'Banner — photo or gradient page header with a breadcrumb',
        'soil_intro' => 'Soil intro — two-tone heading, subtitle and pill links',
        'soil_dimension' => 'Soil dimension — one physical/chemical/biological section',
        'cta_lime' => 'Call to action (lime) — bright lime band with a button',
    ];

    /**
     * Which infographic the `soil_dimension` scene renders.
     *
     * @var array<string, string>
     */
    public const VARIANTS = [
        'structure' => 'Structure — challenges, with Ruma, improved',
        'nutrient' => 'Nutrient — before / after uptake',
        'biological' => 'Biological — degraded vs healthy soil',
        'before_after' => 'Before / after — two-panel comparison',
    ];

    public function key(): string
    {
        return 'section';
    }

    public function label(): string
    {
        return 'Section';
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            Select::make('layout')
                ->label('Layout')
                ->options(self::LAYOUTS)
                ->default('plain')
                ->required()
                ->live()
                ->helperText('How the fields should be arranged on the page.'),
            Select::make('variant')
                ->label('Infographic')
                ->options(self::VARIANTS)
                ->default('structure')
                ->visible(fn (Get $get): bool => $get('layout') === 'soil_dimension')
                ->helperText('Which illustrated stat panel this soil dimension renders.'),
            Repeater::make('fields')
                ->label('Section Fields')
                ->helperText('Add the pieces this section is built from, in the order they should appear.')
                ->reorderable()
                ->collapsible()
                ->collapsed()
                ->defaultItems(1)
                ->itemLabel(fn (array $state): string => self::itemLabel($state))
                ->schema([
                    Select::make('type')
                        ->label('Field Type')
                        ->options(self::FIELD_TYPES)
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                    Group::make()
                        ->columns(2)
                        ->columnSpanFull()
                        ->schema(fn (Get $get): array => self::fieldComponents((string) $get('type'))),
                ])
                ->addActionLabel('Add Field')
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'layout' => 'plain',
            'fields' => [
                ['type' => 'title', 'value' => 'New Section'],
            ],
        ];
    }

    /**
     * The editor components for one palette entry.
     *
     * @return array<int, mixed>
     */
    public static function fieldComponents(?string $type): array
    {
        return match ($type) {
            'eyebrow' => [
                TextInput::make('value')->label('Eyebrow')->required()->maxLength(120),
            ],
            'title' => [
                TextInput::make('value')->label('Title')->required()->maxLength(255),
            ],
            'subtitle' => [
                Textarea::make('value')->label('Subtitle')->rows(2)->columnSpanFull(),
            ],
            'paragraph' => [
                Textarea::make('value')->label('Paragraph')->rows(4)->columnSpanFull(),
            ],
            'rich_text' => [
                RichEditor::make('value')
                    ->label('Content')
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('editor-images')
                    ->columnSpanFull(),
            ],
            'image' => [
                FileUpload::make('value')
                    ->label('Image')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'])
                    ->disk('public')
                    ->directory('page-sections')
                    ->visibility('public')
                    ->required(),
                TextInput::make('alt')->label('Alt Text')->placeholder('Describe the image for accessibility')->maxLength(255),
            ],
            'video' => [
                TextInput::make('value')
                    ->label('Video link')
                    ->placeholder('https://www.youtube.com/watch?v=...')
                    ->helperText('Paste a YouTube or Vimeo link. It is turned into a player automatically.')
                    ->url()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('alt')->label('Title')->placeholder('Shown to screen readers')->maxLength(255),
            ],
            'globe' => [
                TextInput::make('value')
                    ->label('Caption')
                    ->placeholder('Growing a greener connection across continents')
                    ->maxLength(255)
                    ->helperText('Optional. The globe itself is part of the hero design and always shown.'),
            ],
            'profile' => [
                Textarea::make('value')->label('Profile Statement')->rows(3)->columnSpanFull(),
            ],
            'mission' => [
                Textarea::make('value')->label('Mission Statement')->rows(3)->columnSpanFull(),
            ],
            'vision' => [
                Textarea::make('value')->label('Vision Statement')->rows(3)->columnSpanFull(),
            ],
            'milestones' => [
                Repeater::make('items')
                    ->label('Milestones')
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(1)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('year')->label('Year')->required()->maxLength(24)->placeholder('2003'),
                        Textarea::make('text')->label('Milestone Text')->rows(2),
                    ]),
            ],
            'button' => [
                TextInput::make('value')->label('Button Label')->required()->maxLength(120),
                // The page the button opens, read from the pages themselves —
                // see PageLinks. `$get('url')` is this field's own value, which
                // is kept on the list so a link to a page that has since been
                // unpublished still saves instead of failing validation.
                Select::make('url')
                    ->label('Button Destination')
                    ->options(fn (Get $get): array => PageLinks::options($get->string('url', isNullable: true)))
                    ->searchable()
                    ->placeholder('Pick a page')
                    ->helperText('Every published page, by its title. The address is stored without a language prefix, so the button works in every language.'),
            ],
            'gallery' => [
                Repeater::make('items')
                    ->label('Images')
                    ->reorderable()
                    ->defaultItems(1)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('src')
                            ->label('Image')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'])
                            ->disk('public')
                            ->directory('page-sections')
                            ->visibility('public')
                            ->required(),
                        TextInput::make('alt')->label('Alt Text')->maxLength(255),
                    ]),
            ],
            'list' => [
                Repeater::make('items')
                    ->label('Items')
                    ->reorderable()
                    ->defaultItems(1)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')->label('Item Title')->required()->maxLength(255),
                        Textarea::make('text')->label('Item Text')->rows(2),
                    ]),
            ],
            'checklist' => [
                Repeater::make('items')
                    ->label('Checklist Items')
                    ->reorderable()
                    ->defaultItems(1)
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('text')->label('Item')->rows(2)->required(),
                    ]),
            ],
            'callout' => [
                TextInput::make('value')->label('Callout Title')->required()->maxLength(255)->columnSpanFull(),
                Textarea::make('text')->label('Callout Text')->rows(3)->columnSpanFull(),
                Repeater::make('items')
                    ->label('Callout Bullets')
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('text')->label('Bullet')->rows(2),
                    ]),
            ],
            'pills' => [
                Repeater::make('items')
                    ->label('Pills')
                    ->reorderable()
                    ->defaultItems(1)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('value')->label('Label')->required()->maxLength(120),
                        TextInput::make('url')->label('Anchor')->placeholder('#physical')->maxLength(120),
                    ]),
            ],
            'stats' => [
                Repeater::make('items')
                    ->label('Stats')
                    ->reorderable()
                    ->defaultItems(1)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('value')->label('Value')->required()->maxLength(60)->placeholder('33%'),
                        TextInput::make('title')->label('Label')->required()->maxLength(120),
                    ]),
            ],
            default => [],
        };
    }

    /**
     * A human label for a collapsed field row.
     *
     * @param  array<string, mixed>  $state
     */
    public static function itemLabel(array $state): string
    {
        $type = str((string) ($state['type'] ?? 'field'))->headline()->toString();

        $value = $state['value'] ?? null;

        if (is_string($value) && trim($value) !== '') {
            return $type.': '.str(trim($value))->limit(48)->toString();
        }

        return filled($state['items'] ?? null)
            ? $type.' ('.count($state['items']).')'
            : $type;
    }
}
