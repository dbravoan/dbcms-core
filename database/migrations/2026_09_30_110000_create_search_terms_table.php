<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What people actually search for: powers "términos más buscados" and
        // "búsquedas sin resultados" in the Filtros y búsquedas inbox. Search
        // queries may contain personal data; retention is enforced by the
        // search:privacy command and the Search module settings.
        Schema::create('search_terms', function (Blueprint $table): void {
            $table->id();
            $table->string('term', 191);
            $table->string('locale', 10);
            $table->string('market_code', 10)->default('');
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('results')->default(0);
            $table->unsignedInteger('zero_results')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['term', 'locale', 'market_code'], 'search_terms_unique');
            $table->index(['locale', 'hits'], 'search_terms_locale_hits');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_terms');
    }
};
