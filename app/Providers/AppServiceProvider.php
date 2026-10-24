<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Access\AccessService;
use App\Services\Navigation\NavigationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(AccessService $access, NavigationService $navigation): void
    {
        Gate::before(fn (User $user, string $ability) => $access->allows($user, $ability));

        View::composer('layouts.partials.navbar', function (ViewInstance $view) use ($navigation): void {
            $request = request();
            $view->with('menuItems', $request->user() ? $navigation->forUser($request->user(), $request) : collect());
        });
    }
}
