<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Shared\Services\TenantContext::class);
        $this->app->singleton(\App\Shared\Ai\Services\DataPrivacySanitizer::class);
        $this->app->singleton(\App\Shared\Ai\Contracts\AiUsageMonitorInterface::class, \App\Shared\Ai\Services\AiUsageMonitor::class);
        $this->app->singleton(\App\Shared\Ai\Contracts\AiOrchestrationServiceInterface::class, \App\Shared\Ai\Services\AiOrchestrationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
