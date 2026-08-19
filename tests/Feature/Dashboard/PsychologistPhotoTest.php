<?php

namespace Tests\Feature\Dashboard;

use App\Models\Psychologist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PsychologistPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_psychologist_can_upload_a_jpeg_and_resources_expose_its_public_url(): void
    {
        Storage::fake('public');
        $psychologist = Psychologist::factory()->create();

        $response = $this->actingAs($psychologist->user, 'sanctum')
            ->post('/api/psychologist/profile/photo', ['photo' => $this->image('portrait.jpg')], ['Accept' => 'application/json'])
            ->assertOk();

        $path = $psychologist->refresh()->photo;
        $url = Storage::disk('public')->url($path);

        $this->assertMatchesRegularExpression('#^psychologists/photos/[0-9a-f-]+\\.jpe?g$#i', $path);
        Storage::disk('public')->assertExists($path);
        $response->assertJsonPath('data.photo', $url);
        $this->actingAs($psychologist->user, 'sanctum')
            ->getJson('/api/psychologist/profile')
            ->assertOk()
            ->assertJsonPath('data.photo', $url);
        $this->getJson('/api/public/psychologists')
            ->assertOk()
            ->assertJsonPath('data.0.photo', $url);
    }

    public function test_png_is_accepted(): void
    {
        Storage::fake('public');
        $psychologist = Psychologist::factory()->create();

        $this->actingAs($psychologist->user, 'sanctum')
            ->post('/api/psychologist/profile/photo', ['photo' => $this->image('portrait.png')], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertStringEndsWith('.png', $psychologist->refresh()->photo);
    }

    public function test_webp_is_accepted(): void
    {
        Storage::fake('public');
        $psychologist = Psychologist::factory()->create();

        $this->actingAs($psychologist->user, 'sanctum')
            ->post('/api/psychologist/profile/photo', ['photo' => $this->image('portrait.webp')], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertStringEndsWith('.webp', $psychologist->refresh()->photo);
    }

    public function test_non_image_and_oversized_files_are_rejected(): void
    {
        $psychologist = Psychologist::factory()->create();

        $this->actingAs($psychologist->user, 'sanctum')
            ->post('/api/psychologist/profile/photo', ['photo' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
        $this->actingAs($psychologist->user, 'sanctum')
            ->post('/api/psychologist/profile/photo', ['photo' => UploadedFile::fake()->create('large.jpg', 5121, 'image/jpeg')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->post('/api/psychologist/profile/photo', ['photo' => $this->image('portrait.jpg')], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    public function test_replacement_deletes_only_the_previous_managed_photo(): void
    {
        Storage::fake('public');
        $psychologist = Psychologist::factory()->create();
        $oldPath = 'psychologists/'.$psychologist->id.'/123e4567-e89b-42d3-a456-426614174000.jpg';
        Storage::disk('public')->put($oldPath, 'old image');
        $psychologist->update(['photo' => $oldPath]);

        $this->actingAs($psychologist->user, 'sanctum')
            ->post('/api/psychologist/profile/photo', ['photo' => $this->image('replacement.jpg')], ['Accept' => 'application/json'])
            ->assertOk();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($psychologist->refresh()->photo);
    }

    public function test_replacement_never_deletes_an_unmanaged_path(): void
    {
        Storage::fake('public');
        $psychologist = Psychologist::factory()->create();
        $unmanagedPath = 'psychologists/imported/legacy-photo.jpg';
        Storage::disk('public')->put($unmanagedPath, 'legacy image');
        $psychologist->update(['photo' => $unmanagedPath]);

        $this->actingAs($psychologist->user, 'sanctum')
            ->post('/api/psychologist/profile/photo', ['photo' => $this->image('replacement.jpg')], ['Accept' => 'application/json'])
            ->assertOk();

        Storage::disk('public')->assertExists($unmanagedPath);
    }

    public function test_upload_is_scoped_to_the_authenticated_psychologist(): void
    {
        Storage::fake('public');
        $first = Psychologist::factory()->create();
        $firstPath = Psychologist::PHOTO_DIRECTORY.'/123e4567-e89b-42d3-a456-426614174000.jpg';
        Storage::disk('public')->put($firstPath, 'first image');
        $first->update(['photo' => $firstPath]);
        $second = Psychologist::factory()->create();

        $this->actingAs($second->user, 'sanctum')
            ->post('/api/psychologist/profile/photo', ['photo' => $this->image('second.jpg')], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame($firstPath, $first->refresh()->photo);
        Storage::disk('public')->assertExists($firstPath);
        $this->assertNotNull($second->refresh()->photo);
    }

    public function test_resources_preserve_safe_legacy_urls_without_exposing_server_paths(): void
    {
        $psychologist = Psychologist::factory()->create(['photo' => 'https://images.example.test/psychologist.jpg']);

        $this->actingAs($psychologist->user, 'sanctum')
            ->getJson('/api/psychologist/profile')
            ->assertOk()
            ->assertJsonPath('data.photo', 'https://images.example.test/psychologist.jpg');
        $this->getJson('/api/public/psychologists')
            ->assertOk()
            ->assertJsonPath('data.0.photo', 'https://images.example.test/psychologist.jpg');

        $psychologist->update(['photo' => '/srv/private/portrait.jpg']);

        $this->actingAs($psychologist->user, 'sanctum')
            ->getJson('/api/psychologist/profile')
            ->assertOk()
            ->assertJsonPath('data.photo', null);
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, match (pathinfo($name, PATHINFO_EXTENSION)) {
            'jpg', 'jpeg' => base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQL/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AL//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AL//2Q=='),
            'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9+TXAAAAAASUVORK5CYII='),
            'webp' => base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/vuUAAA='),
        });
    }
}
