<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Mmt\JwfLaravel\Authorization\AuthorizationContext;
use Mmt\JwfLaravel\Authorization\JwfOperation;
use Mmt\JwfLaravel\Contracts\JwfAuthorizer;
use Mmt\JwfLaravel\JwfLaravelServiceProvider;
use Mmt\JwfLaravel\JwfManager;
use RuntimeException;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->application();
        $app->bind(JwfAuthorizer::class, AllowingAuthorizer::class);
        $app->make(Kernel::class)->call('migrate', ['--force' => true]);
    }

    /** @param Application $app */
    protected function getEnvironmentSetUp($app): void
    {
        $config = $app->make(Repository::class);
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('j', 32)));
        $config->set('app.cipher', 'AES-256-CBC');
        $config->set('jwf.disk', 'jwf-tests');
    }

    /** @param Application $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [JwfLaravelServiceProvider::class];
    }

    protected function jwf(): JwfManager
    {
        return $this->application()->make(JwfManager::class);
    }

    private function application(): Application
    {
        if (!$this->app instanceof Application) {
            throw new RuntimeException('The Testbench application is not available.');
        }

        return $this->app;
    }
}

final class AllowingAuthorizer implements JwfAuthorizer
{
    public function authorize(JwfOperation $operation, AuthorizationContext $context): void
    {
    }
}
