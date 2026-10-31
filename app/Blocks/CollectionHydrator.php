<?php

namespace App\Blocks;

use App\Models\Page;

/**
 * Resolves collection blocks against the pages library.
 *
 * Collection blocks carry no content of their own: at render time they gather
 * the published pages of their kind into `data.items`. Limits and sources are
 * clamped here because block payloads are treated as untrusted input.
 */
final class CollectionHydrator
{
    /**
     * The block types that gather pages. Contact and map blocks share the
     * `collection` kind but hold their own content, so they are excluded.
     *
     * @var array<int, string>
     */
    private const GATHERERS = [
        'product_collection',
        'post_collection',
        'feedback_collection',
        'faq_accordion',
    ];

    /**
     * @var array<int, string>
     */
    private const POST_SOURCES = [Page::KIND_NEWS, Page::KIND_PROJECT];

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function hydrate(array $blocks, ?Page $current = null): array
    {
        return array_map(
            fn (array $block): array => $this->hydrateBlock($block, $current),
            $blocks,
        );
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function hydrateBlock(array $block, ?Page $current): array
    {
        $type = (string) ($block['type'] ?? '');

        if (! in_array($type, self::GATHERERS, true)) {
            return $block;
        }

        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        $data['items'] = match ($type) {
            'product_collection' => $this->cards(Page::KIND_PRODUCT, $data, $current),
            'post_collection' => $this->cards($this->source($data), $data, $current, newestFirst: true),
            'feedback_collection' => $this->cards(Page::KIND_FEEDBACK, $data, $current),
            default => $this->faqs($data),
        };

        return ['type' => $type, 'data' => $data];
    }

    /**
     * The whitelisted page kind a post collection gathers.
     *
     * @param  array<string, mixed>  $data
     */
    private function source(array $data): string
    {
        $source = (string) ($data['source'] ?? Page::KIND_NEWS);

        return in_array($source, self::POST_SOURCES, true) ? $source : Page::KIND_NEWS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function limit(array $data, int $default): int
    {
        return max(1, min(24, (int) ($data['limit'] ?? $default)));
    }

    /**
     * Card payloads for the published pages of one kind. A page gathering its
     * own kind excludes itself, so a product list never repeats its header.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function cards(string $kind, array $data, ?Page $current, bool $newestFirst = false): array
    {
        $query = Page::query()
            ->published()
            ->ofKind($kind)
            ->with(['featuredImage', 'translations'])
            ->when(
                $current !== null && $current->kind === $kind,
                fn ($builder) => $builder->whereKeyNot($current->getKey()),
            );

        $query = $newestFirst
            ? $query->orderByDesc('published_at')->orderBy('sort_order')
            : $query->ordered();

        return $query
            ->limit($this->limit($data, 6))
            ->get()
            ->map(fn (Page $page): array => $this->card($page))
            ->all();
    }

    /**
     * FAQ items, optionally filtered by their raw (untranslated) context.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function faqs(array $data): array
    {
        $context = trim((string) ($data['context'] ?? ''));

        return Page::query()
            ->published()
            ->ofKind(Page::KIND_FAQ)
            ->with(['featuredImage', 'translations'])
            ->ordered()
            ->limit(50)
            ->get()
            ->filter(fn (Page $page): bool => $context === '' || $this->hasFaqContext($page, $context))
            ->map(fn (Page $page): array => $this->card($page))
            ->values()
            ->all();
    }

    private function hasFaqContext(Page $page, string $context): bool
    {
        foreach ($page->localizedContentBlocks() as $block) {
            if (($block['type'] ?? null) !== 'faq_item') {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if (trim((string) ($data['context'] ?? '')) === $context) {
                return true;
            }
        }

        return false;
    }

    /**
     * One gathered page as the card the Blade collection views render.
     *
     * @return array<string, mixed>
     */
    private function card(Page $page): array
    {
        return [
            'id' => $page->getKey(),
            'slug' => $page->slug,
            'url' => $page->path(),
            'title' => $page->localizedTitle(),
            'excerpt' => $page->localizedExcerpt(),
            'image_url' => $page->featuredImage?->url(),
            'published_at' => $page->published_at?->toIso8601String(),
            'data' => $this->mergedData($page),
        ];
    }

    /**
     * Every block's data merged into one array, which is how a FAQ card finds
     * its question and a feedback card finds its farmer quote.
     *
     * @return array<string, mixed>
     */
    private function mergedData(Page $page): array
    {
        $payload = [];

        foreach ($page->localizedContentBlocks() as $block) {
            $data = $block['data'] ?? null;

            if (is_array($data)) {
                $payload = array_merge($payload, $data);
            }
        }

        return $payload;
    }
}
