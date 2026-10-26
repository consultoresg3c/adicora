<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_stages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 180)->unique();
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('process_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('origin_stage_id')->constrained('process_stages')->restrictOnDelete();
            $table->foreignId('destination_stage_id')->constrained('process_stages')->restrictOnDelete();
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            $table->foreignId('responsible_role_id')->constrained('roles')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['origin_stage_id', 'destination_stage_id']);
            $table->index(['status_id', 'id']);
            $table->index('responsible_role_id');
        });
        Schema::table('audit_entries', function (Blueprint $table): void {
            $table->foreignId('process_stage_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('process_step_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['process_stage_id', 'id']);
            $table->index(['process_step_id', 'id']);
        });

        $now = now();
        foreach (['processes' => 'Procesos', 'process_stages' => 'Etapas de procesos'] as $code => $name) {
            $typeId = DB::table('status_types')->insertGetId(['code' => $code, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            foreach (['Activo' => true, 'Inactivo' => false] as $label => $active) {
                DB::table('statuses')->insert(['status_type_id' => $typeId, 'name' => $label, 'is_active' => $active, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        // Permiso global sin asignaciones: inicialmente solo el superadministrador lo posee.
        $permissionId = DB::table('permissions')->insertGetId(['code' => 'system.processes.manage', 'name' => 'Gestionar procesos y etapas', 'module_id' => null, 'created_at' => $now, 'updated_at' => $now]);
        $menuStatus = DB::table('statuses')->where('status_type_id', DB::table('status_types')->where('code', 'menus')->value('id'))->whereNull('deleted_at')->where('is_active', true)->value('id');
        $parentId = DB::table('menu_items')->where('is_parent', true)->where('name', 'Sistema')->whereNull('deleted_at')->value('id');
        if ($parentId === null) {
            $parentId = DB::table('menu_items')->insertGetId(['name' => 'Sistema', 'icon' => 'settings', 'is_parent' => true, 'status_id' => $menuStatus, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('menu_items')->insert(['parent_id' => $parentId, 'name' => 'Procesos', 'icon' => 'list-check', 'is_parent' => false, 'route_name' => 'processes.index', 'permission_id' => $permissionId, 'status_id' => $menuStatus, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::table('audit_entries', function (Blueprint $table): void {
            $table->dropIndex(['process_stage_id', 'id']);
            $table->dropIndex(['process_step_id', 'id']);
            $table->dropConstrainedForeignId('process_stage_id');
            $table->dropConstrainedForeignId('process_step_id');
        });
        Schema::dropIfExists('process_steps');
        Schema::dropIfExists('process_stages');
        // Conserva permiso, navegación y estados, que pueden tener asignaciones históricas.
    }
};
