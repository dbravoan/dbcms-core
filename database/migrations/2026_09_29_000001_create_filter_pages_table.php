<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filter_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 50);
            $table->string('market_code', 20)->nullable();
            $table->string('locale', 10);
            $table->string('signature', 191);
            $table->string('path', 191)->nullable();
            $table->string('title')->nullable();
            $table->string('heading')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->timestamps();

            $table->unique(['module', 'market_code', 'locale', 'signature'], 'filter_pages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filter_pages');
    }
};
