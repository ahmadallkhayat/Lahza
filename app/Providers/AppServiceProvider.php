<?php

namespace App\Providers;

use App\Services\Translation\GoogleProvider;
use App\Services\Translation\MyMemoryProvider;
use App\Services\Translation\TranslationProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Which translation backend the studio's "translate from English"
        // button uses. See config/translation.php for why the default cannot
        // do Kurdish.
        $this->app->bind(TranslationProvider::class, fn () => match (config('translation.provider')) {
            'google' => new GoogleProvider,
            default => new MyMemoryProvider,
        });
    }

    public function boot(): void
    {
        //
    }
}
