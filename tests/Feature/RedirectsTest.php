<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Old addresses keep working after a rebuild.
 *
 * A redirect row carries no locale: one row answers `/blog`, `/my/blog` and
 * `/ru/blog`, pointing each visitor at the same page in the language they were
 * already reading. These tests also pin down the edges — writes are never
 * redirected, an inactive row is ignored, and a row that points at itself
 * cannot trap a visitor in a loop.
 */
class RedirectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'sort_order' => 1]);
        Language::query()->create(['code' => 'my', 'name' => 'Myanmar', 'sort_order' => 2]);
        Language::forgetCache();
    }

    private function redirectTo(string $from, string $to, int $status = Redirect::STATUS_MOVED_PERMANENTLY): Redirect
    {
        return Redirect::query()->create([
            'from_path' => $from,
            'to_path' => $to,
            'status_code' => $status,
            'is_active' => true,
        ]);
    }

    public function test_an_old_address_answers_with_the_new_one(): void
    {
        $this->redirectTo('/blog', '/news');

        $this->get('/blog')
            ->assertStatus(301)
            ->assertRedirect('/news');
    }

    public function test_the_language_the_visitor_was_reading_is_kept(): void
    {
        $this->redirectTo('blog', '/news');

        $this->get('/my/blog')->assertStatus(301)->assertRedirect('/my/news');
        $this->get('/blog')->assertStatus(301)->assertRedirect('/news');
    }

    public function test_a_pasted_absolute_url_is_stored_as_a_path(): void
    {
        $this->redirectTo('https://www.rumaorganics.com/about-us/?ref=old', 'about');

        $this->assertSame('about-us', Redirect::query()->value('from_path'));
        $this->assertSame('/about', Redirect::query()->value('to_path'));

        $this->get('/about-us')->assertStatus(301)->assertRedirect('/about');
    }

    public function test_the_query_string_survives(): void
    {
        $this->redirectTo('blog', '/news');

        $this->get('/blog?page=2')->assertStatus(301)->assertRedirect('/news?page=2');
    }

    public function test_a_temporary_redirect_answers_with_302(): void
    {
        $this->redirectTo('event', '/news', Redirect::STATUS_FOUND);

        $this->get('/event')->assertStatus(302)->assertRedirect('/news');
    }

    public function test_a_row_that_is_switched_off_is_ignored(): void
    {
        $this->redirectTo('blog', '/news')->update(['is_active' => false]);

        $this->get('/blog')->assertNotFound();
    }

    public function test_the_visit_is_counted(): void
    {
        $redirect = $this->redirectTo('blog', '/news');

        $this->get('/blog');
        $this->get('/blog');

        $this->assertSame(2, $redirect->fresh()->hits);
    }

    public function test_writes_are_never_redirected(): void
    {
        $this->redirectTo('contact', '/faq');

        $this->post('/contact')->assertStatus(405);
    }

    public function test_the_admin_panel_is_out_of_reach(): void
    {
        $this->redirectTo('admin', '/faq');

        // Not redirected, and not public either: the panel answers with its
        // own login redirect.
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_a_row_pointing_at_its_own_address_does_not_loop(): void
    {
        $this->redirectTo('blog', '/blog');

        $this->get('/blog')->assertNotFound();
    }
}
