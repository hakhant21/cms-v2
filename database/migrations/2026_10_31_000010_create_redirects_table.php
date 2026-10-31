<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses that used to work and no longer do.
 *
 * A path is stored without its language prefix (`blog`, not `/my/blog`) so one
 * row serves every language: the middleware puts the visitor's own prefix back
 * on the way out. `hits` answers the only question an editor actually asks
 * about an old address — is anyone still arriving here?
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();

            $table->string('from_path')->unique();
            $table->string('to_path');

            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
