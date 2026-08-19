<?php

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->upgradeUsers();
        $this->upgradePsychologists();
        $this->upgradeWorkingHours();
        $this->upgradeAbsences();
        $this->upgradePatients();
        $this->upgradeAppointments();
    }

    private function upgradeUsers(): void
    {
        if (! Schema::hasColumn('users', 'name')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('name')->nullable()->after('id'));
            DB::table('users')->orderBy('id')->eachById(fn ($user) => DB::table('users')->where('id', $user->id)->update(['name' => trim($user->first_name.' '.$user->last_name)]));
        }

        DB::table('users')->where('role', 'psy')->update(['role' => UserRole::PSYCHOLOGIST->value]);
    }

    private function upgradePsychologists(): void
    {
        if (Schema::hasTable('psys') && ! Schema::hasTable('psychologists')) {
            Schema::rename('psys', 'psychologists');
        }
        if (! Schema::hasTable('psychologists')) {
            return;
        }

        Schema::table('psychologists', function (Blueprint $table) {
            if (! Schema::hasColumn('psychologists', 'first_name')) {
                $table->string('first_name', 100)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('psychologists', 'last_name')) {
                $table->string('last_name', 100)->nullable()->after('first_name');
            }
            if (Schema::hasColumn('psychologists', 'specialty') && ! Schema::hasColumn('psychologists', 'speciality')) {
                $table->renameColumn('specialty', 'speciality');
            }
            if (Schema::hasColumn('psychologists', 'description') && ! Schema::hasColumn('psychologists', 'bio')) {
                $table->renameColumn('description', 'bio');
            }
            if (Schema::hasColumn('psychologists', 'active') && ! Schema::hasColumn('psychologists', 'is_active')) {
                $table->renameColumn('active', 'is_active');
            }
        });

        if (Schema::hasColumn('users', 'first_name')) {
            DB::table('psychologists')->join('users', 'users.id', '=', 'psychologists.user_id')->whereNull('psychologists.first_name')->update([
                'psychologists.first_name' => DB::raw('users.first_name'),
                'psychologists.last_name' => DB::raw('users.last_name'),
            ]);
        }
    }

    private function upgradeWorkingHours(): void
    {
        if (! Schema::hasTable('psy_schedules') || Schema::hasTable('psychologist_working_hours')) {
            return;
        }
        Schema::table('psy_schedules', fn (Blueprint $table) => $table->dropUnique(['psy_id', 'day_of_week', 'consultation_mode']));
        Schema::rename('psy_schedules', 'psychologist_working_hours');
        Schema::table('psychologist_working_hours', function (Blueprint $table) {
            $table->renameColumn('psy_id', 'psychologist_id');
            $table->renameColumn('start_time', 'starts_at');
            $table->renameColumn('end_time', 'ends_at');
            $table->renameColumn('enabled', 'is_active');
            $table->index(['psychologist_id', 'day_of_week']);
        });
    }

    private function upgradeAbsences(): void
    {
        if (! Schema::hasTable('blocked_periods') || Schema::hasTable('psychologist_absences')) {
            return;
        }
        Schema::rename('blocked_periods', 'psychologist_absences');
        Schema::table('psychologist_absences', function (Blueprint $table) {
            $table->renameColumn('psy_id', 'psychologist_id');
            $table->index(['psychologist_id', 'starts_at']);
        });
    }

    private function upgradePatients(): void
    {
        if (Schema::hasColumn('patients', 'psychologist_id')) {
            return;
        }
        Schema::table('patients', fn (Blueprint $table) => $table->foreignId('psychologist_id')->nullable()->after('id')->constrained()->restrictOnDelete());
        DB::table('patients')->orderBy('id')->eachById(function ($patient) {
            $psychologistId = DB::table('appointments')->where('patient_id', $patient->id)->orderBy('starts_at')->value(Schema::hasColumn('appointments', 'psychologist_id') ? 'psychologist_id' : 'psy_id');
            if ($psychologistId) {
                DB::table('patients')->where('id', $patient->id)->update(['psychologist_id' => $psychologistId]);
            }
        });
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'birth_date')) {
                $table->date('birth_date')->nullable();
            }
            $table->index(['psychologist_id', 'last_name', 'first_name']);
        });
    }

    private function upgradeAppointments(): void
    {
        if (Schema::hasColumn('appointments', 'psy_id')) {
            Schema::table('appointments', fn (Blueprint $table) => $table->renameColumn('psy_id', 'psychologist_id'));
        }
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'consultation_mode') && ! Schema::hasColumn('appointments', 'type')) {
                $table->renameColumn('consultation_mode', 'type');
            }
            if (Schema::hasColumn('appointments', 'message') && ! Schema::hasColumn('appointments', 'patient_message')) {
                $table->renameColumn('message', 'patient_message');
            }
            if (! Schema::hasColumn('appointments', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable();
            }
            if (! Schema::hasColumn('appointments', 'confirmed_at')) {
                $table->dateTime('confirmed_at')->nullable();
            }
            if (! Schema::hasColumn('appointments', 'completed_at')) {
                $table->dateTime('completed_at')->nullable();
            }
        });
        DB::table('appointments')->where('status', 'done')->update(['status' => AppointmentStatus::COMPLETED->value]);
    }

    public function down(): void
    {
        // Data-preserving normalization is intentionally irreversible.
    }
};
