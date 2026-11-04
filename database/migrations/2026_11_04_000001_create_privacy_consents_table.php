<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_consents', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('revision', 64);
            $table->json('preferences');
            $table->timestamp('recorded_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_consents');
    }
};
