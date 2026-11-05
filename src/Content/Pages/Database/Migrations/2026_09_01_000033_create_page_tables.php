<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('template')->default('default'); // 'default', 'landing', 'contact'
            $table->string('layout')->default('app');
            $table->boolean('is_published')->default(true)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['is_published', 'published_at'], 'pages_published_idx');
        });

        Schema::create('page_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->string('title');
            $table->string('slug')->index();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('schema_type')->default('WebPage');
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['page_id', 'locale', 'market_id']);
            $table->index(['locale', 'slug'], 'page_translations_locale_slug_idx');
        });

        Schema::create('market_page', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['market_id', 'page_id']);
        });

        Schema::create('page_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('type', 40); // 'html', 'markdown', 'hero', 'widget'
            $table->unsignedSmallInteger('order')->default(0);
            $table->json('styling_json')->nullable(); // is_fullwidth, bg_color, bg_image, scroll_magic
            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('published')->index(); // draft|published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('section_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_section_id')->constrained('page_sections')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->json('content_json'); // Localized widget parameters, markdown text, or HTML
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['page_section_id', 'locale', 'market_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_translations');
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('market_page');
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('pages');
    }
};
