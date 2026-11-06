<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Blocks\BlockRegistry;
use App\Blocks\BlockType;
use App\Models\Image;
use App\Models\Language;
use App\Models\Page;
use App\Support\BlockStructure;
use App\Support\Text;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block as BuilderBlock;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
 * The default language is the one an editor writes; on save the other
 * languages are machine-translated from it unless they were written by hand.
 * Their tabs stay editable for exactly that: wording typed there is kept.
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
                        Toggle::make('auto_translate')
                            ->label('Auto-translate on save')
                            ->default(true)
                            ->helperText('Write the default language once: saving translates it into the other languages in the background. Languages you edit by hand are kept as you wrote them.')
                            ->columnSpanFull(),
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
     * Sections are managed once, in whichever tab the editor happens to be
     * working in: adding, removing, cloning or reordering there re-fits every
     * other language in place — images included, so an upload happens once
     * for the whole page. Words stay per-language, and every tab keeps its
     * own, for editors who want to write a language themselves instead of
     * letting the machine do it.
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
                        ->maxLength(255)
                        ->placeholder($code === $primary ? null : 'Leave empty to translate from the default language'),
                    Textarea::make("translations.{$code}.excerpt")
                        ->label('Short summary')
                        ->rows(3)
                        ->helperText($code === $primary
                            ? 'Shown on cards, and used as the default meta description.'
                            : 'Leave empty to translate automatically, or write your own — what you type here is kept.'),
                    self::sectionsBuilder($code, $languages),
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
                                ->visibility('public')
                                ->helperText('Uploaded once, in any language — every tab follows.')
                                ->afterStateUpdated(self::mirrorToOtherTabs("translations.{$code}.seo.og_image", $code, $languages)),
                            Select::make("translations.{$code}.seo.robots")
                                ->label('Search engines')
                                ->options([
                                    'index,follow' => 'Index this page',
                                    'noindex,follow' => 'Hide from search results',
                                ])
                                ->placeholder('Index this page')
                                ->afterStateUpdated(self::mirrorToOtherTabs("translations.{$code}.seo.robots", $code, $languages)),
                        ]),
                ]);
        }

        return $tabs;
    }

    /**
     * The section builder for one language. Structure is shared: a block
     * added, removed, cloned or reordered in ANY tab is mirrored into every
     * other tab, keeping the words each language already has. An editor
     * therefore works in one language only — whichever they prefer — and
     * never repeats the same change three times.
     *
     * @param  array<string, string>  $languages  every locale this tab mirrors into
     */
    private static function sectionsBuilder(string $code, array $languages): Builder
    {
        return Builder::make("translations.{$code}.content_blocks")
            ->label('Page sections')
            ->blocks(fn (): array => self::blocks())
            ->blockNumbers(false)
            ->collapsible()
            ->cloneable()
            ->addActionLabel('Add section')
            ->columnSpanFull()
            ->helperText('Add, remove or reorder sections here — every other language follows, keeping the words it already has. Images you upload here are used in every language. Only the wording is per-language: leave it to the automatic translation, or type your own — what you write is kept.')
            ->afterStateUpdated(function (mixed $state, Get $get, callable $set) use ($code, $languages): void {
                $canonical = is_array($state) ? $state : [];

                foreach (array_keys($languages) as $other) {
                    if ($other === $code) {
                        continue;
                    }

                    $path = "translations.{$other}.content_blocks";
                    $current = is_array($get($path)) ? $get($path) : [];
                    $aligned = BlockStructure::mirror($canonical, $current);

                    // Only write when the structure really changed, so typing
                    // words here never disturbs the other tabs — and the
                    // mirrored tabs, whose own hooks fire in turn, settle
                    // instead of bouncing the change back and forth.
                    if (json_encode(array_values($aligned)) !== json_encode(array_values($current))) {
                        $set($path, $aligned);
                    }
                }
            });
    }

    /**
     * Copy one language-neutral value — the share image, the search-engine
     * choice — from the tab it was changed in into every other tab, so it is
     * set once for the whole page. Only written where it actually differs,
     * so the tabs settle instead of bouncing the value around.
     *
     * @param  array<string, string>  $languages
     */
    private static function mirrorToOtherTabs(string $path, string $code, array $languages): Closure
    {
        return function (mixed $state, Get $get, callable $set) use ($path, $code, $languages): void {
            foreach (array_keys($languages) as $other) {
                if ($other === $code) {
                    continue;
                }

                $target = "translations.{$other}.".substr($path, strlen("translations.{$code}."));

                if (json_encode($get($target)) !== json_encode($state)) {
                    $set($target, $state);
                }
            }
        };
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
