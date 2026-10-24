<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Status;
use App\Models\StatusType;
use App\Models\User;
use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['companies' => 'Empresas', 'branches' => 'Sucursales', 'users' => 'Usuarios'] as $code => $name) {
            $type = StatusType::firstOrCreate(['code' => $code], ['name' => $name]);
            Status::withTrashed()->firstOrCreate(['status_type_id' => $type->id, 'name' => 'Activo'], ['is_active' => true]);
            Status::withTrashed()->firstOrCreate(['status_type_id' => $type->id, 'name' => 'Inactivo'], ['is_active' => false]);
        }

        foreach (['organization' => 'Organización', 'access' => 'Usuarios y acceso'] as $code => $name) {
            Module::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach ([
            ['system.companies.manage', 'Gestionar empresas y sus módulos', null],
            ['system.statuses.manage', 'Gestionar estados del sistema', null],
            ['organization.branches.manage', 'Gestionar sucursales', 'organization'],
            ['access.users.manage', 'Gestionar usuarios', 'access'],
            ['access.roles.manage', 'Gestionar roles y permisos', 'access'],
        ] as [$code, $name, $moduleCode]) {
            Permission::firstOrCreate(['code' => $code], [
                'name' => $name,
                'module_id' => $moduleCode ? Module::where('code', $moduleCode)->value('id') : null,
            ]);
        }

        $password = config('buko.initial_admin.password');
        if (! $password || strlen($password) < 12) {
            $this->command?->warn('Catálogos creados. Define BUKO_ADMIN_PASSWORD con al menos 12 caracteres y vuelve a ejecutar el seeder para crear el superadministrador.');

            return;
        }

        // No restablecer contraseña ni elevar cuentas existentes al repetir el seeder.
        if (! User::withTrashed()->where('email', config('buko.initial_admin.email'))->exists()) {
            $user = new User([
                'name' => config('buko.initial_admin.name'),
                'email' => config('buko.initial_admin.email'),
                'password' => $password,
                'status_id' => Status::whereHas('type', fn ($query) => $query->where('code', 'users'))->where('is_active', true)->value('id'),
            ]);
            $user->is_super_admin = true;
            $user->email_verified_at = now();
            $user->save();
        }
    }
}
