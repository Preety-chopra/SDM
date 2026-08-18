<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get('database.connections.sqlite.database');

        if (! $app->environment('testing') || $connection !== 'sqlite' || $database !== ':memory:') {
            throw new LogicException(
                'Unsafe test database configuration. Tests must use the in-memory SQLite database.',
            );
        }

        return $app;
    }
}
