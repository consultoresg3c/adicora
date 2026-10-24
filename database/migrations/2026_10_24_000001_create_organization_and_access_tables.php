<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['status_type_id', 'name']);
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tax_id')->unique();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('address')->nullable();
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('status_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_super_admin')->default(false);
            $table->index(['company_id', 'name', 'id']);
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('company_module', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->primary(['company_id', 'module_id']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });
        // La unicidad normal permite múltiples NULL tanto en PostgreSQL como en SQLite.
        DB::statement('CREATE UNIQUE INDEX roles_global_name_unique ON roles (name) WHERE company_id IS NULL');

        foreach (['permission_role' => ['permission_id', 'role_id'], 'role_user' => ['role_id', 'user_id'], 'permission_user' => ['permission_id', 'user_id']] as $name => $keys) {
            Schema::create($name, function (Blueprint $table) use ($keys) {
                foreach ($keys as $key) {
                    $table->foreignId($key)->constrained()->cascadeOnDelete();
                }
                $table->primary($keys);
                $table->index(array_reverse($keys));
            });
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', fn (Blueprint $table) => $table->dropForeign(['user_id']));
        foreach (['permission_user', 'role_user', 'permission_role', 'roles', 'permissions', 'company_module', 'modules'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('status_id');
            $table->dropColumn('is_super_admin');
        });
        foreach (['branches', 'companies', 'statuses', 'status_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
