<?php

namespace App\Providers;

use App\Models\BoardTemplate;
use Illuminate\Support\ServiceProvider;

class BoardTemplateServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Only run in non-console environments by default
        // You can also add a config flag to control this
        if (!$this->app->runningInConsole() && config('board_templates.auto_sync', true)) {
            // Check if we have any system templates
            $count = BoardTemplate::withoutGlobalScope('withoutSystem')
                ->withoutGlobalScope('OrganizationScope')
                ->where('is_system', true)
                ->count();

            // If no system templates exist, sync them
            if ($count === 0) {
                BoardTemplate::syncFromConfig();
            }
        }
    }
}
