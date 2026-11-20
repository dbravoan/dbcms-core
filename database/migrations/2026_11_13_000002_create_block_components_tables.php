<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_components', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_published')->default(false);
            $table->json('exposed_properties')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'is_published']);
        });
        Schema::create('block_component_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('block_component_id');
            $table->foreign('block_component_id', substr($table->getTable().'_owner_fk', 0, 64))->references('id')->on('block_components')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->unsignedBigInteger('market_id')->nullable();
            $table->foreign('market_id', substr($table->getTable().'_market_fk', 0, 64))->references('id')->on('markets')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->timestamps();
            $table->unique(['block_component_id', 'locale', 'market_id'], 'bc_translation_context_unique');
        });
        Schema::create('block_component_market', function (Blueprint $table): void {
            $table->unsignedBigInteger('block_component_id');
            $table->foreign('block_component_id', substr($table->getTable().'_owner_fk', 0, 64))->references('id')->on('block_components')->cascadeOnDelete();
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->primary(['block_component_id', 'market_id']);
        });
        Schema::create('block_component_sections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('block_component_id');
            $table->foreign('block_component_id', substr($table->getTable().'_owner_fk', 0, 64))->references('id')->on('block_components')->cascadeOnDelete();
            $table->string('type')->default('text');
            $table->unsignedInteger('order')->default(0);
            $table->json('styling_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('block_component_section_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('block_component_section_id');
            $table->foreign('block_component_section_id', 'bc_section_translation_owner_fk')->references('id')->on('block_component_sections')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->unsignedBigInteger('market_id')->nullable();
            $table->foreign('market_id', substr($table->getTable().'_market_fk', 0, 64))->references('id')->on('markets')->cascadeOnDelete();
            $table->json('content_json')->nullable();
            $table->timestamps();
            $table->unique(['block_component_section_id', 'locale', 'market_id'], 'bc_section_context_unique');
        });
    }

    public function down(): void
    {
        foreach (['block_component_section_translations', 'block_component_sections', 'block_component_market', 'block_component_translations', 'block_components'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
