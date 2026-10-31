<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\NavigationItems\Pages\ListNavigationItems;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Support\InlineFilters;
use App\Models\Language;
use App\Models\Page;
use App\Models\User;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Filters are choices, not a panel to open.
 *
 * Editors asked for the filter controls to sit beside the search box, so the
 * tables render them through a toolbar hook and hide Filament's own trigger.
 * These tests hold that arrangement in place: the state path has to be the one
 * the table really uses, and no choice filter may be left without a control.
 */
class AdminTableFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::forgetCache();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_page_list_renders_a_select_per_filter(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/pages')
            ->assertOk()
            ->assertSee('wire:model.live="tableFilters.kind.value"', false)
            ->assertSee('wire:model.live="tableFilters.status.value"', false);
    }

    public function test_the_click_to_open_filter_button_is_gone(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/pages')
            ->assertOk()
            ->assertDontSee('fi-ta-filters-trigger', false);
    }

    public function test_the_search_box_comes_before_the_filter_selects(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/pages')
            ->assertOk()
            ->getContent();

        $search = strpos($html, 'fi-ta-search-field');
        $filter = strpos($html, 'tableFilters.kind.value');

        $this->assertNotFalse($search, 'The page list should show a search field.');
        $this->assertNotFalse($filter, 'The page list should show an inline kind filter.');
        $this->assertLessThan(
            $filter,
            $search,
            'The search field is read first; the filters follow it in the toolbar.',
        );
    }

    public function test_choosing_a_value_in_the_select_narrows_the_list(): void
    {
        Page::query()->create([
            'kind' => Page::KIND_NEWS,
            'slug' => 'a-story',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        Page::query()->create([
            'kind' => Page::KIND_PAGE,
            'slug' => 'a-page',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords(Page::query()->get())
            ->set('tableFilters.kind.value', Page::KIND_NEWS)
            ->assertCanSeeTableRecords(Page::query()->where('slug', 'a-story')->get())
            ->assertCanNotSeeTableRecords(Page::query()->where('slug', 'a-page')->get());
    }

    public static function listScreens(): array
    {
        return [
            'pages' => [ListPages::class],
            'menus' => [ListNavigationItems::class],
            'messages' => [ListContactMessages::class],
            'users' => [ListUsers::class],
            'redirects' => [ListRedirects::class],
        ];
    }

    /**
     * Nothing may be left behind the hidden trigger.
     *
     * @param  class-string  $component
     */
    #[DataProvider('listScreens')]
    public function test_every_filter_of_these_tables_has_an_inline_control(string $component): void
    {
        $this->actingAs($this->admin);

        $table = Livewire::test($component)->instance()->getTable();

        $this->assertInstanceOf(Table::class, $table);
        $this->assertGreaterThan(0, count($table->getFilters()));
        $this->assertCount(count($table->getFilters()), InlineFilters::for($table));
    }
}
