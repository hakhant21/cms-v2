<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per page per locale. `content_blocks` keeps the ordered
 * `{type, data:{layout, fields}}` array the editor already understands, but it
 * now lives beside its own title and SEO fields instead of in `_my`/`_ru`
 * column copies.
 *
 * `locale` is deliberately a plain string and not a foreign key: disabling or
 * removing a language must never delete the copy people wrote in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);

            $table->string('title')->nullable();
            $table->text('excerpt')->nullable(); // card and meta description fallback

            $table->json('content_blocks')->nullable();
            $table->json('seo')->nullable(); // title, description, og_image, canonical, robots
            $table->boolean('is_manual')->default(false); // hand-written copy is kept from auto-translation

            $table->timestamps();

            $table->unique(['page_id', 'locale']);
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_translations');
    }
};
