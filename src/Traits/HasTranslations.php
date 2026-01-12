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
        
        // Check pending translations first (useful during saving event or before save)
        if (isset($this->pendingTranslations[$locale][$key])) {
            return $this->pendingTranslations[$locale][$key];
        }

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
                // Also check pending for fallback locale
                if (isset($this->pendingTranslations[$fallback][$key])) {
                    return $this->pendingTranslations[$fallback][$key];
                }

                $translation = $this->translations
                    ->where($this->getLocaleColumn(), $fallback)
                    ->first();
                    
                return $translation ? $translation->getAttribute($key) : null;
            }
        }

        return null;
    }

    /**
     * Get pending translation (for compatibility with slug packages)
     */
    public function getPendingTranslation(string $key, string $locale): ?string
    {
        return $this->pendingTranslations[$locale][$key] ?? null;
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
     * Scope: Find by slug in translations table.
     * Searches in all locales by default, or in a specific locale if provided.
     * 
     * Usage:
     *   Article::whereTranslatedSlug('my-article-slug')->first();
     *   Article::whereTranslatedSlug('my-article-slug', 'ar')->first();
     */
    public function scopeWhereTranslatedSlug(Builder $query, string $slug, ?string $locale = null, string $slugColumn = 'slug'): Builder
    {
        return $query->whereHas('translations', function ($q) use ($slug, $locale, $slugColumn) {
            $q->where($slugColumn, $slug);
            
            if ($locale !== null) {
                $q->where($this->getLocaleColumn(), $locale);
            }
        });
    }

    /**
     * Static helper: Find model by translated slug.
     * Returns first matching model or null.
     * 
     * Usage:
     *   $article = Article::findByTranslatedSlug('my-article-slug');
     *   $article = Article::findByTranslatedSlug('my-article-slug', 'ar');
     */
    public static function findByTranslatedSlug(string $slug, ?string $locale = null, string $slugColumn = 'slug'): ?static
    {
        return static::whereTranslatedSlug($slug, $locale, $slugColumn)->first();
    }

    /**
     * Static helper: Find model by translated slug or fail with 404.
     * 
     * Usage:
     *   $article = Article::findByTranslatedSlugOrFail('my-article-slug');
     */
    public static function findByTranslatedSlugOrFail(string $slug, ?string $locale = null, string $slugColumn = 'slug'): static
    {
        $model = static::findByTranslatedSlug($slug, $locale, $slugColumn);
        
        if (!$model) {
            abort(404);
        }
        
        return $model;
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
        // Auto-decode JSON strings if they are passed as values
        foreach ($data as $key => $value) {
            if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data[$key] = $decoded;
                }
            }
        }

        if ($locale === null && count($data) > 0) {
            $firstKey = (string) key($data);
            $firstValue = reset($data);

            if (is_array($firstValue)) {
                // Determine if this is attribute-first: ['name' => ['ar' => '...', 'en' => '...']]
                // or locale-first: ['ar' => ['name' => '...'], 'en' => [...]]
                if ($this->isTranslationAttribute($firstKey)) {
                    foreach ($data as $attribute => $translations) {
                        if (is_array($translations)) {
                            foreach ($translations as $loc => $val) {
                                $this->pendingTranslations[$loc][$attribute] = $val;
                            }
                        } else {
                            // If a single value is mixed in, use current locale
                            $loc = app()->getLocale();
                            $this->pendingTranslations[$loc][$attribute] = $translations;
                        }
                    }
                    return $this;
                }

                // Standard locale-first format: ['ar' => ['name' => '...'], 'en' => [...]]
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
            // Skip locales where all translatable fields are empty
            $hasContent = false;
            foreach ($this->getTranslatableAttributes() as $field) {
                if (isset($attributes[$field]) && !empty($attributes[$field])) {
                    $hasContent = true;
                    break;
                }
            }

            if (!$hasContent) {
                continue; // Skip this locale entirely
            }

            $this->translations()->updateOrCreate(
                [$this->getLocaleColumn() => $locale],
                $attributes
            );
        }

        $this->pendingTranslations = [];
    }
}
