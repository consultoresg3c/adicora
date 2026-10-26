<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('CREATE UNIQUE INDEX process_definitions_live_company ON process_definitions (company_id) WHERE deleted_at IS NULL');
        Schema::table('purchase_stages', function (Blueprint $table): void {
            $table->foreignId('process_definition_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('field_name', 40)->nullable()->change();
            $table->dropUnique(['company_id', 'position']);
            $table->dropUnique(['company_id', 'field_name']);
            $table->dropUnique(['company_id', 'process_stage_id']);
        });
        Schema::table('process_steps', function (Blueprint $table): void {
            $table->foreignId('process_definition_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['process_definition_id', 'origin_stage_id']);
        });
        Schema::table('audit_entries', function (Blueprint $table): void {
            $table->foreignId('process_definition_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['process_definition_id', 'id']);
        });
        $status = DB::table('statuses')->where('status_type_id', DB::table('status_types')->where('code', 'processes')->value('id'))->where('is_active', true)->whereNull('deleted_at')->value('id');
        foreach (DB::table('purchase_stages')->distinct()->pluck('company_id') as $companyId) {
            $id = DB::table('process_definitions')->insertGetId(['company_id' => $companyId, 'name' => 'Compras', 'status_id' => $status, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('purchase_stages')->where('company_id', $companyId)->update(['process_definition_id' => $id]);
            $stages = DB::table('purchase_stages')->where('company_id', $companyId)->get();
            foreach ($stages as $stage) {
                DB::table('process_steps')->where('origin_stage_id', $stage->process_stage_id)->where('responsible_role_id', $stage->responsible_role_id)->update(['process_definition_id' => $id]);
            }
        }
        // Unicidad de la secuencia vigente sin impedir conservar las filas eliminadas.
        DB::statement('CREATE UNIQUE INDEX purchase_stages_live_position ON purchase_stages (process_definition_id, position) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX purchase_stages_live_stage ON purchase_stages (process_definition_id, process_stage_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX purchase_stages_live_field ON purchase_stages (process_definition_id, field_name) WHERE deleted_at IS NULL AND field_name IS NOT NULL');
    }

    public function down(): void
    {
        // No reconstruye restricciones anteriores: etapas históricas podrían repetir posiciones.
        DB::statement('DROP INDEX purchase_stages_live_position');
        DB::statement('DROP INDEX purchase_stages_live_stage');
        DB::statement('DROP INDEX purchase_stages_live_field');
        Schema::table('audit_entries', function (Blueprint $table): void {
            $table->dropIndex(['process_definition_id', 'id']);
            $table->dropConstrainedForeignId('process_definition_id');
        });
        Schema::table('process_steps', function (Blueprint $table): void {
            $table->dropIndex(['process_definition_id', 'origin_stage_id']);
            $table->dropConstrainedForeignId('process_definition_id');
        });
        Schema::table('purchase_stages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('process_definition_id');
        });
        Schema::dropIfExists('process_definitions');
    }
};
