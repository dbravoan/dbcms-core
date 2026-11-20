<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Forms (identity + delivery settings only; textual data is localized)
        Schema::create('custom_forms', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('notification_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Localized form metadata (locale + optional market override)
        Schema::create('custom_form_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('submit_button_text')->default('Enviar');
            $table->text('success_message')->nullable();
            $table->timestamps();

            $table->unique(['custom_form_id', 'locale', 'market_id']);
        });

        // 3. Market assignment (same pattern as pages/posts/products/galleries)
        Schema::create('market_form', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['market_id', 'custom_form_id']);
        });

        // 4. Form fields (structural definition; labels/options are localized)
        Schema::create('form_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->string('field_key', 50);       // e.g. 'full_name', 'email', 'message'
            $table->string('type', 30);            // 'text', 'email', 'textarea', 'select', 'checkbox', 'radio'
            $table->boolean('is_required')->default(false);
            $table->string('validation_rules')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        // 5. Localized field data (label, placeholder, select/radio options value => label)
        Schema::create('form_field_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_field_id')->constrained('form_fields')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();

            $table->unique(['form_field_id', 'locale', 'market_id']);
        });

        // 6. Submissions
        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->json('payload');
            $table->string('locale', 10)->nullable();
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_reviewed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_field_translations');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('market_form');
        Schema::dropIfExists('custom_form_translations');
        Schema::dropIfExists('custom_forms');
    }
};
