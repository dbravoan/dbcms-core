<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_slug_locks', function (Blueprint $table): void {
            $table->string('namespace', 64)->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_slug_locks');
    }
};
