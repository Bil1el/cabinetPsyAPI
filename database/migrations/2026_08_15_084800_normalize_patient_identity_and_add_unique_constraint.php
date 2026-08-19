<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $phone = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(phone), ' ', ''), '-', ''), '.', ''), '(', ''), ')', '')";

        $duplicateExists = DB::table('patients')
            ->selectRaw("psychologist_id, LOWER(TRIM(email)) as identity_email, {$phone} as identity_phone, COUNT(*) as total")
            ->groupByRaw("psychologist_id, LOWER(TRIM(email)), {$phone}")
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateExists) {
            throw new RuntimeException('La normalisation des coordonnées patient révèle des doublons. Une résolution manuelle est requise avant cette migration.');
        }

        DB::table('patients')->orderBy('id')->eachById(function (object $patient): void {
            DB::table('patients')->where('id', $patient->id)->update([
                'email' => mb_strtolower(trim($patient->email)),
                'phone' => str_replace([' ', '-', '.', '(', ')'], '', trim($patient->phone)),
            ]);
        });

        Schema::table('patients', function ($table): void {
            $table->unique(['psychologist_id', 'email', 'phone'], 'patients_psychologist_email_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function ($table): void {
            $table->dropUnique('patients_psychologist_email_phone_unique');
        });
    }
};
