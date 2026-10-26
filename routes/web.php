<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Management\BranchController;
use App\Http\Controllers\Management\CompanyController;
use App\Http\Controllers\Management\MenuItemController;
use App\Http\Controllers\Management\ProcessDefinitionController;
use App\Http\Controllers\Management\ProcessStageController;
use App\Http\Controllers\Management\RecordHistoryController;
use App\Http\Controllers\Management\RoleController;
use App\Http\Controllers\Management\StatusController;
use App\Http\Controllers\Management\SupplierController;
use App\Http\Controllers\Management\UserController;
use App\Http\Controllers\Purchases\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SessionController::class, 'create'])->name('login');
Route::post('/login', [SessionController::class, 'store'])->middleware('guest')->name('login.store');
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/inicio', DashboardController::class)->name('dashboard');
    Route::get('/{resource}/{record}/historial', RecordHistoryController::class)
        ->whereIn('resource', ['empresas', 'sucursales', 'usuarios', 'roles', 'estados', 'menus', 'procesos', 'etapas-procesos', 'proveedores', 'solicitudes-compra', 'flujos-procesos', 'etapas-compras'])
        ->whereNumber('record')->name('records.history');
    Route::resource('proveedores', SupplierController::class)->parameters(['proveedores' => 'supplier'])->names('suppliers')->except('show')->middleware('can:purchases.suppliers.manage');
    Route::middleware('can:purchases.requests.work')->group(function () {
        Route::get('/compras', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/compras/tracking', [PurchaseController::class, 'index'])->name('purchases.tracking');
        Route::get('/compras/crear', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/compras', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('/compras/{purchase}', [PurchaseController::class, 'show'])->whereNumber('purchase')->name('purchases.show');
        Route::put('/compras/{purchase}', [PurchaseController::class, 'update'])->whereNumber('purchase')->name('purchases.update');
    });
    Route::middleware('can:system.processes.manage')->group(function () {
        Route::get('/procesos/{processDefinition}/etapas/create', [ProcessDefinitionController::class, 'createStage'])->name('processes.stages.create');
        Route::post('/procesos/{processDefinition}/etapas', [ProcessDefinitionController::class, 'storeStage'])->name('processes.stages.store');
        Route::get('/procesos/{processDefinition}/etapas/{purchaseStage}/edit', [ProcessDefinitionController::class, 'editStage'])->name('processes.stages.edit');
        Route::put('/procesos/{processDefinition}/etapas/{purchaseStage}', [ProcessDefinitionController::class, 'updateStage'])->name('processes.stages.update');
        Route::delete('/procesos/{processDefinition}/etapas/{purchaseStage}', [ProcessDefinitionController::class, 'destroyStage'])->name('processes.stages.destroy');
        Route::resource('procesos', ProcessDefinitionController::class)->parameters(['procesos' => 'processDefinition'])->names('processes')->except('show');
    });
    Route::resource('etapas-procesos', ProcessStageController::class)->parameters(['etapas-procesos' => 'processStage'])->names('process-stages')->except('show')->middleware('can:system.processes.manage');
    Route::resource('empresas', CompanyController::class)->parameters(['empresas' => 'company'])->names('companies')->except('show')->middleware('can:system.companies.manage');
    Route::resource('sucursales', BranchController::class)->parameters(['sucursales' => 'branch'])->names('branches')->except('show')->middleware('can:organization.branches.manage');
    Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->names('users')->except('show')->middleware('can:access.users.manage');
    Route::resource('roles', RoleController::class)->names('roles')->except('show')->middleware('can:access.roles.manage');
    Route::resource('estados', StatusController::class)->parameters(['estados' => 'status'])->names('statuses')->except('show')->middleware('can:system.statuses.manage');
    Route::resource('menus', MenuItemController::class)->parameters(['menus' => 'menuItem'])->names('menus')->except('show')->middleware('can:system.menus.manage');
});
