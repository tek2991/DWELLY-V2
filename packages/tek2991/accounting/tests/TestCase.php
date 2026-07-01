<?php

namespace Tek2991\Accounting\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Tek2991\Accounting\AccountingServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestCase extends Orchestra
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            PermissionServiceProvider::class,
            ActivitylogServiceProvider::class,
            AccountingServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
