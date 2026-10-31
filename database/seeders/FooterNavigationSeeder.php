<?php

namespace Database\Seeders;

use App\Models\NavigationItem;
use Illuminate\Database\Seeder;

/**
 * Gives the footer its own menu.
 *
 * The footer is a separate placement in the admin, so an editor can drop or
 * reorder entries without touching the header. On a fresh install there is no
 * footer menu at all, and the site fell back to repeating the header — a
 * sensible default, but not something an editor could curate. This copies the
 * header once into the footer so the starting point is complete; an existing
 * footer menu is never modified.
 */
class FooterNavigationSeeder extends Seeder
{
    public function run(): void
    {
        $created = self::seed();

        if ($created > 0) {
            $this->command?->info("Footer menu seeded with {$created} links.");
        }
    }

    /**
     * @return int the number of links created, 0 when the footer already exists
     */
    public static function seed(): int
    {
        $alreadySeeded = NavigationItem::query()
            ->placement(NavigationItem::PLACEMENT_FOOTER)
            ->exists();

        if ($alreadySeeded) {
            return 0;
        }

        $header = NavigationItem::tree(NavigationItem::PLACEMENT_HEADER);

        if ($header->isEmpty()) {
            return 0;
        }

        $created = 0;
        $position = 0;

        foreach ($header as $item) {
            $parent = self::copy($item, null, ++$position);
            $created++;

            foreach ($item->children as $offset => $child) {
                self::copy($child, $parent->getKey(), $offset + 1);
                $created++;
            }
        }

        return $created;
    }

    private static function copy(NavigationItem $item, ?int $parentId, int $position): NavigationItem
    {
        $copy = NavigationItem::create([
            'parent_id' => $parentId,
            'placement' => NavigationItem::PLACEMENT_FOOTER,
            'target_type' => $item->target_type,
            'page_id' => $item->page_id,
            'url' => $item->url,
            'sort_order' => $position,
            'is_active' => $item->is_active,
            'opens_new_tab' => $item->opens_new_tab,
        ]);

        foreach ($item->translations as $translation) {
            $copy->translations()->create([
                'locale' => $translation->locale,
                'label' => $translation->label,
            ]);
        }

        return $copy;
    }
}
