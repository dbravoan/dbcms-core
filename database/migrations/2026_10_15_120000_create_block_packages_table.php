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
        Schema::create('block_packages', function (Blueprint $table): void {
            $table->string('identifier', 64)->primary();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
        DB::table('permissions')->updateOrInsert(['slug' => 'manage:blocks'], ['name' => 'Administrar paquetes de bloques']);
        $id = DB::table('permissions')->where('slug', 'manage:blocks')->value('id');
        foreach (DB::table('roles')->whereIn('slug', ['admin', 'super-admin'])->pluck('id') as $role) {
            DB::table('permission_role')->updateOrInsert(['permission_id' => $id, 'role_id' => $role]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('block_packages');
        $id = DB::table('permissions')->where('slug', 'manage:blocks')->value('id');
        DB::table('permission_role')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
    }
};
