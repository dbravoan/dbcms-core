<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notifications', function (Blueprint $table): void {
            $table->id();
            // Nullable: notifications without a user stay global.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uuid', 64)->unique();
            $table->string('module', 50)->index();
            $table->string('type', 50)->default('info');
            $table->string('title', 255);
            $table->text('message');
            $table->string('action_url', 500)->nullable();
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_global')->default(true);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
