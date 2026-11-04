<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_email_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 64);
            $table->boolean('email_enabled')->default(true);
            $table->unique(['user_id', 'module']);
            $table->timestamps();
        });
        Schema::create('notification_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained('system_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('failure_code')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unique(['notification_id', 'user_id']);
            $table->index('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_email_deliveries');
        Schema::dropIfExists('notification_email_preferences');
    }
};
