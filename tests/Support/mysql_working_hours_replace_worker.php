<?php

use App\DTOs\WorkingHours\UpdateWorkingHoursDTO;
use App\Exceptions\WorkingHoursConflictException;
use App\Services\WorkingHoursService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

[$readyFile, $goFile, $resultFile, $psychologistId] = array_slice($argv, 1);
$startsAt = $argv[5] ?? '14:00';
$endsAt = $argv[6] ?? '18:00';
$mode = $argv[7] ?? 'both';

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

    file_put_contents($readyFile, 'ready');

    $deadline = microtime(true) + 10;
    while (! file_exists($goFile)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('La barrière de concurrence a expiré.');
        }

        usleep(10_000);
    }

    file_put_contents($readyFile.'.attempting', 'attempting');

    app(WorkingHoursService::class)->replace(
        (int) $psychologistId,
        UpdateWorkingHoursDTO::fromArray([
            'ranges' => [
                ['day_of_week' => 'monday', 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'mode' => $mode],
            ],
        ]),
    );

    file_put_contents($resultFile, json_encode(['outcome' => 'success'], JSON_THROW_ON_ERROR));
} catch (WorkingHoursConflictException) {
    file_put_contents($resultFile, json_encode(['outcome' => 'conflict'], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    file_put_contents($resultFile, json_encode(['outcome' => 'error', 'class' => $exception::class], JSON_THROW_ON_ERROR));
}
