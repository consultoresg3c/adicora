<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['companies', 'branches', 'users', 'roles', 'statuses', 'menu_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        Schema::create('audit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->foreignId('company_record_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('statuses')->restrictOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 20);
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent();

            foreach (['company_id', 'company_record_id', 'branch_id', 'user_id', 'role_id', 'status_id', 'menu_item_id'] as $column) {
                $table->index([$column, 'id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_entries');

        foreach (['companies', 'branches', 'users', 'roles', 'statuses', 'menu_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
