<?php

namespace App\Providers;

use App\Repositories\SecretRepository;
use App\Repositories\SecretRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            SecretRepositoryInterface::class,
            SecretRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
