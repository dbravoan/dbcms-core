<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier', 50)->unique(); // e.g. 'blog', 'gallery', 'forms'
            $table->string('name');                      // e.g. 'Blog Engine'
            $table->string('version', 20)->default('1.0.0');
            $table->text('description')->nullable();
            $table->string('provider');                  // e.g. 'DbCms\Modules\Blog\BlogModuleServiceProvider'
            $table->boolean('is_pageable')->default(false);
            $table->boolean('is_installed')->default(false);
            $table->boolean('is_active')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_modules');
    }
};
