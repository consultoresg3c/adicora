<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use App\Services\Purchases\PurchaseSetupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PreparePurchases extends Command
{
    protected $signature = 'purchases:prepare {--company= : ID de la empresa existente}';

    protected $description = 'Prepara el flujo local de compras, proveedor y cuentas de prueba sin restablecer contraseñas';

    public function handle(PurchaseSetupService $setup): int
    {
        if (! app()->environment('local')) {
            $this->error('La preparación de cuentas de prueba solo está disponible en local.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('purchase_requests')) {
            $this->error('Aplica primero la migración pendiente de compras.');

            return self::FAILURE;
        }
        $companies = Company::query()->when($this->option('company'), fn ($query, $id) => $query->whereKey($id), fn ($query) => $query->where('name', 'Yraida Guillén'))->get();
        if ($companies->count() !== 1) {
            $this->error('Debe existir una única empresa Yraida Guillén; también puedes indicar su ID con --company.');

            return self::FAILURE;
        }
        $actor = User::where('is_super_admin', true)->whereNull('company_id')->whereHas('status', fn ($query) => $query->where('is_active', true))->firstOrFail();
        $password = $this->secret('Contraseña temporal para las cuentas nuevas (las existentes se conservan)');
        try {
            $users = $setup->prepare($companies->first(), $actor, (string) $password);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                $this->error(implode(' ', $messages));
            }

            return self::FAILURE;
        }
        $this->table(['Usuario', 'Correo'], array_map(fn ($user) => [$user['name'], $user['email']], $users));
        $this->info('Flujo, proveedor y cuentas preparados. No se restablecieron contraseñas existentes.');

        return self::SUCCESS;
    }
}
