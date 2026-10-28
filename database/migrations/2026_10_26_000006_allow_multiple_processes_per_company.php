<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_definitions', function (Blueprint $table): void {
            $table->index(['company_id', 'id']);
        });
        DB::statement('DROP INDEX process_definitions_live_company');
    }

    public function down(): void
    {
        // Conservar los datos cuando la restricción anterior ya no pueda restablecerse.
        if (DB::table('process_definitions')->select('company_id')->whereNull('deleted_at')->groupBy('company_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('No se puede revertir: hay empresas con varios procesos vigentes.');
        }
        DB::statement('CREATE UNIQUE INDEX process_definitions_live_company ON process_definitions (company_id) WHERE deleted_at IS NULL');
        Schema::table('process_definitions', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'id']);
        });
    }
};
