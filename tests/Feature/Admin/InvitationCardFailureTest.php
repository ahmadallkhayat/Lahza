<?php

namespace Tests\Feature\Admin;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use App\Services\OgImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

/**
 * The share card is drawn straight after the invitation is written, and it
 * reaches for things a server may not have: a writable storage/app/fonts, the
 * downloaded render faces, ffmpeg. Only the ffmpeg call was ever guarded, so a
 * failure anywhere else threw after the row had already been committed — the
 * studio saw a 500 for a save that had in fact succeeded, and then saved again.
 */
class InvitationCardFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /** Whatever the card does, the invitation is still created. */
    public function test_creating_survives_a_card_that_cannot_be_drawn(): void
    {
        $this->breakTheCardRenderer();

        $this->post('/admin/invitations', $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Invitation::count());
        $this->assertNull(Invitation::sole()->og_image);
    }

    /** And editing one still saves. */
    public function test_updating_survives_a_card_that_cannot_be_drawn(): void
    {
        $invitation = Invitation::factory()->create(['groom_ar' => 'قديم']);

        $this->breakTheCardRenderer();

        // Bound by slug, not id — Invitation::getRouteKeyName() says so.
        $this->put("/admin/invitations/{$invitation->slug}", [
            ...$this->payload(),
            'template_id' => $invitation->template_id,
            'groom_ar' => 'جديد',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('جديد', $invitation->fresh()->groom_ar);
    }

    /**
     * The real service, not a stand-in: a renderer that blows up mid-draw
     * returns null instead of throwing, and says why in the log.
     *
     * A face has to exist on disk first or generate() bails before it reaches
     * anything that can fail, which would make this test pass for the wrong
     * reason. The file is never read — Process is faked.
     */
    public function test_the_real_renderer_catches_and_logs_instead_of_throwing(): void
    {
        File::ensureDirectoryExists(storage_path('app/fonts'));
        File::put(storage_path('app/fonts/'.OgImageGenerator::FACES['Amiri']), 'not really a font');

        Process::fake(fn () => throw new RuntimeException('ffmpeg is not installed'));
        Log::spy();

        $invitation = Invitation::factory()->create();

        $result = app(OgImageGenerator::class)->generate($invitation);

        $this->assertNull($result);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'could not be drawn'))
            ->once();
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/fonts/'.OgImageGenerator::FACES['Amiri']));

        parent::tearDown();
    }

    /**
     * Stand in for every way the renderer can fail on a real box. The point is
     * not which one — it is that none of them reaches the studio.
     */
    private function breakTheCardRenderer(): void
    {
        $this->mock(OgImageGenerator::class, function ($mock) {
            $mock->shouldReceive('generate')
                ->andReturnUsing(function (Invitation $invitation) {
                    // The real service catches this and logs; the mock stands in
                    // for the service being unable to draw, so it does the same.
                    Log::warning('The invitation card could not be drawn.', [
                        'invitation' => $invitation->slug,
                        'error' => 'storage/app/fonts is not writable',
                    ]);

                    return null;
                });

            $mock->shouldReceive('forget')->andReturnNull();
        });
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'template_id' => Template::factory()->create()->id,
            'locales' => ['ar'],
            'default_locale' => 'ar',
            'groom_ar' => 'يُوسُف',
            'bride_ar' => 'لَيْلَى',
            // The form takes the day and the clock separately and joins them
            // into starts_at, so a test posting starts_at is not posting what
            // the studio posts.
            'date' => '2027-01-01',
            'time' => '19:00',
            'timezone' => 'Asia/Baghdad',
            'duration_hours' => 5,
        ];
    }
}
