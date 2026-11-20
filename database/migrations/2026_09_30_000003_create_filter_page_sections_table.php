<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Curated filter pages compose their intro with the CMS block manager,
        // so they reuse the exact same section/translation shape as pages,
        // posts and products.
        Schema::create('filter_page_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('filter_page_id')->constrained('filter_pages')->cascadeOnDelete();
            $table->string('type', 40);
            $table->unsignedSmallInteger('order')->default(0);
            $table->json('styling_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('published')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('filter_page_section_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('filter_page_section_id')->constrained('filter_page_sections')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->json('content_json');
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['filter_page_section_id', 'locale', 'market_id'], 'filter_page_sec_trans_unique');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('filter_page_section_translations');
        Schema::dropIfExists('filter_page_sections');

    }
};
