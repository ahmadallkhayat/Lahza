<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploads are unlimited in production, which is easy to break by accident:
 * a bare `max:` rule throws, and the form hint reads from the same config.
 */
class UploadSizeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->actingAs(User::factory()->create());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Big Media',
            'base_template' => 'luxury-wedding-v1',
            'derive_palette' => 0,
        ], $overrides);
    }

    public function test_a_large_video_is_accepted_when_unlimited(): void
    {
        config(['templates.max_video_kb' => null]);

        // 300MB — comfortably past the old 100000KB ceiling.
        $video = UploadedFile::fake()->create('intro.mp4', 300_000, 'video/mp4');

        $this->post('/admin/templates', $this->payload(['intro_video' => $video]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('templates', ['name' => 'Big Media']);
    }

    public function test_a_large_music_file_is_accepted_when_unlimited(): void
    {
        config(['templates.max_music_kb' => null]);

        $music = UploadedFile::fake()->create('score.mp3', 200_000, 'audio/mpeg');

        $this->post('/admin/templates', $this->payload(['music' => $music]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('templates', ['name' => 'Big Media']);
    }

    /** Setting a number must still be honoured — unlimited is a choice, not a removal. */
    public function test_a_configured_limit_still_rejects_an_oversized_file(): void
    {
        config(['templates.max_video_kb' => 1000]);

        $video = UploadedFile::fake()->create('intro.mp4', 5_000, 'video/mp4');

        $this->post('/admin/templates', $this->payload(['intro_video' => $video]))
            ->assertSessionHasErrors('intro_video');
    }

    /** The type check must survive the limit becoming optional. */
    public function test_the_wrong_file_type_is_still_rejected_when_unlimited(): void
    {
        config(['templates.max_video_kb' => null]);

        $notAVideo = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $this->post('/admin/templates', $this->payload(['intro_video' => $notAVideo]))
            ->assertSessionHasErrors('intro_video');
    }

    public function test_the_form_renders_the_unlimited_hint(): void
    {
        config([
            'templates.max_video_kb' => null,
            'templates.max_music_kb' => null,
        ]);

        $this->get('/admin/templates/create')
            ->assertOk()
            ->assertSee('no size limit');
    }
}
