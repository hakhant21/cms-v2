<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The visible label of a navigation item, one row per locale. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_item_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('navigation_item_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('label');

            $table->timestamps();

            $table->unique(['navigation_item_id', 'locale']);
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_item_translations');
    }
};
