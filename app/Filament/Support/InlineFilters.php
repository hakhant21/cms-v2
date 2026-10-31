<?php

namespace App\Filament\Support;

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The table filters that can be shown as a plain select in the toolbar.
 *
 * Filament puts filters behind a "Filters" button. Editors asked for the
 * choices to be visible next to the search box instead, so the filters that
 * are simply a list of options are lifted out of the panel and handed to
 * `filament.tables.inline-filters`; anything else (a date range, a custom
 * filter form, a relationship lookup) keeps the button it needs.
 */
final class InlineFilters
{
    /**
     * @return array<int, array{name: string, label: string, options: array<string, string>}>
     */
    public static function for(Table $table): array
    {
        $filters = [];

        foreach ($table->getFilters() as $name => $filter) {
            if (! $filter instanceof SelectFilter) {
                continue;
            }

            // A relationship filter resolves its options against a loaded
            // record, which the toolbar does not have. It stays in the panel.
            if ($filter->queriesRelationships()) {
                continue;
            }

            $options = self::options($filter);

            if ($options === []) {
                continue;
            }

            $label = $filter->getLabel();

            $filters[] = [
                'name' => (string) $name,
                'label' => $label instanceof Htmlable ? strip_tags($label->toHtml()) : (string) $label,
                'options' => $options,
            ];
        }

        return $filters;
    }

    /**
     * The options of the select the filter would have rendered itself.
     *
     * @return array<string, string>
     */
    private static function options(SelectFilter $filter): array
    {
        $options = [];

        foreach ($filter->getFormField()->getOptions() as $value => $label) {
            // A grouped select arrives as [[group => label], ...]; flattening
            // keeps the toolbar a plain list of options.
            foreach (is_array($label) ? $label : [$value => $label] as $key => $text) {
                $options[(string) $key] = $text instanceof Htmlable ? strip_tags($text->toHtml()) : (string) $text;
            }
        }

        return $options;
    }

    /**
     * The value a filter currently holds, shaped for `<option value="…">`.
     */
    public static function current(?array $state, string $name): string
    {
        $value = $state[$name]['value'] ?? null;

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
