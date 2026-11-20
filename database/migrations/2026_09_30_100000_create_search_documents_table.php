<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One denormalized row per entity translation: the ranking query never
        // joins module tables, and the canonical URL is precomputed so serving
        // a result is a single SELECT.
        Schema::create('search_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 60);
            $table->string('entity_id', 64);
            $table->string('locale', 10);
            $table->string('market_code', 10)->default('');
            $table->string('url', 500);
            $table->string('title', 500)->default('');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->float('weight')->default(1);
            $table->string('image_url', 500)->nullable();
            $table->timestamps();

            $table->unique(['type', 'entity_id', 'locale', 'market_code'], 'search_documents_unique');
            $table->index(['type', 'locale'], 'search_documents_type_locale');
            $table->index('locale', 'search_documents_locale');

            // MySQL requires the MATCH() column list to match a FULLTEXT index
            // exactly: one index per ranked column so relevance can be weighted
            // (title > excerpt > body). The column collation (utf8mb4_…_ci)
            // makes matching accent-insensitive for free.
            $table->fullText('title', 'search_documents_title_fulltext');
            $table->fullText('excerpt', 'search_documents_excerpt_fulltext');
            $table->fullText('body', 'search_documents_body_fulltext');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_documents');
    }
};
