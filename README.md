# Laravel Auto Translate

Automatic translation package for Laravel models using DeepL API. {{ THIS PACKAGE STILL IN TEST PROCESS. }}

## Installation

```bash
composer require northlab/laravel-auto-translate
```

## Configuration

1. Publish the configuration and migrations:
```bash
php artisan vendor:publish --provider="NorthLab\AutoTranslate\AutoTranslateServiceProvider"
```

2. Add your DeepL API key to your .env file:
```env
DEEPL_API_KEY=your-api-key
```

3. Run the migrations:
```bash
php artisan migrate
```

## Usage

1. Implement the Translatable interface and use the HasAutoTranslations trait in your model:

```php
use NorthLab\AutoTranslate\Contracts\Translatable;
use NorthLab\AutoTranslate\Traits\HasAutoTranslations;
```
## For Laravel 11 
```php
#[ObservedBy([UserObserver::class])]
class Post extends Model implements Translatable
{
    use HasAutoTranslations;

    protected array $translatable = ['title', 'content'];

    protected string $sourceLanguage = 'Column containing source language'

    public function getTranslatableAttributes(): array
    {
        return $this->translatable;
    }

    public function getSourceLanguageAttribute(): string
    {
        return $this->source_language;
    }

}
```
## For Laravel 10 ...
```php

class Post extends Model implements Translatable
{
    use HasAutoTranslations;

    protected array $translatable = ['title', 'content'];

    protected string $sourceLanguage = 'Column containing source language'

    public function getTranslatableAttributes(): array
    {
        return $this->translatable;
    }

    public function getSourceLanguageAttribute(): string
    {
        return $this->source_language;
    }

    protected static function boot()
    {
       Post::observe(TranslatableModelObserver::class);
    }
}
```
2. The translations will be automatically processed when the model is saved.

3. Skip translation:

```php
$post->withoutAutoTranslation()->save();
```
4. Updates

It will be constantly updated, taking into account its dependencies and features that can be added.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
