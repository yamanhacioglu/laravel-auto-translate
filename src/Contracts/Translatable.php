<?php

namespace NorthLab\AutoTranslate\Contracts;

interface Translatable
{
    public function getTranslatableAttributes(): array;
    public function getSourceLanguageAttribute(): string;
    public function shouldAutoTranslate(): bool;
}
