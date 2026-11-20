<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_forms', function (Blueprint $table): void {
            $table->json('layout')->nullable();
            $table->json('actions')->nullable();
        });
        Schema::create('form_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->string('field_key', 50);
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('form_submission_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->string('action_key', 64);
            $table->json('definition');
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('failure_code')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['form_submission_id', 'action_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_actions');
        Schema::dropIfExists('form_attachments');
        Schema::table('custom_forms', function (Blueprint $table): void {
            $table->dropColumn(['layout', 'actions']);
        });
    }
};
