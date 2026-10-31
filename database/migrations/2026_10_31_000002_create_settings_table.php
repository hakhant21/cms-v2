<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value site settings. `category` drives the tabs on the admin settings
 * page and `type` tells the Setting model how to cast `value`, so callers get
 * a bool/int/array back instead of re-parsing strings by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('category')->default('general'); // general | company | theme | mail
            $table->string('type')->default('string'); // string | text | bool | int | json
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
