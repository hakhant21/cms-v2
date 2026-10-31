<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The locale-neutral shell of a page: identity, publication state and media.
 * All visitor-facing copy lives in `page_translations`, one row per locale, so
 * adding a language is data rather than a schema change.
 *
 * `slug` is unique per kind (not globally) because a product and a news post
 * may legitimately share a slug and still resolve to different URLs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();

            $table->string('kind')->default('page'); // page | product | news | faq | feedback | next-project
            $table->string('slug');
            $table->string('status')->default('draft'); // draft | published | archived

            $table->foreignId('featured_image_id')->nullable()->constrained('images')->nullOnDelete();

            $table->string('animation')->nullable(); // per-page override of the site-wide animation
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes(); // admins can trash a page without losing it

            $table->unique(['kind', 'slug']);
            $table->index(['kind', 'status']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
