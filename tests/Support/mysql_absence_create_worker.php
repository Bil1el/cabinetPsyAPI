<?php

use App\DTOs\Absence\StoreAbsenceDTO;
use App\Exceptions\AbsenceConflictException;
use App\Services\AbsenceService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

[$ready, $go, $result, $psychologistId, $startsAt] = array_slice($argv, 1);
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (
        $app->environment() !== 'testing'
        || ! env('RUN_MYSQL_CONCURRENCY_TESTS')
        || ! is_string(env('MYSQL_CONCURRENCY_TEST_PASSWORD'))
        || env('MYSQL_CONCURRENCY_TEST_PASSWORD') === ''
        || config('database.default') !== 'mysql'
        || config('database.connections.mysql.database') !== 'cabinetpsy_testing_mysql'
        || config('database.connections.mysql.password') !== env('MYSQL_CONCURRENCY_TEST_PASSWORD')
        || DB::scalar('SELECT DATABASE()') !== 'cabinetpsy_testing_mysql'
    ) {
        throw new LogicException('Configuration MySQL de concurrence non sûre.');
    }
    file_put_contents($ready, 'ready');
    $deadline = microtime(true) + 10;
    while (! file_exists($go)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('La barrière de concurrence a expiré.');
        }

        usleep(10_000);
    }
    file_put_contents($ready.'.attempting', 'attempting');
    app(AbsenceService::class)->create((int) $psychologistId, StoreAbsenceDTO::fromArray(['starts_at' => $startsAt, 'ends_at' => CarbonImmutable::parse($startsAt)->addHour()->toISOString()]));
    file_put_contents($result, json_encode(['outcome' => 'success'], JSON_THROW_ON_ERROR));
} catch (AbsenceConflictException) {
    file_put_contents($result, json_encode(['outcome' => 'conflict'], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    file_put_contents($result, json_encode(['outcome' => 'error', 'class' => $exception::class], JSON_THROW_ON_ERROR));
}
