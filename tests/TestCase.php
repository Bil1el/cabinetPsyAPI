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

        if (
            $app->environment() !== 'testing'
            || $app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new LogicException('Les tests doivent utiliser SQLite en mémoire dans l’environnement testing.');
        }

        return $app;
    }
}
