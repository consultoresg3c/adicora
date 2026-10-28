<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_stages', function (Blueprint $table): void {
            $table->boolean('review_only')->default(false);
        });
        Schema::create('process_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('process_definition_id')->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->string('attribute', 40)->nullable();
            $table->string('data_type', 20);
            $table->unsignedInteger('text_length')->nullable();
            $table->unsignedSmallInteger('decimal_places')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['process_definition_id', 'attribute']);
            $table->index(['process_definition_id', 'position', 'id']);
        });
        DB::statement('CREATE UNIQUE INDEX process_fields_live_name ON process_fields (process_definition_id, name) WHERE deleted_at IS NULL');
        Schema::create('process_stage_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_stage_id')->constrained()->restrictOnDelete();
            $table->foreignId('process_field_id')->constrained()->restrictOnDelete();
            $table->string('mode', 10)->default('hidden');
            $table->boolean('is_required')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['purchase_stage_id', 'process_field_id']);
        });
        Schema::create('purchase_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('process_field_id')->constrained()->restrictOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['purchase_request_id', 'process_field_id']);
        });
        Schema::table('audit_entries', function (Blueprint $table): void {
            $table->foreignId('process_field_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['process_field_id', 'id']);
        });
        $definitions = ['supplier_id' => ['Proveedor', 'supplier', null], 'amount' => ['Monto', 'numeric', 2], 'purchase_date' => ['Fecha de compra', 'date', null], 'receipt_date' => ['Fecha de recepción', 'date', null], 'approval_date' => ['Fecha de aprobación', 'date', null], 'payment_date' => ['Fecha de pago', 'date', null]];
        foreach (DB::table('process_definitions')->get() as $process) {
            $fields = [];
            foreach ($definitions as $attribute => [$name, $type, $decimals]) {
                $fields[$attribute] = DB::table('process_fields')->insertGetId(['company_id' => $process->company_id, 'process_definition_id' => $process->id, 'name' => $name, 'attribute' => $attribute, 'data_type' => $type, 'decimal_places' => $decimals, 'position' => count($fields) + 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach (DB::table('purchase_stages')->where('process_definition_id', $process->id)->get() as $stage) {
                $owned = $stage->field_name === 'purchase_date' ? ['supplier_id', 'amount', 'purchase_date'] : array_filter([$stage->field_name]);
                DB::table('purchase_stages')->where('id', $stage->id)->update(['review_only' => $stage->field_name === null]);
                foreach ($fields as $attribute => $fieldId) {
                    DB::table('process_stage_fields')->insert(['purchase_stage_id' => $stage->id, 'process_field_id' => $fieldId, 'mode' => in_array($attribute, $owned, true) ? 'edit' : 'read', 'is_required' => in_array($attribute, $owned, true), 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('audit_entries', function (Blueprint $table): void {
            $table->dropIndex(['process_field_id', 'id']);
            $table->dropConstrainedForeignId('process_field_id');
        });
        Schema::dropIfExists('purchase_field_values');
        Schema::dropIfExists('process_stage_fields');
        Schema::dropIfExists('process_fields');
        Schema::table('purchase_stages', fn (Blueprint $table) => $table->dropColumn('review_only'));
    }
};
