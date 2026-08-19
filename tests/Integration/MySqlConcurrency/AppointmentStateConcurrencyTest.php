<?php

namespace Tests\Integration\MySqlConcurrency;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Models\Appointment;
use App\Models\Psychologist;
use App\Models\PsychologistWorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Tests\MySqlConcurrencyTestCase;

class AppointmentStateConcurrencyTest extends MySqlConcurrencyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_competing_terminal_transitions_revalidate_the_current_locked_status(): void
    {
        $appointment = $this->createAppointment(AppointmentStatus::CONFIRMED);
        $results = $this->runWorkers($appointment, ['complete', 'cancel']);

        $this->assertSame(['invalid_transition', 'success'], collect($results)->pluck('outcome')->sort()->values()->all(), json_encode($results));
        $this->assertContains($appointment->refresh()->status, [AppointmentStatus::COMPLETED, AppointmentStatus::CANCELLED]);
    }

    public function test_stale_update_is_rejected_after_a_concurrent_terminal_transition_commits(): void
    {
        $appointment = $this->createAppointment(AppointmentStatus::PENDING);
        $results = $this->runTerminalTransitionBeforeUpdate($appointment);

        $this->assertSame('success', $results['cancel']['outcome'], json_encode($results));
        $this->assertSame('invalid_transition', $results['update']['outcome'], json_encode($results));
        $appointment->refresh();
        $this->assertSame(AppointmentStatus::CANCELLED, $appointment->status);
        $this->assertNull($appointment->patient_message);
    }

    private function createAppointment(AppointmentStatus $status): Appointment
    {
        $psychologist = Psychologist::factory()->create(['consultation_duration' => 60]);
        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $psychologist->id,
            'day_of_week' => DayOfWeek::MONDAY,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);
        $slot = CarbonImmutable::now()->next('Monday')->setTime(10, 0);

        return Appointment::factory()->create([
            'psychologist_id' => $psychologist->id,
            'starts_at' => $slot,
            'ends_at' => $slot->addHour(),
            'status' => $status,
            'patient_message' => null,
        ]);
    }

    private function runWorkers(Appointment $appointment, array $operations): array
    {
        $directory = sys_get_temp_dir().'/cabinetpsy-appointment-state-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);

        try {
            $processes = collect($operations)->mapWithKeys(function (string $operation) use ($directory, $appointment): array {
                $process = $this->worker($directory, $operation, $appointment->id, "{$directory}/go-{$operation}");
                $process->start();

                return [$operation => $process];
            });
            $this->waitFor(fn () => count(glob("{$directory}/*.ready")) === $processes->count());

            foreach ($operations as $operation) {
                touch("{$directory}/go-{$operation}");
            }
            $this->waitFor(fn () => count(glob("{$directory}/*.attempting")) === $processes->count());

            return $this->collectResults($processes, $directory);
        } finally {
            $this->cleanDirectory($directory);
        }
    }

    private function runTerminalTransitionBeforeUpdate(Appointment $appointment): array
    {
        $directory = sys_get_temp_dir().'/cabinetpsy-appointment-terminal-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);

        try {
            $cancel = $this->worker($directory, 'cancel_holding_psychologist', $appointment->id, "{$directory}/go-cancel", "{$directory}/psychologist.locked", "{$directory}/release-cancel");
            $update = $this->worker($directory, 'update', $appointment->id, "{$directory}/go-update");
            $cancel->start();
            $update->start();
            $this->waitFor(fn () => count(glob("{$directory}/*.ready")) === 2);

            touch("{$directory}/go-cancel");
            $this->waitFor(fn () => file_exists("{$directory}/psychologist.locked"));
            touch("{$directory}/go-update");
            $this->waitFor(fn () => file_exists("{$directory}/update.ready.attempting"));
            touch("{$directory}/release-cancel");

            return $this->collectResults(collect(['cancel_holding_psychologist' => $cancel, 'update' => $update]), $directory);
        } finally {
            $this->cleanDirectory($directory);
        }
    }

    private function worker(string $directory, string $operation, int $appointmentId, string $goFile, string $lockFile = '', string $releaseFile = ''): Process
    {
        return new Process([
            PHP_BINARY,
            base_path('tests/Support/mysql_appointment_state_worker.php'),
            "{$directory}/{$operation}.ready",
            $goFile,
            "{$directory}/{$operation}.json",
            (string) $appointmentId,
            $operation,
            $lockFile,
            $releaseFile,
        ]);
    }

    private function collectResults($processes, string $directory): array
    {
        return $processes->mapWithKeys(function (Process $process, string $operation) use ($directory): array {
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

            $name = $operation === 'cancel_holding_psychologist' ? 'cancel' : $operation;

            return [$name => json_decode(file_get_contents("{$directory}/{$operation}.json"), true, flags: JSON_THROW_ON_ERROR)];
        })->all();
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

    private function cleanDirectory(string $directory): void
    {
        foreach (glob("{$directory}/*") ?: [] as $file) {
            unlink($file);
        }

        rmdir($directory);
    }
}
