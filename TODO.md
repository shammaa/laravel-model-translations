# Laravel Model Translations - TODO & Future Improvements

## Version 2.0.0 (Major Release)

### 🎯 High Priority

#### 1. Enhanced JSON String Support
**Status:** Partially Implemented (v1.4.0)
**Remaining Work:**
- Add dedicated method `fillTranslationsFromJson(string $json)`
- Support nested JSON structures
- Add validation for malformed JSON with helpful error messages

```php
public function fillTranslationsFromJson(string $json): self
{
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidTranslationDataException(
            "Invalid JSON provided: " . json_last_error_msg()
        );
    }
    
    return $this->fillTranslations($data);
}
```

#### 2. Auto-Slug Generation
**Status:** Not Implemented
**Priority:** High
**Description:** Add built-in slug generation within the translation trait itself

```php
protected bool $autoGenerateSlug = true;
protected string $slugSourceField = 'name';

protected function generateSlugIfNeeded(string $locale, array $attributes): array
{
    if (!$this->autoGenerateSlug) {
        return $attributes;
    }
    
    if (empty($attributes['slug']) && !empty($attributes[$this->slugSourceField])) {
        $attributes['slug'] = Str::slug($attributes[$this->slugSourceField]);
    }
    
    return $attributes;
}
```

#### 3. Better Exception Handling
**Status:** Basic implementation exists
**Improvements Needed:**
- Create dedicated exception classes
- Add context to error messages (locale, field, model)
- Implement rollback on partial failures

```php
// New Exception Classes needed:
- TranslationSaveException
- InvalidTranslationDataException
- MissingLocaleException
- InvalidFieldException
```

---

### 🔧 Medium Priority

#### 4. Bulk Operations Support
Add methods for bulk translation operations:

```php
public function bulkUpdateTranslations(array $data): self
public function bulkDeleteTranslations(array $locales): self
public function copyTranslation(string $fromLocale, string $toLocale): self
```

#### 5. Translation Versioning
Keep history of translation changes:
- Add `translation_versions` table
- Track who changed what and when
- Add `revertTranslation($locale, $version)` method

#### 6. Translation Validation Rules
Allow models to define validation rules per locale:

```php
protected array $translationRules = [
    'name' => 'required|min:3|max:255',
    'description' => 'nullable|min:10',
];

public function validateTranslations(): bool
```

---

### 💡 Low Priority / Nice to Have

#### 7. Translation Events
Dispatch events for translation operations:
- `TranslationCreating`, `TranslationCreated`
- `TranslationUpdating`, `TranslationUpdated`
- `TranslationDeleting`, `TranslationDeleted`

#### 8. Translation Observers
Support for dedicated translation observers:
```php
class CategoryTranslationObserver
{
    public function created(CategoryTranslation $translation) {}
    public function updated(CategoryTranslation $translation) {}
}
```

#### 9. Translation Export/Import
Add helpers for translation data portability:
```php
public function exportTranslations(string $format = 'json'): string
public function importTranslations(string $data, string $format = 'json'): self
```

#### 10. Smart Translation Sync
Detect missing translations and suggest auto-translation:
```php
public function getMissingTranslations(): array
public function suggestTranslations(string $provider = 'google'): array
```

---

## Performance Optimizations

### 11. Eager Loading Improvements
- Auto-detect when to eager load translations
- Smart query optimization based on usage patterns

### 12. Caching Layer
- Add optional Redis/Memcached support
- Cache translation results per locale
- Invalidate cache on updates

---

## Developer Experience

### 13. Better Documentation
- [ ] Add interactive examples
- [ ] Create video tutorials
- [ ] Add troubleshooting guide
- [ ] Document all edge cases

### 14. Testing Suite
- [ ] Add comprehensive unit tests
- [ ] Add integration tests
- [ ] Add performance benchmarks
- [ ] Test with different Laravel versions

### 15. IDE Support
- [ ] Add PHPDoc annotations for magic methods
- [ ] Create IDE helper file
- [ ] Add Laravel IDE Helper integration

---

## Community Requests

Track feature requests from GitHub issues here:
- [ ] Support for polymorphic translations
- [ ] Multi-tenant translation support
- [ ] GraphQL integration
- [ ] REST API helpers

---

## Breaking Changes Planned for v2.0

1. Minimum Laravel version: 10.0
2. Minimum PHP version: 8.2
3. Rename `translateTo()` to `setTranslations()` for clarity
4. Change default behavior for empty translations

---

**Last Updated:** 2026-01-02
**Current Stable Version:** v1.4.0
**Next Planned Release:** v1.5.0 (Minor improvements)
**Major Release:** v2.0.0 (Q2 2026)
