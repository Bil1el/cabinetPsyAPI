<?php

namespace Tests\Integration\MySqlConcurrency;

use App\Models\Patient;
use App\Models\Psychologist;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Tests\MySqlConcurrencyTestCase;

class PatientIdentityConcurrencyTest extends MySqlConcurrencyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_concurrent_patient_updates_map_the_unique_identity_violation_to_a_conflict(): void
    {
        $psychologist = Psychologist::factory()->create();
        $first = Patient::factory()->create([
            'psychologist_id' => $psychologist->id,
            'email' => 'first@example.test',
            'phone' => '0600000001',
        ]);
        $second = Patient::factory()->create([
            'psychologist_id' => $psychologist->id,
            'email' => 'second@example.test',
            'phone' => '0600000002',
        ]);

        $outcomes = $this->runConcurrentUpdates($first->id, $second->id);

        $this->assertSame(['conflict', 'success'], $outcomes);
        $this->assertSame(1, Patient::query()
            ->where('psychologist_id', $psychologist->id)
            ->where('email', 'patient@example.test')
            ->where('phone', '0612345678')
            ->count());
    }

    private function runConcurrentUpdates(int $firstPatientId, int $secondPatientId): array
    {
        $directory = sys_get_temp_dir().'/cabinetpsy-patient-update-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);

        try {
            $updates = [
                [$firstPatientId, '  PATIENT@EXAMPLE.TEST ', '(06) 12-34.56.78'],
                [$secondPatientId, 'patient@example.test', '0612345678'],
            ];
            $processes = [];

            foreach ($updates as $index => [$patientId, $email, $phone]) {
                $processes[] = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/mysql_patient_identity_update_worker.php'),
                    "{$directory}/{$index}.ready",
                    "{$directory}/go",
                    "{$directory}/{$index}.json",
                    (string) $patientId,
                    $email,
                    $phone,
                ]);
            }

            foreach ($processes as $process) {
                $process->start();
            }

            $this->waitFor(fn () => count(glob("{$directory}/*.ready")) === count($processes));
            touch("{$directory}/go");
            $this->waitFor(fn () => count(glob("{$directory}/*.attempting")) === count($processes));

            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            }

            return collect(range(0, count($processes) - 1))
                ->map(fn (int $index) => json_decode(file_get_contents("{$directory}/{$index}.json"), true, flags: JSON_THROW_ON_ERROR)['outcome'])
                ->sort()
                ->values()
                ->all();
        } finally {
            foreach (glob("{$directory}/*") ?: [] as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    private function waitFor(callable $condition): void
    {
        $deadline = microtime(true) + 10;
        while (! $condition()) {
            if (microtime(true) >= $deadline) {
                $this->fail('Les processus concurrents n’ont pas atteint la barrière à temps.');
            }

            usleep(10_000);
        }
    }
}
