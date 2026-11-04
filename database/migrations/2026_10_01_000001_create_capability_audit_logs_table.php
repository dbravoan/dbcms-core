<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every agent call that mutates the CMS (and every denial) is recorded
        // here: who, which token, which tool, with which arguments — with secrets
        // redacted — and what happened. Guarantees traceability for
        // "the agent can do everything the backoffice can".
        Schema::create('capability_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_id', 100)->nullable();
            $table->string('client_id', 100)->nullable();
            $table->boolean('is_m2m')->default(false);
            $table->string('tool', 120);
            $table->string('ability', 20)->default('read');
            $table->string('permission', 60)->nullable();
            $table->string('status', 32)->default('ok');
            $table->json('args')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['tool', 'status'], 'capability_audit_tool_status');
            $table->index('created_at', 'capability_audit_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capability_audit_logs');
    }
};
