@php
    /*
     * Inline filters, rendered right after the search box through the
     * `tables::toolbar.search.after` hook.
     *
     * Two things matter here. Filament's admin CSS is compiled from the package
     * own sources, so a utility class that is not already used by Filament does
     * not exist at all — the layout is therefore carried by the classes Filament
     * ships (`fi-*`, `fi-sr-only`) plus a handful of inline styles. And the hook
     * lands inside the toolbar flex row that already holds the search field, so
     * the selects are emitted as plain siblings: they inherit that row gap and
     * sit on the same line as the search box, in reading order.
     *
     * Each select writes straight into the state the filters already use
     * (`tableFilters.<name>.value`), so Filament's query, indicators and page
     * reset keep working unchanged.
     */
    $inlineFilters = $table === null ? [] : \App\Filament\Support\InlineFilters::for($table);
    $filterState = is_array($state ?? null) ? $state : [];
@endphp

@if ($inlineFilters !== [])
    @foreach ($inlineFilters as $filter)
        @php $current = \App\Filament\Support\InlineFilters::current($filterState, $filter['name']); @endphp

        <label class="fi-sr-only" for="table-filter-{{ $filter['name'] }}">{{ $filter['label'] }}</label>

        <x-filament::input.wrapper style="min-width: 11rem; flex: 0 0 auto">
            <x-filament::input.select
                :id="'table-filter-' . $filter['name']"
                wire:model.live="tableFilters.{{ $filter['name'] }}.value"
            >
                <option value="">{{ $filter['label'] }}</option>

                @foreach ($filter['options'] as $value => $optionLabel)
                    <option value="{{ $value }}" @selected($current === (string) $value)>{{ $optionLabel }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    @endforeach

    @if (collect($filterState)->filter(fn ($values): bool => filled(is_array($values) ? ($values['value'] ?? null) : $values))->isNotEmpty())
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-o-x-mark"
            wire:click="resetTableFiltersForm"
        >
            {{ __('filament-tables::table.filters.actions.reset.label') }}
        </x-filament::button>
    @endif
@endif
