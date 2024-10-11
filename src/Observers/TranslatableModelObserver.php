<?php

namespace NorthLab\AutoTranslate\Observers;

use NorthLab\AutoTranslate\Jobs\TranslateModelJob;
use NorthLab\AutoTranslate\Services\TranslationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class TranslatableModelObserver
{
    /**
     * Handle the Model "saved" event.
     */
    public function saved(Model $model): void
    {
        if (isset($model->skipTranslation) && $model->skipTranslation) {
            return;
        }

        if (config('auto-translate.queue_translations', true)) {
            dispatch(new TranslateModelJob($model));
        } else {
            try {
                app(TranslationService::class)->handleTranslation($model);
            } catch (\Exception $e) {
                Log::error('Auto translation failed for model ' . get_class($model), [
                    'model_id' => $model->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
