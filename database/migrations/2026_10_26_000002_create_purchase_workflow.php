<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'status_id']);
        });
        Schema::create('purchase_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('process_stage_id')->constrained()->restrictOnDelete();
            $table->foreignId('responsible_role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('initial_status_id')->nullable()->constrained('statuses')->restrictOnDelete();
            $table->foreignId('completion_status_id')->constrained('statuses')->restrictOnDelete();
            $table->string('field_name', 40);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'position']);
            $table->unique(['company_id', 'process_stage_id']);
            $table->unique(['company_id', 'field_name']);
        });
        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_stage_id')->constrained()->restrictOnDelete();
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 16, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            foreach (['purchase_date', 'approval_date', 'receipt_date', 'payment_date'] as $date) {
                $table->date($date)->nullable();
            }
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'purchase_stage_id', 'completed_at', 'id']);
            $table->index(['company_id', 'status_id', 'id']);
        });
        Schema::table('audit_entries', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_stage_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->restrictOnDelete();
            foreach (['supplier_id', 'purchase_stage_id', 'purchase_request_id'] as $key) {
                $table->index([$key, 'id']);
            }
            $table->index(['actor_id', 'purchase_request_id']);
        });

        $now = now();
        $module = DB::table('modules')->insertGetId(['code' => 'purchases', 'name' => 'Compras', 'created_at' => $now, 'updated_at' => $now]);
        $permissions = [];
        foreach (['purchases.requests.work' => 'Gestionar mis etapas de compra y tracking', 'purchases.suppliers.manage' => 'Gestionar proveedores'] as $code => $label) {
            $permissions[$code] = DB::table('permissions')->insertGetId(['code' => $code, 'name' => $label, 'module_id' => $module, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['suppliers' => ['Proveedores', ['Activo' => true, 'Inactivo' => false]], 'purchase_requests' => ['Solicitudes de compra', ['Borrador' => true, 'compra solicitada' => true, 'compra aprobada nivel 1' => true, 'Compra recibida y verificada' => true, 'Compra pagada' => true]]] as $code => [$name, $statuses]) {
            $type = DB::table('status_types')->insertGetId(['code' => $code, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($statuses as $label => $active) {
                DB::table('statuses')->insert(['status_type_id' => $type, 'name' => $label, 'is_active' => $active, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $status = DB::table('statuses')->where('status_type_id', DB::table('status_types')->where('code', 'menus')->value('id'))->whereNull('deleted_at')->where('is_active', true)->value('id');
        $parent = DB::table('menu_items')->insertGetId(['name' => 'Compras', 'icon' => 'briefcase', 'is_parent' => true, 'status_id' => $status, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([['Pendientes', 'purchases.index', 'purchases.requests.work'], ['Tracking de solicitudes', 'purchases.tracking', 'purchases.requests.work'], ['Proveedores', 'suppliers.index', 'purchases.suppliers.manage']] as $order => [$name, $route, $ability]) {
            DB::table('menu_items')->insert(['parent_id' => $parent, 'name' => $name, 'icon' => 'list-check', 'is_parent' => false, 'route_name' => $route, 'permission_id' => $permissions[$ability], 'module_id' => $module, 'status_id' => $status, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('audit_entries', function (Blueprint $table): void {
            $table->dropIndex(['actor_id', 'purchase_request_id']);
            foreach (['supplier_id', 'purchase_stage_id', 'purchase_request_id'] as $key) {
                $table->dropIndex([$key, 'id']);
                $table->dropConstrainedForeignId($key);
            }
        });
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('purchase_stages');
        Schema::dropIfExists('suppliers');
        // Los catálogos y permisos se conservan por sus posibles asignaciones históricas.
    }
};
