<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_guide_progress', function (Blueprint $table): void {
            $table->id();
            $table->string('installation_id', 128);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 64);
            $table->string('guide_id', 64);
            $table->string('guide_version', 40);
            $table->string('step_id', 64);
            $table->timestamp('completed_at');
            $table->unique(['installation_id', 'user_id', 'module', 'guide_id', 'guide_version', 'step_id'], 'integration_guide_progress_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_guide_progress');
    }
};
