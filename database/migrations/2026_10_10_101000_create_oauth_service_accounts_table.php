<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_service_accounts', function (Blueprint $table): void {
            $table->uuid('client_id')->primary();
            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('allowed_scopes');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_service_accounts');

    }
};
