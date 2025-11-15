<?php

namespace Stellify\Laravel;

use Illuminate\Support\ServiceProvider;
use Stellify\Laravel\Commands\ExportCommand;

class StellifyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportCommand::class,
            ]);
        }
    }
}
