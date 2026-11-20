<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique(); // e.g. 'es', 'en', 'ca', 'fr'
            $table->string('name');              // e.g. 'Spanish'
            $table->string('native_name');       // e.g. 'Español'
            $table->string('flag', 16)->nullable(); // e.g. '🇪🇸' (assigned in the backoffice)
            $table->string('direction', 3)->default('ltr'); // 'ltr' | 'rtl'
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('markets', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique(); // e.g. 'es', 'us', 'mx', 'global'
            $table->string('name');              // e.g. 'Spain Market'
            $table->string('flag', 16)->nullable();
            $table->string('currency', 5)->default('EUR');
            $table->string('default_locale', 10)->default('es');
            $table->foreignId('fallback_market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('market_language', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['market_id', 'language_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_language');
        Schema::dropIfExists('markets');
        Schema::dropIfExists('languages');
    }
};
