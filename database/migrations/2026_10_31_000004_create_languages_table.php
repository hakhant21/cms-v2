<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The locales the site can render, seeded with en/my/ru. This table is the
 * single source of truth: `is_default` replaces hard-coded fallbacks and
 * `lang_country` keeps the regional tag used for date and number formatting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique(); // en | my | ru
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->string('lang_country', 16)->nullable(); // en-GB, my-MM, ru-RU
            $table->string('direction', 3)->default('ltr'); // ltr | rtl
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
