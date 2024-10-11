<?php

namespace NorthLab\AutoTranslate\Services;

use NorthLan\AutoTranslate\Models\Deepl.php
use DeepL\Translator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Illuminate\Database\Eloquent\Model;

class TranslationService
{
    protected ?Translator $translator = null;
    protected array $supportedLanguages;
    protected ?string $sourceLanguage = null;
    protected array $translatableAttributes;
    protected Model $model;
    protected string $logFile;
    protected array $processedTranslations = [];

    public function __construct()
    {
        $this->setupLogging();
        $this->supportedLanguages = LaravelLocalization::getSupportedLanguagesKeys();
        $this->log('TranslationService initialized with languages: ' . implode(', ', $this->supportedLanguages));
    }


    protected function setupLogging(): void
    {
        $this->logFile = storage_path('logs/translation-service.log');

        // Ensure logs directory exists
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }

        // Ensure log file exists and is writable
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0644);
        }
    }

    /**
     * Custom logging method
     *
     * @param string $message The message to log
     * @param string $level The log level (info, error, warning, etc.)
     * @param array $context Additional context data
     */
    protected function log(string $message, string $level = 'info', array $context = []): void
    {
        $dateTime = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logMessage = "[$dateTime] [$level] $message$contextStr" . PHP_EOL;

        try {
            // Write to our custom log file
            file_put_contents($this->logFile, $logMessage, FILE_APPEND);

            // Also use Laravel's logging
            match($level) {
                'error' => Log::error($message, $context),
                'warning' => Log::warning($message, $context),
                'debug' => Log::debug($message, $context),
                default => Log::info($message, $context)
            };
        } catch (\Exception $e) {
            error_log("Translation Service Error: Failed to write to log file - " . $e->getMessage());
        }
    }

    protected function initializeTranslator(): void
    {
        $this->log('Starting translator initialization');

        if ($this->translator !== null) {
            $this->log('Translator already initialized');
            return;
        }

        try {
            $apiKey = $this->getDeeplApiKey();
            if (empty($apiKey)) {
                $this->log('DeepL API key is empty or not found', 'error');
                return;
            }
            $this->translator = new Translator($apiKey);
            $this->log('Translator successfully initialized');
        } catch (\Exception $e) {
            $this->log(
                'Failed to initialize DeepL translator: ' . $e->getMessage(),
                'error',
                ['trace' => $e->getTraceAsString()]
            );
        }
    }

    protected function getDeeplApiKey(): ?string
    {
        $this->log('Attempting to retrieve DeepL API key');

        try {
            $config = ApiConfig::where('key', 'deepl')->first();

            if (!$config) {
                $this->log('DeepL configuration not found in database', 'error');
                return null;
            }

            if (empty($config->api_key)) {
                $this->log('DeepL API key is empty in database', 'error');
                return null;
            }

            $this->log('DeepL API key successfully retrieved');
            return $config->api_key;
        } catch (\Exception $e) {
            $this->log(
                'Error retrieving DeepL API key: ' . $e->getMessage(),
                'error',
                ['trace' => $e->getTraceAsString()]
            );
            return null;
        }
    }


    public function handleTranslation(Model $model): void
    {
        $this->log('Starting translation process', 'info', [
            'model_class' => get_class($model),
            'model_id' => $model->getKey()
        ]);

        $this->initializeTranslator();

        if ($this->translator === null) {
            $this->log('Translation process aborted: Translator is not initialized', 'warning');
            return;
        }

        $this->model = $model;

        try {
            if (!method_exists($model, 'getTranslatableAttributes')) {
                $this->log('Model missing getTranslatableAttributes method', 'error', [
                    'model_class' => get_class($model)
                ]);
                throw new \RuntimeException('Model must implement getTranslatableAttributes method');
            }

            $this->translatableAttributes = $model->getTranslatableAttributes();
            $this->log('Translatable attributes loaded', 'info', [
                'attributes' => $this->translatableAttributes
            ]);

            $this->sourceLanguage = $this->getModelSourceLanguage($model);
            $this->translateAttributes();

        } catch (\Exception $e) {
            $this->log(
                'Translation process failed: ' . $e->getMessage(),
                'error',
                [
                    'trace' => $e->getTraceAsString(),
                    'model_class' => get_class($model),
                    'model_id' => $model->getKey()
                ]
            );
            throw $e;
        }
    }

    protected function getModelSourceLanguage(Model $model): string
    {
        if (!method_exists($model, 'getSourceLanguageAttribute')) {
            $this->log('Model missing getSourceLanguageAttribute method', 'error', [
                'model_class' => get_class($model)
            ]);
            throw new \RuntimeException('Model must implement getSourceLanguageAttribute method');
        }

        $sourceLanguageColumn = $model->getSourceLanguageAttribute();
        $sourceLanguage = $sourceLanguageColumn;

        if (empty($sourceLanguage)) {
            $this->log('Source language is empty', 'error', [
                'model_id' => $model->getKey()
            ]);
            throw new \RuntimeException('Source language not found in the model');
        }

        $this->log('Source language loaded', 'info', [
            'source_language' => $sourceLanguage
        ]);

        return $sourceLanguage;
    }

    protected function getSupportedLanguages(): array
    {
        Log::info('Retrieving supported languages');
        $languages = array_keys(LaravelLocalization::getSupportedLocales());
        Log::info('Supported languages retrieved', ['languages' => $languages]);
        return $languages;
    }

    protected function translateAttributes(): void
    {
        $this->log('Starting attributes translation');

        // Get existing translations
        $existingTranslations = $this->getExistingTranslations();
        $this->log('Existing translations', 'info', ['translations' => $existingTranslations]);

        // Get target languages (excluding those that already have translations)
        $targetLanguages = $this->getTargetLanguages($existingTranslations);
        $this->log('Target languages', 'info', ['languages' => $targetLanguages]);

        if (empty($targetLanguages)) {
            $this->log('No new languages to translate to');
            return;
        }

        $translationsChanged = false;

        foreach ($this->translatableAttributes as $attribute) {
            $sourceText = $this->getSourceText($attribute);

            if (empty($sourceText)) {
                $this->log('Empty source text for attribute', 'warning', ['attribute' => $attribute]);
                continue;
            }

            foreach ($targetLanguages as $targetLanguage) {
                // Skip if already processed
                $key = "{$attribute}_{$targetLanguage}";
                if (isset($this->processedTranslations[$key])) {
                    continue;
                }

                try {
                    $translatedText = $this->translateThis($sourceText, $targetLanguage);

                    if ($translatedText !== null) {
                        $this->setTranslation($attribute, $targetLanguage, $translatedText);
                        $this->processedTranslations[$key] = true;
                        $translationsChanged = true;
                    }
                } catch (\Exception $e) {
                    $this->log('Translation failed for attribute', 'error', [
                        'attribute' => $attribute,
                        'target_language' => $targetLanguage,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Only save if changes were made
        if ($translationsChanged) {
            try {
                $this->model->save();
                $this->log('Model saved successfully with new translations');
            } catch (\Exception $e) {
                $this->log('Failed to save model', 'error', ['error' => $e->getMessage()]);
                throw $e;
            }
        } else {
            $this->log('No new translations were added');
        }
    }
    protected function getExistingTranslations(): array
    {
        try {
            if (method_exists($this->model, 'getTranslations')) {
                return $this->model->getTranslations() ?? [];
            }

            return [];
        } catch (\Exception $e) {
            $this->log('Error getting existing translations', 'error', ['error' => $e->getMessage()]);
            return [];
        }
    }
    protected function getTargetLanguages(array $existingTranslations): array
    {
        return array_filter($this->supportedLanguages, function ($lang) use ($existingTranslations) {
            // Skip source language
            if ($lang === $this->sourceLanguage) {
                return false;
            }

            // Skip if any attribute already has a translation in this language
            foreach ($this->translatableAttributes as $attribute) {
                if (isset($existingTranslations[$attribute][$lang])) {
                    return false;
                }
            }

            return true;
        });
    }
    protected function getSourceText(string $attribute): ?string
    {
        try {
            return $this->model->getTranslation($attribute, $this->sourceLanguage);
        } catch (\Exception $e) {
            $this->log('Error getting source text', 'error', [
                'attribute' => $attribute,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    protected function setTranslation(string $attribute, string $targetLanguage, string $translatedText): void
    {
        try {
            if ($attribute === 'slug') {
                $translatedText = Str::slug($translatedText);
            }

            $this->model->setTranslation($attribute, $targetLanguage, $translatedText);

            $this->log('Translation set successfully', 'info', [
                'attribute' => $attribute,
                'language' => $targetLanguage
            ]);
        } catch (\Exception $e) {
            $this->log('Failed to set translation', 'error', [
                'attribute' => $attribute,
                'language' => $targetLanguage,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function translateThis(string $text, string $targetLanguage): ?string
    {
        if ($this->translator === null) {
            $this->log('Translator not initialized', 'warning');
            return null;
        }

        try {
            $targetLang = $targetLanguage === 'en' ? 'en-US' : $targetLanguage;

            $result = $this->translator->translateText(
                $text,
                null,
                $targetLang
            );

            return $result->text;
        } catch (\Exception $e) {
            $this->log('Translation API call failed', 'error', [
                'target_language' => $targetLanguage,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

}
