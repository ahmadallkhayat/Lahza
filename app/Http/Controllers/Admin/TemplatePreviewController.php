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
use Illuminate\Support\Facades\Validator;
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
        // Validated by hand rather than with $request->validate(), which
        // answers a failure with a 302 back to the form unless the caller
        // asked for JSON. fetch() follows that redirect and hands back the
        // admin page with status 200 — which the preview then rendered inside
        // its own frame, showing the studio a copy of the page it was already
        // looking at. A failure here must always be a status, never a redirect.
        $validator = Validator::make($this->normalised($request), [
            'base_template' => ['required', Rule::in(array_keys(BaseTemplates::available()))],
            'palette' => ['array'],
            'palette.*' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settings' => ['array'],

            // Not `numeric`, which is what this was: a design may declare a
            // setting that is a name rather than a number — an opening style,
            // say — and rejecting it took the whole preview down with a 422
            // for every theme built on that design.
            //
            // Deliberately loose about *value* as well as type. These arrive
            // mid-keystroke, so a half-typed "9" on its way to "900" must not
            // blank the frame the studio is watching. Nothing unsafe gets
            // through by being permissive here: Template::settings() keeps
            // only keys the design declares, casts its numbers with (int) and
            // (float), and falls a choice back to the default unless it is one
            // the design actually offers — so no string in this array can
            // reach the stylesheet or the page intact.
            'settings.*' => ['nullable', 'string', 'max:64'],

            // Media is referenced, never fetched by us. A file chosen but not
            // yet uploaded arrives as a blob: URL the browser made, which only
            // resolves inside that same browser — hence the strict pattern
            // rather than accepting any URL, which would let a crafted value
            // put arbitrary content in the frame.
            'intro_video' => ['nullable', 'string', 'max:2048', 'regex:#^(blob:https?://|/storage/)#'],
            'music' => ['nullable', 'string', 'max:2048', 'regex:#^(blob:https?://|/storage/)#'],

            'locale' => ['nullable', Rule::in(array_keys(config('templates.locales')))],
        ]);

        if ($validator->fails()) {
            return response(
                $validator->errors()->has('base_template')
                    ? 'Choose a base template to preview.'
                    : 'Those values cannot be previewed.',
                422
            )->header('Cache-Control', 'no-store');
        }

        $data = $validator->validated();

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
     * The request as one shape, whatever sent it.
     *
     * A browser's FormData sends every setting as a string; a caller posting
     * JSON may send real numbers. Rather than give the rule below a type
     * union — which would have to accept both and so could assert neither —
     * scalars are flattened to strings here and anything that is not a scalar
     * becomes null, so a nested array cannot slip past a rule that only knows
     * how to reject strings.
     *
     * @return array<string, mixed>
     */
    private function normalised(Request $request): array
    {
        $input = $request->all();

        if (is_array($input['settings'] ?? null)) {
            $input['settings'] = array_map(
                fn ($value) => is_scalar($value) ? (string) $value : null,
                $input['settings'],
            );
        }

        return $input;
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
            'groom_ar' => 'يُوسُف',
            'bride_ar' => 'لَيْلَى',
            'groom_latin' => 'Yusuf',
            'bride_latin' => 'Layla',
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
