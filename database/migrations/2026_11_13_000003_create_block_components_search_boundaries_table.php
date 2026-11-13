<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_component_search_boundaries', function (Blueprint $table): void {
            $table->unsignedBigInteger('block_component_id')->primary();
            $table->foreign('block_component_id', 'bc_search_boundary_owner_fk')->references('id')->on('block_components')->cascadeOnDelete();
            $table->timestamp('available_at')->index();
        });
        DB::table('block_component_search_boundaries')->insertUsing(['block_component_id', 'available_at'],
            DB::table('block_components')->select(['id', 'published_at'])
                ->where('is_active', true)->where('is_published', true)->where('published_at', '>', now()));
    }

    public function down(): void
    {
        Schema::dropIfExists('block_component_search_boundaries');
    }
};
