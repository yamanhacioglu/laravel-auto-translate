<?php

namespace NorthLab\AutoTranslate\Traits;

use NorthLab\AutoTranslate\Jobs\TranslateModelJob;
use NorthLab\AutoTranslate\Observers\TranslatableModelObserver;
use Spatie\Translatable\HasTranslations;

trait HasAutoTranslations
{
    use HasTranslations;

    public static function bootHasAutoTranslations()
    {
        static::observe(TranslatableModelObserver::class);
    }

    public function shouldAutoTranslate(): bool
    {
        return true;
    }

    public function queueTranslation(): void
    {
        if ($this->shouldAutoTranslate()) {
            TranslateModelJob::dispatch($this);
        }
    }

    public function translate(): void
    {
        app(TranslationService::class)->handleTranslation($this);
    }
}
