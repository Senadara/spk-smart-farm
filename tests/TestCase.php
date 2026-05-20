<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Dotenv\Dotenv;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $workspaceRoot = dirname(__DIR__);
        $viewPath = $workspaceRoot . DIRECTORY_SEPARATOR . 'resources/views';
        $compiledViewPath = $workspaceRoot . DIRECTORY_SEPARATOR . 'storage/framework/views';

        // Load .env.testing if present so tests can target dedicated test DB
        if (file_exists(base_path('.env.testing'))) {
            try {
                Dotenv::createImmutable(base_path(), '.env.testing')->safeLoad();
            } catch (\Throwable $e) {
                // ignore dotenv load errors
            }
        }

        // Respect env values when provided, otherwise default to non-sqlite test drivers
        $this->app['config']->set('session.driver', env('SESSION_DRIVER', 'array'));
        $this->app['config']->set('cache.default', env('CACHE_STORE', 'array'));
        $this->app['config']->set('queue.default', env('QUEUE_CONNECTION', 'sync'));
        $this->app['config']->set('database.default', env('DB_CONNECTION', 'mysql'));
        $this->app['config']->set('database.connections.mysql.database', env('DB_DATABASE', 'smartfarm_test'));
        $this->app['config']->set('mail.default', env('MAIL_MAILER', 'array'));
        $this->app['config']->set('view.paths', [$viewPath]);
        $this->app['config']->set('view.compiled', $compiledViewPath);
        $this->app['view']->getFinder()->setPaths([$viewPath]);
        // Ensure an application key exists for encryption/session during tests
        $this->app['config']->set('app.key', 'base64:'.base64_encode(str_repeat("0", 32)));
    }

    protected function tearDown(): void
    {
        // Ensure Mockery expectations are cleared between tests to avoid cross-test contamination
        if (class_exists('\Mockery')) {
            \Mockery::close();
        }

        parent::tearDown();
    }
}
