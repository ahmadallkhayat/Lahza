<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Translation\TranslationFailed;
use App\Services\Translation\TranslationProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Fills the other languages from the English content.
 *
 * Reports per language rather than all-or-nothing, so a provider that can do
 * Arabic but not Kurdish fills Arabic and says plainly why Kurdish was left
 * alone. Nothing is written into a field on a doubtful translation.
 */
class TranslateController extends Controller
{
    public function __invoke(Request $request, TranslationProvider $provider): JsonResponse
    {
        $codes = array_keys(config('templates.locales'));

        $data = $request->validate([
            'source' => ['required', Rule::in($codes)],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => [Rule::in($codes)],
            'texts' => ['required', 'array', 'min:1', 'max:'.config('translation.max_strings')],
            'texts.*' => ['required', 'string', 'max:'.config('translation.max_chars')],
        ]);

        $translations = [];
        $declined = [];

        foreach (array_unique($data['targets']) as $target) {
            if ($target === $data['source']) {
                continue;
            }

            if (! $provider->supports($target)) {
                $declined[$target] = $this->whyNot($provider->name(), $target);

                continue;
            }

            try {
                $translations[$target] = $provider->translate($data['texts'], $data['source'], $target);
            } catch (TranslationFailed $e) {
                $declined[$target] = $e->getMessage();
            } catch (\Throwable $e) {
                // Network trouble and the like: tell the studio something
                // useful without leaking internals.
                Log::warning('Translation request failed.', ['target' => $target, 'error' => $e->getMessage()]);
                $declined[$target] = 'Could not reach '.$provider->name().'.';
            }
        }

        return response()->json([
            'provider' => $provider->name(),
            'translations' => $translations,
            'declined' => $declined,
        ]);
    }

    /**
     * Short enough to read at a glance. The full explanation of why the default
     * provider cannot do Kurdish lives in config/translation.php, where whoever
     * is configuring it will be looking.
     */
    private function whyNot(string $provider, string $target): string
    {
        $label = config("templates.locales.{$target}.label", $target);

        return "{$label} needs a translation key — see config/translation.php.";
    }
}
