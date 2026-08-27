<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel;

use Illuminate\Support\ServiceProvider;
use Mmt\Jwf\Submissions\Contracts\Clock;
use Mmt\Jwf\Submissions\Contracts\SubmissionRepository;
use Mmt\Jwf\Submissions\Contracts\ValidationProfileVersionResolver;
use Mmt\Jwf\ValidationProfiles\Contracts\ValidationEngine;
use Mmt\JwfLaravel\Contracts\FileStorage;
use Mmt\JwfLaravel\Files\LaravelFileStorage;
use Mmt\JwfLaravel\Submissions\Repositories\EloquentSubmissionRepository;
use Mmt\JwfLaravel\Submissions\SystemClock;
use Mmt\JwfLaravel\ValidationProfiles\LaravelValidationEngine;
use Mmt\JwfLaravel\ValidationProfiles\Repositories\ValidationProfileRepository;

final class JwfLaravelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/jwf.php', 'jwf');

        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(FileStorage::class, LaravelFileStorage::class);
        $this->app->bind(SubmissionRepository::class, EloquentSubmissionRepository::class);
        $this->app->bind(ValidationEngine::class, LaravelValidationEngine::class);
        $this->app->bind(ValidationProfileVersionResolver::class, ValidationProfileRepository::class);
        $this->app->singleton(JwfManager::class);
        $this->app->alias(JwfManager::class, 'jwf');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/jwf.php' => config_path('jwf.php'),
            ], 'jwf-config');
            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'jwf-migrations');
        }
    }
}
