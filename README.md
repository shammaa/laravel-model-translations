# Laravel Smart Model Translations

A professional multilingual translation system for Laravel using the **Table per Model** approach with enhanced query capabilities and **Flat JSON** support.

## Features

*   ✅ **Clean & Flat JSON:** Translatable fields appear directly in the model's JSON output (No nested arrays!).
*   ✅ **Auto-Joins:** Query translated fields directly without worrying about `join` or `whereHas`.
*   ✅ **Fluent API:** Clean methods like `translateTo()` and magic property access.
*   ✅ **Native Ordering:** Sort results by translated columns with built-in scopes.
*   ✅ **Performance:** Optimized database queries for multilingual data using SQL Joins.

## Installation

```bash
composer require shammaa/laravel-model-translations
```

## Setup

### 1. Prepare your Database
For an `Article` model, create an `article_translations` table:

```php
Schema::create('article_translations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('article_id')->constrained()->cascadeOnDelete();
    $table->string('locale')->index();
    
    // Translatable fields
    $table->string('title');
    $table->text('content');
});
```

### 2. Configure Models

**Main Model:**
```php
use Shammaa\LaravelModelTranslations\Traits\HasTranslations;

class Article extends Model
{
    use HasTranslations;

    protected $translatable = ['title', 'content'];
}
```

**Translation Model:**
```php
class ArticleTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['title', 'content', 'locale'];
}
```

---

## 🚀 The "Killer" Features

### 1. Flat JSON Output (API Ready)
One of the biggest pain points in translation packages is nested translation arrays. This package solves it by flattening the output automatically based on the current app locale.

**Astrotomic/Traditional Output:**
```json
{
    "id": 1,
    "translations": [{"locale": "ar", "title": "عنوان"}]
}
```

**Smart Model Translations Output (Single JSON):**
```json
{
    "id": 1,
    "status": "published",
    "title": "عنوان المقال", // Injected directly!
    "slug": "article-slug"
}
```

### 2. Smart Querying & Joins
Perform a single `leftJoin` to get everything in one database hit.

```php
// Gets articles and their current locale translations in ONE query
$articles = Article::withTranslation()->get();

// No N+1 queries when accessing $article->title in loops!
```

### 3. Native Attribute Access (Blade)
Access translated fields as if they were native columns:

```html
<h1>{{ $article->title }}</h1>
<p>{{ $article->content }}</p>
```

---

## Data Entry Methods

### 1. Simple Property Assignment
```php
app()->setLocale('ar');
$article->title = 'عنوان المقال'; // Saved to translation table
$article->save();
```

### 2. Bulk Multi-locale Array
```php
$article->translateTo([
    'ar' => ['title' => 'عنوان عربي'],
    'en' => ['title' => 'English Title']
])->save();
```

---

## Advanced Scopes

#### Order by Translated Column
```php
$articles = Article::orderByTranslation('title', 'asc')->get();
```

#### Find by Translated Slug
```php
$article = Article::findByTranslatedSlugOrFail($slug, 'ar');
```

## License
MIT

## Author
**Shadi Shammaa**
