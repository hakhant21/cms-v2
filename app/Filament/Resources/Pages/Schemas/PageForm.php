<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Blocks\BlockRegistry;
use App\Blocks\BlockType;
use App\Models\Image;
use App\Models\Language;
use App\Models\Page;
use App\Support\BlockStructure;
use App\Support\Text;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block as BuilderBlock;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Unique;

/**
 * One form for the whole page: the locale-neutral details on top, then a tab
 * per language holding that language's title, summary, sections and SEO.
 *
 * The section builder is driven by {@see BlockRegistry}, so a new block type
 * appears in the editor without touching this class.
 */
class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page details')
                    ->description('Where this page lives, and who can see it.')
                    ->columns(2)
                    ->schema([
                        Select::make('kind')
                            ->label('Page type')
                            ->options(self::kinds())
                            ->default(Page::KIND_PAGE)
                            ->required()
                            ->live()
                            ->helperText('Products and news get their own address under /products and /news.'),
                        TextInput::make('slug')
                            ->label('Address')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('kind', (string) $get('kind')),
                            )
                            ->helperText('Lowercase words separated by dashes, e.g. about-us.'),
                        Select::make('status')
                            ->options(self::statuses())
                            ->default(Page::STATUS_DRAFT)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->seconds(false)
                            ->helperText('Leave empty to publish immediately.'),
                        Select::make('featured_image_id')
                            ->label('Featured image')
                            ->relationship('featuredImage', 'path')
                            ->getOptionLabelFromRecordUsing(fn (Image $record): string => $record->altText() ?: Text::fileName((string) $record->path))
                            ->searchable()
                            ->preload()
                            ->createOptionForm(self::imageForm())
                            ->createOptionUsing(fn (array $data): int => Image::query()->create(self::imageData($data))->getKey()),
                        Select::make('gallery')
                            ->label('Product images')
                            ->relationship(
                                'galleryImages',
                                'path',
                                // The relation already orders by pivot sort_order.
                                // Only add a tie-breaker here, and use a column
                                // that is in the SELECT list: MySQL rejects an
                                // ORDER BY on `page_images.id` alongside DISTINCT
                                // ("Expression #3 of ORDER BY clause is not in
                                // SELECT list"), which Filament adds for a
                                // searchable multiple relationship.
                                modifyQueryUsing: fn (EloquentBuilder $query): EloquentBuilder => $query
                                    ->orderBy('images.id'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Image $record): string => $record->altText() ?: Text::fileName((string) $record->path))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->createOptionForm(self::imageForm())
                            ->createOptionUsing(fn (array $data): int => Image::query()->create(self::imageData($data))->getKey())
                            ->helperText('Extra photos of the product. The first one is used when there is no featured image.')
                            ->columnSpanFull(),
                        Select::make('animation')
                            ->label('Animation')
                            ->options(self::animations())
                            ->placeholder('Use the site default'),
                        TextInput::make('sort_order')
                            ->label('Sort order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first in lists.'),
                    ]),
                Tabs::make('Translations')
                    ->columnSpanFull()
                    ->tabs(self::translationTabs()),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function kinds(): array
    {
        return [
            Page::KIND_PAGE => 'Page',
            Page::KIND_PRODUCT => 'Product',
            Page::KIND_NEWS => 'News',
            Page::KIND_PROJECT => 'Next project',
            Page::KIND_FAQ => 'FAQ',
            Page::KIND_FEEDBACK => 'Farmer feedback',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            Page::STATUS_DRAFT => 'Draft',
            Page::STATUS_PUBLISHED => 'Published',
            Page::STATUS_ARCHIVED => 'Archived',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function animations(): array
    {
        return [
            'dynamic' => 'Dynamic',
            'calm' => 'Calm',
            'none' => 'None',
        ];
    }

    /**
     * The active locales, each rendered as its own tab.
     *
     * Sections are managed once, in the default language's tab: adding,
     * removing, cloning or reordering there re-fits every other language in
     * place, so an editor never deletes the same section three times.
     *
     * @return array<int, Tab>
     */
    private static function translationTabs(): array
    {
        $languages = self::languages();
        $primary = self::primaryLocale($languages);
        $tabs = [];

        foreach ($languages as $code => $label) {
            $tabs[] = Tab::make($label)
                ->schema([
                    TextInput::make("translations.{$code}.title")
                        ->label('Page title')
                        ->maxLength(255),
                    Textarea::make("translations.{$code}.excerpt")
                        ->label('Short summary')
                        ->rows(3)
                        ->helperText('Shown on cards, and used as the default meta description.'),
                    self::sectionsBuilder($code, $code === $primary ? $languages : null),
                    Fieldset::make('Search engine listing')
                        ->columns(1)
                        ->schema([
                            TextInput::make("translations.{$code}.seo.title")
                                ->label('Meta title')
                                ->maxLength(255),
                            Textarea::make("translations.{$code}.seo.description")
                                ->label('Meta description')
                                ->rows(2)
                                ->maxLength(320),
                            FileUpload::make("translations.{$code}.seo.og_image")
                                ->label('Share image')
                                ->image()
                                ->disk('public')
                                ->directory('seo')
                                ->visibility('public'),
                            Select::make("translations.{$code}.seo.robots")
                                ->label('Search engines')
                                ->options([
                                    'index,follow' => 'Index this page',
                                    'noindex,follow' => 'Hide from search results',
                                ])
                                ->placeholder('Index this page'),
                        ]),
                ]);
        }

        return $tabs;
    }

    /**
     * The section builder for one language. In the default language it owns
     * the page's structure and mirrors every change into the other tabs;
     * elsewhere the sections only arrive from there, so a block is added,
     * removed or reordered exactly once for the whole page.
     *
     * @param  array<string, string>|null  $syncInto  the locales this tab drives, null when another tab leads
     */
    private static function sectionsBuilder(string $code, ?array $syncInto): Builder
    {
        $builder = Builder::make("translations.{$code}.content_blocks")
            ->label('Page sections')
            ->blocks(fn (): array => self::blocks())
            ->blockNumbers(false)
            ->collapsible()
            ->cloneable()
            ->addActionLabel('Add section')
            ->columnSpanFull();

        if ($syncInto === null) {
            return $builder
                ->helperText('Sections are managed in the main language tab — here you only translate the words.')
                ->disableItemCreation()
                ->disableItemDeletion()
                ->disableItemMovement();
        }

        return $builder
            ->helperText('Add, remove or reorder sections here — every other language follows, keeping the words it already has.')
            ->afterStateUpdated(function (mixed $state, Get $get, callable $set) use ($code, $syncInto): void {
                $canonical = is_array($state) ? $state : [];

                foreach (array_keys($syncInto) as $other) {
                    if ($other === $code) {
                        continue;
                    }

                    $path = "translations.{$other}.content_blocks";
                    $current = is_array($get($path)) ? $get($path) : [];
                    $aligned = BlockStructure::align($canonical, $current);

                    // Only write when the words really need re-fitting, so
                    // typing here never disturbs the other tabs.
                    if (json_encode(array_values($aligned)) !== json_encode(array_values($current))) {
                        $set($path, $aligned);
                    }
                }
            });
    }

    /**
     * The language whose tab owns the page structure.
     *
     * @param  array<string, string>  $languages
     */
    private static function primaryLocale(array $languages): string
    {
        $default = Language::defaultCode();

        return isset($languages[$default]) ? $default : (string) array_key_first($languages);
    }

    /**
     * The languages an editor can write in, labelled by their own name.
     *
     * @return array<string, string>
     */
    public static function languages(): array
    {
        /** @var Collection<int, Language> $languages */
        $languages = Language::query()->active()->get();

        if ($languages->isEmpty()) {
            return [(string) config('app.locale', 'en') => 'Default language'];
        }

        return $languages
            ->mapWithKeys(fn (Language $language): array => [
                $language->code => (string) ($language->native_name ?: $language->name),
            ])
            ->all();
    }

    /**
     * @return array<int, BuilderBlock>
     */
    private static function blocks(): array
    {
        return array_values(array_map(
            fn (BlockType $type): BuilderBlock => BuilderBlock::make($type->key())
                ->label($type->label())
                ->schema($type->fields()),
            app(BlockRegistry::class)->all(),
        ));
    }

    /**
     * @return array<int, mixed>
     */
    private static function imageForm(): array
    {
        return [
            FileUpload::make('path')
                ->label('Image file')
                ->image()
                ->disk('public')
                ->directory('pages')
                ->visibility('public')
                ->required()
                ->columnSpanFull(),
            TextInput::make('alt')
                ->label('Alt text')
                ->helperText('Describe the image for screen readers and search engines.')
                ->maxLength(255),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function imageData(array $data): array
    {
        return [
            'disk' => 'public',
            'path' => (string) ($data['path'] ?? ''),
            'alt' => $data['alt'] ?? null,
            'original_name' => Text::fileName((string) ($data['path'] ?? '')),
        ];
    }
}
