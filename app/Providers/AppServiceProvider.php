<?php

namespace App\Providers;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
    public function boot(): void
    {
        View::composer(['layouts.app', 'orders.menu', 'pos.index', 'pos.receipt'], function ($view): void {
            $view->with('storeSettings', StoreSetting::query()->pluck('value', 'key'));
        });
    }
}
