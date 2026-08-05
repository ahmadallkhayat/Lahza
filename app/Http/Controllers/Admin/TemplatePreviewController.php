<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Template;
use App\Support\BaseTemplates;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;

/**
 * Renders the invitation as it would look with the values currently in the
 * form — before any of them are saved.
 *
 * It renders the real base template, not a mock-up, so the preview cannot drift
 * from what a guest eventually sees: same markup, same reveal sequence, same
 * JavaScript. Nothing is persisted; the Template and Invitation exist only for
 * the duration of the request.
 */
class TemplatePreviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'base_template' => ['required', Rule::in(array_keys(BaseTemplates::available()))],
            'palette' => ['array'],
            'palette.*' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settings' => ['array'],
            'settings.*' => ['nullable', 'numeric'],

            // Media is referenced, never fetched by us. A file chosen but not
            // yet uploaded arrives as a blob: URL the browser made, which only
            // resolves inside that same browser — hence the strict pattern
            // rather than accepting any URL, which would let a crafted value
            // put arbitrary content in the frame.
            'intro_video' => ['nullable', 'string', 'max:2048', 'regex:#^(blob:https?://|/storage/)#'],
            'music' => ['nullable', 'string', 'max:2048', 'regex:#^(blob:https?://|/storage/)#'],

            'locale' => ['nullable', Rule::in(array_keys(config('templates.locales')))],
        ]);

        $template = $this->transientTemplate($data);
        $invitation = $this->sampleInvitation($template, $data['locale'] ?? 'ar');

        App::setLocale($invitation->locale());

        return response()
            ->view($template->viewName(), compact('invitation', 'template'))
            // The preview is a scratch render of unsaved values; it should never
            // be cached, indexed, or embedded anywhere but this form.
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function transientTemplate(array $data): Template
    {
        return new Template([
            'name' => 'Preview',
            'base_template' => $data['base_template'],
            'palette' => array_filter($data['palette'] ?? [], fn ($v) => filled($v)),
            'settings' => array_filter($data['settings'] ?? [], fn ($v) => $v !== null && $v !== ''),
            'intro_video' => $data['intro_video'] ?? null,
            'music' => $data['music'] ?? null,
        ]);
    }

    /**
     * Real content where there is any, so the preview shows the kind of text
     * that will actually sit in the layout. Falls back to a stand-in for a
     * template that has no invitations yet.
     */
    private function sampleInvitation(Template $template, string $locale): Invitation
    {
        // Prefer an invitation already built on this design, so the preview
        // shows text of the length and script that really sits in this layout.
        $invitation = Invitation::query()
            ->whereRelation('template', 'base_template', $template->base_template)
            ->latest()
            ->first()
            ?? Invitation::latest()->first()
            ?? $this->standIn($locale);

        if (! $invitation->hasLocale($locale)) {
            $locale = $invitation->defaultLocale();
        }

        // Point it at the unsaved template so the form's colours and timings are
        // what render, not whatever is stored.
        $invitation->setRelation('template', $template);

        return $invitation->renderIn($locale);
    }

    private function standIn(string $locale): Invitation
    {
        $text = [
            'ar' => ['hosts' => 'تتشرف عائلة السالم وعائلة الحسن بدعوتكم', 'venue' => 'قاعة بابل', 'city' => 'بغداد، العراق'],
            'ku' => ['hosts' => 'خێزانی ساڵم بانگهێشتتان دەکەن', 'venue' => 'هۆڵی بابل', 'city' => 'بەغدا، عێراق'],
            'en' => ['hosts' => 'The Al-Salem family requests the honour of your presence', 'venue' => 'Babylon Hall', 'city' => 'Baghdad, Iraq'],
        ][$locale] ?? [];

        $invitation = new Invitation([
            'slug' => 'preview',
            'locales' => [$locale],
            'default_locale' => $locale,
            'groom_ar' => 'أَحْمَد',
            'bride_ar' => 'سِدْرَة',
            'groom_latin' => 'Ahmad',
            'bride_latin' => 'Sidra',
            'hosts' => [$locale => $text['hosts'] ?? ''],
            'venue_name' => [$locale => $text['venue'] ?? ''],
            'venue_city' => [$locale => $text['city'] ?? ''],
            'starts_at' => CarbonImmutable::now(config('templates.default_timezone'))->addMonths(4)->setTime(19, 0),
            'timezone' => config('templates.default_timezone'),
            'duration_hours' => 5,
            'programme' => [$locale => [
                ['time' => $locale === 'en' ? '7:00' : '٧:٠٠', 'title' => $text['venue'] ?? 'Reception'],
            ]],
            'phone' => '+9647700000000',
            'show_verse' => true,
            'published' => true,
        ]);

        // Route helpers need a key; it is never saved.
        $invitation->id = 0;

        return $invitation;
    }
}
