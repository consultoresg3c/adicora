<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Management\BranchController;
use App\Http\Controllers\Management\CompanyController;
use App\Http\Controllers\Management\MenuItemController;
use App\Http\Controllers\Management\RecordHistoryController;
use App\Http\Controllers\Management\RoleController;
use App\Http\Controllers\Management\StatusController;
use App\Http\Controllers\Management\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SessionController::class, 'create'])->name('login');
Route::post('/login', [SessionController::class, 'store'])->middleware('guest')->name('login.store');
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/inicio', DashboardController::class)->name('dashboard');
    Route::get('/{resource}/{record}/historial', RecordHistoryController::class)
        ->whereIn('resource', ['empresas', 'sucursales', 'usuarios', 'roles', 'estados', 'menus'])
        ->whereNumber('record')->name('records.history');
    Route::resource('empresas', CompanyController::class)->parameters(['empresas' => 'company'])->names('companies')->except('show')->middleware('can:system.companies.manage');
    Route::resource('sucursales', BranchController::class)->parameters(['sucursales' => 'branch'])->names('branches')->except('show')->middleware('can:organization.branches.manage');
    Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->names('users')->except('show')->middleware('can:access.users.manage');
    Route::resource('roles', RoleController::class)->names('roles')->except('show')->middleware('can:access.roles.manage');
    Route::resource('estados', StatusController::class)->parameters(['estados' => 'status'])->names('statuses')->except('show')->middleware('can:system.statuses.manage');
    Route::resource('menus', MenuItemController::class)->parameters(['menus' => 'menuItem'])->names('menus')->except('show')->middleware('can:system.menus.manage');
});
