<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header and footer links, in a self-referencing tree. A link either points at
 * a page (so renaming a slug updates the menu automatically) or at a raw URL.
 * Its visible text lives in `navigation_item_translations`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')->nullable()->constrained('navigation_items')->nullOnDelete();

            $table->string('placement')->default('header'); // header | footer
            $table->string('target_type')->default('page'); // page | url
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('opens_new_tab')->default(false);

            $table->timestamps();

            $table->index(['placement', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
