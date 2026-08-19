<?php

use App\DTOs\Appointment\UpdateAppointmentDTO;
use App\Exceptions\InvalidAppointmentTransitionException;
use App\Models\Appointment;
use App\Models\Psychologist;
use App\Services\AppointmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

[$readyFile, $goFile, $resultFile, $appointmentId, $operation, $lockFile, $releaseFile] = array_slice($argv, 1);

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

    $appointment = Appointment::query()->findOrFail((int) $appointmentId);
    file_put_contents($readyFile, 'ready');

    $deadline = microtime(true) + 10;
    while (! file_exists($goFile)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('La barrière de concurrence a expiré.');
        }

        usleep(10_000);
    }

    file_put_contents($readyFile.'.attempting', 'attempting');
    $service = app(AppointmentService::class);

    if ($operation === 'complete') {
        $service->complete($appointment);
    } elseif ($operation === 'cancel') {
        $service->cancel($appointment, 'Concurrence');
    } elseif ($operation === 'update') {
        $service->update($appointment, UpdateAppointmentDTO::fromArray(['patient_message' => 'Mise à jour concurrente']));
    } elseif ($operation === 'cancel_holding_psychologist') {
        DB::transaction(function () use ($appointment, $service, $lockFile, $releaseFile): void {
            Psychologist::query()->lockForUpdate()->findOrFail($appointment->psychologist_id);
            file_put_contents($lockFile, 'locked');

            $deadline = microtime(true) + 10;
            while (! file_exists($releaseFile)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('La barrière de concurrence a expiré.');
                }

                usleep(10_000);
            }

            $service->cancel($appointment, 'Concurrence');
        });
    } else {
        throw new InvalidArgumentException('Opération de concurrence inconnue.');
    }

    file_put_contents($resultFile, json_encode(['outcome' => 'success'], JSON_THROW_ON_ERROR));
} catch (InvalidAppointmentTransitionException) {
    file_put_contents($resultFile, json_encode(['outcome' => 'invalid_transition'], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    file_put_contents($resultFile, json_encode(['outcome' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR));
}
