<?php

use App\DTOs\Patient\UpdatePatientDTO;
use App\Exceptions\PatientIdentityConflictException;
use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

[$readyFile, $goFile, $resultFile, $patientId, $email, $phone] = array_slice($argv, 1);

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

    app(PatientService::class)->update(
        Patient::query()->findOrFail((int) $patientId),
        UpdatePatientDTO::fromArray(['email' => $email, 'phone' => $phone]),
    );

    file_put_contents($resultFile, json_encode(['outcome' => 'success'], JSON_THROW_ON_ERROR));
} catch (PatientIdentityConflictException) {
    file_put_contents($resultFile, json_encode(['outcome' => 'conflict'], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    file_put_contents($resultFile, json_encode(['outcome' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR));
}
