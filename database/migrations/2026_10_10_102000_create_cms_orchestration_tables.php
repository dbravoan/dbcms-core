<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_executions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 128);
            $table->string('request_hash', 64);
            $table->string('status')->default('planning');
            $table->unsignedInteger('version')->default(1);
            $table->text('instruction');
            $table->json('actor');
            $table->json('editor_context')->nullable();
            $table->json('plan')->nullable();
            $table->text('question')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });
        Schema::create('cms_execution_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('execution_id')->constrained('cms_executions')->cascadeOnDelete();
            $table->string('key', 64);
            $table->unsignedInteger('position');
            $table->string('capability');
            $table->json('arguments');
            $table->json('dependencies');
            $table->json('resolved_arguments')->nullable();
            $table->string('status')->default('pending');
            $table->string('binding_hash', 64)->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['execution_id', 'key']);
        });
        Schema::create('cms_execution_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('step_id')->constrained('cms_execution_steps')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('plan_version');
            $table->string('binding_hash', 64);
            $table->timestamp('approved_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->unique(['step_id', 'binding_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_execution_approvals');
        Schema::dropIfExists('cms_execution_steps');
        Schema::dropIfExists('cms_executions');
    }
};
