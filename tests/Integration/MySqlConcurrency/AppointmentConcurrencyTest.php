<?php

namespace Tests\Integration\MySqlConcurrency;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Enums\WorkingHoursMode;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Psychologist;
use App\Models\PsychologistAbsence;
use App\Models\PsychologistWorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\MySqlConcurrencyTestCase;

class AppointmentConcurrencyTest extends MySqlConcurrencyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_same_psychologist_concurrent_bookings_leave_exactly_one_blocking_appointment(): void
    {
        $psychologist = $this->createAvailablePsychologist();
        $slot = CarbonImmutable::now()->next('Monday')->setTime(10, 0);

        $connection = DB::connection();
        $connection->beginTransaction();
        $connection->table('psychologists')->where('id', $psychologist->id)->lockForUpdate()->first();
        $lockHeld = true;

        try {
            $outcomes = $this->runConcurrentWorkers($psychologist->id, $slot, function () use ($connection, &$lockHeld): void {
                $connection->commit();
                $lockHeld = false;
            });
        } finally {
            if ($lockHeld) {
                $connection->rollBack();
            }
        }

        $this->assertSame(['conflict', 'success'], $outcomes);
        $this->assertSame(1, $this->blockingOverlapCount($psychologist->id, $slot));
    }

    public function test_same_time_bookings_for_different_psychologists_both_succeed(): void
    {
        $a = $this->createAvailablePsychologist();
        $b = $this->createAvailablePsychologist();
        $slot = CarbonImmutable::now()->next('Monday')->setTime(10, 0);

        $outcomes = $this->runConcurrentWorkers([$a->id, $b->id], $slot);

        $this->assertSame(['success', 'success'], $outcomes);
        $this->assertSame(1, $this->blockingOverlapCount($a->id, $slot));
        $this->assertSame(1, $this->blockingOverlapCount($b->id, $slot));
    }

    public function test_concurrent_booking_and_schedule_replacement_never_leave_an_appointment_outside_hours(): void
    {
        $psychologist = $this->createAvailablePsychologist();
        $slot = CarbonImmutable::now()->next('Monday')->setTime(10, 0);
        $results = $this->runBookingAndScheduleWorkers($psychologist->id, $slot);
        $booking = $results['booking'];
        $schedule = $results['schedule'];

        $this->assertTrue(
            ($booking['outcome'] === 'success' && $schedule['outcome'] === 'conflict')
            || (($booking['class'] ?? null) === 'App\\Exceptions\\AppointmentNotAvailableException' && $schedule['outcome'] === 'success'),
            json_encode($results),
        );

        if ($booking['outcome'] === 'success') {
            $this->assertSame(1, $this->blockingOverlapCount($psychologist->id, $slot));
            $this->assertSame('09:00:00', PsychologistWorkingHour::query()->where('psychologist_id', $psychologist->id)->value('starts_at'));
        } else {
            $this->assertSame(0, $this->blockingOverlapCount($psychologist->id, $slot));
            $this->assertSame('14:00:00', PsychologistWorkingHour::query()->where('psychologist_id', $psychologist->id)->value('starts_at'));
        }
    }

    public function test_concurrent_booking_and_mode_change_never_leave_an_incompatible_appointment(): void
    {
        $psychologist = $this->createAvailablePsychologist();
        $slot = CarbonImmutable::now()->next('Monday')->setTime(10, 0);
        $results = $this->runBookingAndScheduleWorkers($psychologist->id, $slot, '09:00', '12:00', 'online');

        $this->assertTrue(
            ($results['booking']['outcome'] === 'success' && $results['schedule']['outcome'] === 'conflict')
            || (($results['booking']['class'] ?? null) === 'App\\Exceptions\\AppointmentNotAvailableException' && $results['schedule']['outcome'] === 'success'),
            json_encode($results),
        );

        if ($results['booking']['outcome'] === 'success') {
            $this->assertSame(WorkingHoursMode::BOTH, PsychologistWorkingHour::query()->where('psychologist_id', $psychologist->id)->value('mode'));
        } else {
            $this->assertSame(WorkingHoursMode::ONLINE, PsychologistWorkingHour::query()->where('psychologist_id', $psychologist->id)->value('mode'));
        }
    }

    public function test_concurrent_booking_and_absence_creation_never_persist_an_overlap(): void
    {
        $psychologist = $this->createAvailablePsychologist();
        $slot = CarbonImmutable::now()->next('Monday')->setTime(10, 0);
        $results = $this->runBookingAndAbsenceWorkers($psychologist->id, $psychologist->id, $slot);

        $this->assertSame(1, collect($results)->where('outcome', 'success')->count(), json_encode($results));
        $this->assertSame(0, $this->blockingOverlapCount($psychologist->id, $slot) * $this->absenceOverlapCount($psychologist->id, $slot));
    }

    public function test_booking_and_absence_for_different_psychologists_both_succeed(): void
    {
        $a = $this->createAvailablePsychologist();
        $b = $this->createAvailablePsychologist();
        $slot = CarbonImmutable::now()->next('Monday')->setTime(10, 0);

        $results = $this->runBookingAndAbsenceWorkers($a->id, $b->id, $slot);

        $this->assertSame(['success', 'success'], collect($results)->pluck('outcome')->sort()->values()->all(), json_encode($results));
        $this->assertSame(1, $this->blockingOverlapCount($a->id, $slot));
        $this->assertSame(1, $this->absenceOverlapCount($b->id, $slot));
    }

    public function test_concurrent_booking_resolves_one_normalized_patient_identity(): void
    {
        $psychologist = $this->createAvailablePsychologist();
        $firstSlot = CarbonImmutable::now()->next('Monday')->setTime(10, 0);
        $outcomes = $this->runPatientIdentityWorkers($psychologist->id, $firstSlot);

        $this->assertSame(['success', 'success'], $outcomes);
        $this->assertSame(2, Appointment::query()->where('psychologist_id', $psychologist->id)->count());
        $this->assertSame(1, Patient::query()->where('psychologist_id', $psychologist->id)->count());
        $this->assertSame('patient@example.test', Patient::query()->where('psychologist_id', $psychologist->id)->value('email'));
        $this->assertSame('0612345678', Patient::query()->where('psychologist_id', $psychologist->id)->value('phone'));
    }

    private function createAvailablePsychologist(): Psychologist
    {
        $psychologist = Psychologist::factory()->create(['consultation_duration' => 60]);
        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $psychologist->id,
            'day_of_week' => DayOfWeek::MONDAY,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);

        return $psychologist;
    }

    private function runConcurrentWorkers(int|array $psychologistIds, CarbonImmutable $slot, ?callable $releaseLock = null): array
    {
        $psychologistIds = is_array($psychologistIds) ? $psychologistIds : [$psychologistIds, $psychologistIds];
        $directory = sys_get_temp_dir().'/cabinetpsy-concurrency-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);

        try {
            $processes = [];
            foreach ($psychologistIds as $index => $psychologistId) {
                $ready = "{$directory}/{$index}.ready";
                $result = "{$directory}/{$index}.json";
                $processes[] = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/mysql_concurrent_booking_worker.php'),
                    $ready,
                    "{$directory}/go",
                    $result,
                    (string) $psychologistId,
                    $slot->toISOString(),
                    (string) ($index + 1),
                ]);
            }

            foreach ($processes as $process) {
                $process->start();
            }

            $this->waitFor(fn () => count(glob("{$directory}/*.ready")) === count($processes));
            touch("{$directory}/go");
            $this->waitFor(fn () => count(glob("{$directory}/*.attempting")) === count($processes));

            if ($releaseLock) {
                usleep(200_000);
                $releaseLock();
            }

            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            }

            $outcomes = collect(range(0, count($processes) - 1))
                ->map(fn (int $index) => json_decode(file_get_contents("{$directory}/{$index}.json"), true, flags: JSON_THROW_ON_ERROR)['outcome'])
                ->sort()
                ->values()
                ->all();

            return $outcomes;
        } finally {
            foreach (glob("{$directory}/*") ?: [] as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    private function runBookingAndScheduleWorkers(int $psychologistId, CarbonImmutable $slot, string $startsAt = '14:00', string $endsAt = '18:00', string $mode = 'both'): array
    {
        $directory = sys_get_temp_dir().'/cabinetpsy-schedule-concurrency-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);

        try {
            $workers = [
                'booking' => new Process([
                    PHP_BINARY,
                    base_path('tests/Support/mysql_concurrent_booking_worker.php'),
                    "{$directory}/booking.ready",
                    "{$directory}/go",
                    "{$directory}/booking.json",
                    (string) $psychologistId,
                    $slot->toISOString(),
                    '1',
                ]),
                'schedule' => new Process([
                    PHP_BINARY,
                    base_path('tests/Support/mysql_working_hours_replace_worker.php'),
                    "{$directory}/schedule.ready",
                    "{$directory}/go",
                    "{$directory}/schedule.json",
                    (string) $psychologistId,
                    $startsAt,
                    $endsAt,
                    $mode,
                ]),
            ];

            foreach ($workers as $worker) {
                $worker->start();
            }

            $this->waitFor(fn () => count(glob("{$directory}/*.ready")) === count($workers));
            touch("{$directory}/go");
            $this->waitFor(fn () => count(glob("{$directory}/*.attempting")) === count($workers));

            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
            }

            return collect(array_keys($workers))
                ->mapWithKeys(fn (string $name) => [$name => json_decode(file_get_contents("{$directory}/{$name}.json"), true, flags: JSON_THROW_ON_ERROR)])
                ->all();
        } finally {
            foreach (glob("{$directory}/*") ?: [] as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    private function runBookingAndAbsenceWorkers(int $bookingPsychologistId, int $absencePsychologistId, CarbonImmutable $slot): array
    {
        $directory = sys_get_temp_dir().'/cabinetpsy-absence-concurrency-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        try {
            $workers = [
                'booking' => [base_path('tests/Support/mysql_concurrent_booking_worker.php'), [$bookingPsychologistId, $slot->toISOString(), '9']],
                'absence' => [base_path('tests/Support/mysql_absence_create_worker.php'), [$absencePsychologistId, $slot->toISOString()]],
            ];
            $processes = [];
            foreach ($workers as $name => [$script, $args]) {
                $processes[$name] = new Process([PHP_BINARY, $script, "{$directory}/{$name}.ready", "{$directory}/go", "{$directory}/{$name}.json", ...array_map('strval', $args)]);
                $processes[$name]->start();
            }
            $this->waitFor(fn () => count(glob("{$directory}/*.ready")) === 2);
            touch("{$directory}/go");
            $this->waitFor(fn () => count(glob("{$directory}/*.attempting")) === 2);
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            }

            return collect(array_keys($processes))
                ->mapWithKeys(fn (string $name) => [$name => json_decode(file_get_contents("{$directory}/{$name}.json"), true, flags: JSON_THROW_ON_ERROR)])
                ->all();
        } finally {
            foreach (glob("{$directory}/*") ?: [] as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    private function runPatientIdentityWorkers(int $psychologistId, CarbonImmutable $slot): array
    {
        $directory = sys_get_temp_dir().'/cabinetpsy-patient-identity-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);

        try {
            $workers = [
                [
                    $slot->toISOString(),
                    '  PATIENT@EXAMPLE.TEST ',
                    '(06) 12-34.56.78',
                ],
                [
                    $slot->addHour()->toISOString(),
                    'patient@example.test',
                    '0612345678',
                ],
            ];
            $processes = [];

            foreach ($workers as $index => [$startsAt, $email, $phone]) {
                $processes[] = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/mysql_patient_identity_booking_worker.php'),
                    "{$directory}/{$index}.ready",
                    "{$directory}/go",
                    "{$directory}/{$index}.json",
                    (string) $psychologistId,
                    $startsAt,
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

    private function blockingOverlapCount(int $psychologistId, CarbonImmutable $slot): int
    {
        return Appointment::query()
            ->where('psychologist_id', $psychologistId)
            ->whereIn('status', [AppointmentStatus::PENDING, AppointmentStatus::CONFIRMED])
            ->where('starts_at', '<', $slot->addHour())
            ->where('ends_at', '>', $slot)
            ->count();
    }

    private function absenceOverlapCount(int $psychologistId, CarbonImmutable $slot): int
    {
        return PsychologistAbsence::query()
            ->where('psychologist_id', $psychologistId)
            ->where('starts_at', '<', $slot->addHour())
            ->where('ends_at', '>', $slot)
            ->count();
    }
}
