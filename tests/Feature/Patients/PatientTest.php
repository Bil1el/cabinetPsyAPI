<?php

namespace Tests\Feature\Patients;

use App\Models\Patient;
use App\Models\Psychologist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_is_paginated_searchable_and_scoped(): void
    {
        $a = Psychologist::factory()->create();
        $b = Psychologist::factory()->create();
        Patient::factory()->count(3)->create(['psychologist_id' => $a->id]);
        Patient::factory()->create(['psychologist_id' => $a->id, 'last_name' => 'UniqueSearch']);
        Patient::factory()->count(2)->create(['psychologist_id' => $b->id]);
        $this->actingAs($a->user)->getJson('/api/patients?search=UniqueSearch&per_page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 2);
        $this->actingAs($a->user)->getJson('/api/patients?per_page=0')->assertUnprocessable();
    }

    public function test_create_and_update_patient(): void
    {
        $psychologist = Psychologist::factory()->create();
        $response = $this->actingAs($psychologist->user)->postJson('/api/patients', ['first_name' => 'Lina', 'last_name' => 'Petit', 'email' => 'lina@example.test', 'phone' => '0612345678'])->assertCreated();
        $id = $response->json('data.id');
        $this->actingAs($psychologist->user)->patchJson("/api/patients/{$id}", ['phone' => '0699999999'])->assertOk()->assertJsonPath('data.phone', '0699999999');
    }

    public function test_patient_contacts_are_normalized_and_a_conflicting_identity_cannot_be_created_by_update(): void
    {
        $psychologist = Psychologist::factory()->create();
        $first = $this->actingAs($psychologist->user)->postJson('/api/patients', [
            'first_name' => 'Lina',
            'last_name' => 'Petit',
            'email' => '  LINA@EXAMPLE.TEST ',
            'phone' => '06 12-34.56.78',
        ])->assertCreated();
        $firstId = $first->json('data.id');
        $second = Patient::factory()->create(['psychologist_id' => $psychologist->id, 'email' => 'other@example.test', 'phone' => '0699999999']);

        $this->assertDatabaseHas('patients', ['id' => $firstId, 'email' => 'lina@example.test', 'phone' => '0612345678']);
        $this->actingAs($psychologist->user)->postJson('/api/patients', [
            'first_name' => 'Lina',
            'last_name' => 'Petit',
            'email' => 'LINA@EXAMPLE.TEST',
            'phone' => '(06) 12 34 56 78',
        ])->assertSuccessful()->assertJsonPath('data.id', $firstId);
        $this->assertSame(2, Patient::query()->where('psychologist_id', $psychologist->id)->count());
        $this->actingAs($psychologist->user)->patchJson("/api/patients/{$second->id}", [
            'email' => 'LINA@EXAMPLE.TEST',
            'phone' => '06 12 34 56 78',
        ])->assertConflict()->assertJsonPath('code', 'PATIENT_IDENTITY_CONFLICT');
        $this->assertDatabaseHas('patients', ['id' => $second->id, 'email' => 'other@example.test', 'phone' => '0699999999']);
    }
}
