<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_probe_entries', static function (Blueprint $table): void {
            $table->id();
            $table->string('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_probe_entries');
    }
};
