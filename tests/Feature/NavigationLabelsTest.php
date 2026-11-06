<?php

namespace Tests\Feature;

use App\Contracts\Translator;
use App\Filament\Resources\NavigationItems\Pages\CreateNavigationItem;
use App\Filament\Resources\NavigationItems\Pages\EditNavigationItem;
use App\Models\Language;
use App\Models\NavigationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A menu label is written once, in any one language: saving translates the
 * other languages in the background, and a label an editor wrote by hand is
 * kept until they change it themselves.
 *
 * The queue runs synchronously here and the translator is faked, so a save
 * shows its full effect — rows written, flags set — in one request.
 */
class NavigationLabelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::query()->create(['code' => 'my', 'name' => 'Myanmar', 'sort_order' => 2]);
        Language::forgetCache();

        config([
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'gemini-test',
        ]);

        $this->app->instance(Translator::class, $this->fakeTranslator());
    }

    /** A translator that answers deterministically, without the network. */
    private function fakeTranslator(): Translator
    {
        return new class implements Translator
        {
            public function translate(array $texts, string $from, string $to): array
            {
                return array_map(fn (string $text): string => "[{$to}] {$text}", $texts);
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };
    }

    public function test_creating_a_link_with_one_label_fills_every_language(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(CreateNavigationItem::class)
            ->fillForm([
                'placement' => NavigationItem::PLACEMENT_HEADER,
                'target_type' => NavigationItem::TARGET_URL,
                'url' => '/about',
                'translations' => [
                    ['locale' => 'en', 'label' => 'About us'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = NavigationItem::query()->first();

        $this->assertNotNull($item);
        $this->assertDatabaseHas('navigation_item_translations', [
            'navigation_item_id' => $item->getKey(),
            'locale' => 'en',
            'label' => 'About us',
            'is_manual' => true,
        ]);
        $this->assertDatabaseHas('navigation_item_translations', [
            'navigation_item_id' => $item->getKey(),
            'locale' => 'my',
            'label' => '[my] About us',
            'is_manual' => false,
        ]);
    }

    public function test_editing_the_label_once_refreshes_the_machine_languages(): void
    {
        $item = $this->linkWithLabels(['en' => 'About us', 'my' => '[my] About us']);

        $editor = Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditNavigationItem::class, ['record' => $item->getRouteKey()]);

        $state = $editor->get('data.translations');

        foreach ($state as $key => $row) {
            if (($row['locale'] ?? null) === 'en') {
                $state[$key]['label'] = 'Our company';
            }
        }

        $editor->set('data.translations', $state)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('navigation_item_translations', [
            'navigation_item_id' => $item->getKey(),
            'locale' => 'en',
            'label' => 'Our company',
        ]);
        $this->assertDatabaseHas('navigation_item_translations', [
            'navigation_item_id' => $item->getKey(),
            'locale' => 'my',
            'label' => '[my] Our company',
            'is_manual' => false,
        ]);
    }

    public function test_a_label_written_by_hand_survives_later_saves(): void
    {
        $item = $this->linkWithLabels(['en' => 'About us', 'my' => '[my] About us']);

        $editor = User::factory()->admin()->create();

        // The editor rewrites the Burmese label: from now on it is theirs.
        $component = Livewire::actingAs($editor)
            ->test(EditNavigationItem::class, ['record' => $item->getRouteKey()]);

        $state = $component->get('data.translations');

        foreach ($state as $key => $row) {
            if (($row['locale'] ?? null) === 'my') {
                $state[$key]['label'] = 'ကျွန်ုပ်တို့အကြောင်း';
            }
        }

        $component->set('data.translations', $state)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('navigation_item_translations', [
            'navigation_item_id' => $item->getKey(),
            'locale' => 'my',
            'label' => 'ကျွန်ုပ်တို့အကြောင်း',
            'is_manual' => true,
        ]);

        // A later save that only touches English leaves the hand-written
        // label alone.
        $component = Livewire::actingAs($editor)
            ->test(EditNavigationItem::class, ['record' => $item->getRouteKey()]);

        $state = $component->get('data.translations');

        foreach ($state as $key => $row) {
            if (($row['locale'] ?? null) === 'en') {
                $state[$key]['label'] = 'Our company';
            }
        }

        $component->set('data.translations', $state)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('navigation_item_translations', [
            'navigation_item_id' => $item->getKey(),
            'locale' => 'my',
            'label' => 'ကျွန်ုပ်တို့အကြောင်း',
        ]);
        $this->assertDatabaseHas('navigation_item_translations', [
            'navigation_item_id' => $item->getKey(),
            'locale' => 'en',
            'label' => 'Our company',
        ]);
    }

    public function test_an_untouched_save_keeps_the_machine_languages_machine_owned(): void
    {
        // Saving the link details — the address, the order — without touching
        // any label must not turn the translated rows hand-written.
        $item = $this->linkWithLabels(['en' => 'About us', 'my' => '[my] About us']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditNavigationItem::class, ['record' => $item->getRouteKey()])
            ->set('data.sort_order', 5)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('navigation_item_translations', [
            'navigation_item_id' => $item->getKey(),
            'locale' => 'my',
            'is_manual' => false,
        ]);
    }

    /**
     * A header link with one machine-owned label per given locale.
     *
     * @param  array<string, string>  $labels
     */
    private function linkWithLabels(array $labels): NavigationItem
    {
        $item = NavigationItem::query()->create([
            'placement' => NavigationItem::PLACEMENT_HEADER,
            'target_type' => NavigationItem::TARGET_URL,
            'url' => '/about',
        ]);

        foreach ($labels as $locale => $label) {
            $item->translations()->create([
                'locale' => $locale,
                'label' => $label,
                'is_manual' => $locale === 'en',
            ]);
        }

        return $item;
    }
}
