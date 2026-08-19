<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PDO;

abstract class MySqlConcurrencyTestCase extends BaseTestCase
{
    protected const TEST_DATABASE = 'cabinetpsy_testing_mysql';

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (
            $app->environment() !== 'testing'
            || ! (bool) env('RUN_MYSQL_CONCURRENCY_TESTS')
            || ! is_string(env('MYSQL_CONCURRENCY_TEST_PASSWORD'))
            || env('MYSQL_CONCURRENCY_TEST_PASSWORD') === ''
            || $app['config']->get('database.default') !== 'mysql'
            || $app['config']->get('database.connections.mysql.database') !== self::TEST_DATABASE
            || $app['config']->get('database.connections.mysql.password') !== env('MYSQL_CONCURRENCY_TEST_PASSWORD')
        ) {
            throw new LogicException('Les tests de concurrence MySQL exigent leur base de test dédiée.');
        }

        $connection = DB::connection();

        if (
            $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
            || DB::scalar('SELECT DATABASE()') !== self::TEST_DATABASE
        ) {
            throw new LogicException('La connexion MySQL active ne correspond pas à la base de test dédiée.');
        }

        return $app;
    }
}
