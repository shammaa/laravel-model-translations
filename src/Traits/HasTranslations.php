<?php

declare(strict_types=1);

namespace Shammaa\LaravelModelTranslations\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait HasTranslations
{
    /**
     * Boot the trait.
     */
    public static function bootHasTranslations(): void
    {
        static::saved(function (Model $model) {
            /** @var HasTranslations $model */
            $model->savePendingTranslations();
        });
    }

    /**
     * Get the translations relationship.
     */
    public function translations(): HasMany
    {
        return $this->hasMany($this->getTranslationModelName(), $this->getTranslationRelationKey());
    }

    /**
     * Get the translation model name.
     */
    protected function getTranslationModelName(): string
    {
        return property_exists($this, 'translationModel') 
            ? $this->translationModel 
            : static::class . 'Translation';
    }

    /**
     * Get the foreign key for the translation relationship.
     */
    protected function getTranslationRelationKey(): string
    {
        return property_exists($this, 'translationForeignKey') 
            ? $this->translationForeignKey 
            : $this->getForeignKey();
    }

    /**
     * Pending translation attributes to be saved.
     */
    protected array $pendingTranslations = [];

    /**
     * Magic getter for translatable attributes.
     */
    public function __get($key)
    {
        if ($this->isTranslationAttribute($key)) {
            return $this->getTranslationValue($key);
        }

        return parent::__get($key);
    }

    /**
     * Magic setter for translatable attributes.
     */
    public function __set($key, $value)
    {
        if ($this->isTranslationAttribute($key)) {
            $this->translateTo([$key => $value]);
            return;
        }

        parent::__set($key, $value);
    }

    /**
     * Override setAttribute to handle translatable fields from fill() or create().
     */
    public function setAttribute($key, $value)
    {
        if ($this->isTranslationAttribute($key)) {
            $this->translateTo([$key => $value]);
            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Check if an attribute is translatable.
     */
    protected function isTranslationAttribute(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes());
    }

    /**
     * Get the translatable attributes.
     */
    protected function getTranslatableAttributes(): array
    {
        return property_exists($this, 'translatable') ? $this->translatable : [];
    }

    /**
     * Get the translation value for the current locale.
     */
    public function getTranslationValue(string $key, ?string $locale = null): mixed
    {
        $locale = $locale ?: app()->getLocale();
        
        $translation = $this->translations
            ->where($this->getLocaleColumn(), $locale)
            ->first();

        if ($translation) {
            return $translation->getAttribute($key);
        }

        // Fallback to default locale if enabled
        if (config('model-translations.fallback_enabled', true)) {
            $fallback = config('model-translations.default_locale', config('app.fallback_locale', 'en'));
            if ($locale !== $fallback) {
                $translation = $this->translations
                    ->where($this->getLocaleColumn(), $fallback)
                    ->first();
                    
                return $translation ? $translation->getAttribute($key) : null;
            }
        }

        return null;
    }

    /**
     * Get the locale column name.
     */
    protected function getLocaleColumn(): string
    {
        return property_exists($this, 'localeColumn') ? $this->localeColumn : 'locale';
    }

    /**
     * Scope: Smart join with translations table.
     */
    public function scopeWithTranslation(Builder $query, ?string $locale = null): Builder
    {
        $locale = $locale ?: app()->getLocale();
        $translationTable = (new ($this->getTranslationModelName()))->getTable();
        $mainTable = $this->getTable();
        $foreignKey = $this->getTranslationRelationKey();

        return $query->leftJoin($translationTable, function ($join) use ($translationTable, $mainTable, $foreignKey, $locale) {
            $join->on("{$mainTable}.id", '=', "{$translationTable}.{$foreignKey}")
                 ->where("{$translationTable}.{$this->getLocaleColumn()}", '=', $locale);
        })->select("{$mainTable}.*");
    }

    /**
     * Scope: Smart search in translations.
     */
    public function scopeWhereTranslation(Builder $query, string $column, $operator, $value = null, ?string $locale = null): Builder
    {
        $locale = $locale ?: app()->getLocale();
        $translationTable = (new ($this->getTranslationModelName()))->getTable();
        
        // Ensure joined
        if (!$this->isTableJoined($query, $translationTable)) {
            $this->scopeWithTranslation($query, $locale);
        }

        return $query->where("{$translationTable}.{$column}", $operator, $value);
    }

    /**
     * Check if a table is already joined in the query.
     */
    protected function isTableJoined(Builder $query, string $table): bool
    {
        $joins = $query->getQuery()->joins;
        if (!$joins) return false;

        foreach ($joins as $join) {
            if ($join->table === $table) return true;
        }

        return false;
    }

    /**
     * Scope: Order by translated column.
     */
    public function scopeOrderByTranslation(Builder $query, string $column, string $direction = 'asc', ?string $locale = null): Builder
    {
        $locale = $locale ?: app()->getLocale();
        $translationTable = (new ($this->getTranslationModelName()))->getTable();

        if (!$this->isTableJoined($query, $translationTable)) {
            $this->scopeWithTranslation($query, $locale);
        }

        return $query->orderBy("{$translationTable}.{$column}", $direction);
    }

    /**
     * Scope: Filter models that are missing translation for a locale.
     */
    public function scopeEmptyTranslation(Builder $query, ?string $locale = null): Builder
    {
        $locale = $locale ?: app()->getLocale();
        
        return $query->whereDoesntHave('translations', function ($q) use ($locale) {
            $q->where($this->getLocaleColumn(), $locale);
        });
    }

    /**
     * Check if a translation exists for a specific locale.
     */
    public function hasTranslation(?string $locale = null): bool
    {
        $locale = $locale ?: app()->getLocale();
        return $this->translations->where($this->getLocaleColumn(), $locale)->isNotEmpty();
    }

    /**
     * Get all available locales for this model.
     */
    public function getAvailableLocales(): array
    {
        return $this->translations->pluck($this->getLocaleColumn())->unique()->toArray();
    }

    /**
     * Set multiple translations. 
     * Now supports: 
     * 1. translateTo(['title' => 'Ar'], 'ar')
     * 2. translateTo(['ar' => ['title' => 'Ar'], 'en' => ['title' => 'En']])
     */
    public function translateTo(array $data, ?string $locale = null): self
    {
        if ($locale === null && count($data) > 0) {
            // If the first element is an array, we assume it's a multi-locale format: ['en' => [...]]
            $firstValue = reset($data);
            if (is_array($firstValue)) {
                return $this->fillTranslations($data);
            }
        }

        $locale = $locale ?: app()->getLocale();
        
        if (!isset($this->pendingTranslations)) {
            $this->pendingTranslations = [];
        }

        // Merge existing pending translations for this locale to avoid overwriting during Mass Assignment
        $this->pendingTranslations[$locale] = array_merge(
            $this->pendingTranslations[$locale] ?? [],
            $data
        );

        return $this;
    }

    /**
     * Fill translations from array [locale => [data]]
     */
    public function fillTranslations(array $translations): self
    {
        foreach ($translations as $locale => $data) {
            $this->translateTo($data, $locale);
        }

        return $this;
    }

    /**
     * Save pending translations.
     */
    protected function savePendingTranslations(): void
    {
        if (empty($this->pendingTranslations)) {
            return;
        }

        foreach ($this->pendingTranslations as $locale => $attributes) {
            $this->translations()->updateOrCreate(
                [$this->getLocaleColumn() => $locale],
                $attributes
            );
        }

        $this->pendingTranslations = [];
    }
}
