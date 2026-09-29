<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     * Enforces test database isolation to protect development and production databases.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if (config('database.default') !== 'sqlite') {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
            ]);
        }

        return $app;
    }
}
