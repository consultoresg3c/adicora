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
            $table->boolean('is_start')->default(false);
            $table->boolean('is_end')->default(false);
            $table->index(['process_definition_id', 'is_start']);
        });
        // Conserva el comportamiento existente; las etapas nuevas tienen ambos valores false.
        foreach (DB::table('purchase_stages')->whereNull('deleted_at')->whereNotNull('process_definition_id')->distinct()->pluck('process_definition_id') as $processId) {
            $rows = DB::table('purchase_stages')->where('process_definition_id', $processId)->whereNull('deleted_at')->orderBy('position')->get();
            DB::table('purchase_stages')->where('id', $rows->first()->id)->update(['is_start' => true]);
            DB::table('purchase_stages')->where('id', $rows->last()->id)->update(['is_end' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('purchase_stages', function (Blueprint $table): void {
            $table->dropIndex(['process_definition_id', 'is_start']);
            $table->dropColumn(['is_start', 'is_end']);
        });
    }
};
