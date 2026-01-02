# Laravel Smart Model Translations

A professional multilingual translation system for Laravel using the **Table per Model** approach with enhanced query capabilities.

## Features

*   ✅ **Auto-Joins:** Query translated fields directly without worrying about `join` or `whereHas`.
*   ✅ **Fluent API:** Clean methods like `translateTo()` and magic property access.
*   ✅ **Performance:** Optimized database queries for multilingual data.
*   ✅ **Native Ordering:** Sort results by translated columns with built-in scopes.
*   ✅ **Flexible Setup:** Easy integration with existing models and professional trait implementation.

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

## Data Entry Methods

This package offers three flexible methods for handling translations to fit any workflow.

### 1. Simple Property Assignment (No Arrays)
The most intuitive way. Just assign values to translatable properties as if they were normal model attributes. The library automatically detects the current application locale and directs the data to the correct translation table.

**Best for:** Single-language forms or simple updates.

```php
// Assuming current locale is 'ar'
app()->setLocale('ar');

$article = new Article();
$article->status = 'active';      // Saved to main 'articles' table
$article->title = 'عنوان المقال';  // Automatically routed to 'article_translations'
$article->save();
```

### 2. Specific Locale Array
Save translations for a specific language explicitly, regardless of the current application locale.

**Best for:** Programmatic updates or background tasks.

```php
$article->translateTo([
    'title'   => 'English Title',
    'content' => 'High-performance content'
], 'en')->save();
```

### 3. Bulk Multi-locale Array
Handle multiple languages in a single call. The library intelligently parses the nested arrays in two different formats.

**Best for:** Admin dashboards with multi-language tabs.

**Format A: Locale-first (Traditional)**
```php
$article->translateTo([
    'ar' => [
        'title' => 'عنوان عربي',
        'content' => 'محتوى عربي'
    ],
    'en' => [
        'title' => 'English Title',
        'content' => 'English content'
    ]
])->save();
```

**Format B: Attribute-first (Smart)**
This format is particularly useful when working with slug generators or specialized input fields.
```php
$article->translateTo([
    'title' => [
        'ar' => 'عنوان عربي',
        'en' => 'English Title'
    ],
    'content' => [
        'ar' => 'محتوى عربي',
        'en' => 'English content'
    ]
])->save();
```

### 4. Smart Mass Assignment
You can use standard Laravel methods like `create()` or `update()`. The library will filter translatable fields automatically.

```php
Article::create([
    'status' => 'published',
    'title'  => 'New Smart Article' // Automatically saved for current locale
]);
```

---

### Smart Querying (The "Killer" Feature)

Unlike other packages, you don't need to manually join tables or use complex `whereHas` queries.

#### 1. Smart Join (Performance)
The `withTranslation()` scope performs a single `leftJoin` on the database level. This is much faster than `with('translations')` for large lists when you only need the current locale.

```php
// Gets articles and their current locale translations in ONE query
$articles = Article::withTranslation()->get();
```

#### 2. Automatic Filtering
```php
// Search title in current language
$articles = Article::whereTranslation('title', 'like', '%Laravel%')->get();
```

#### 3. Smart Ordering
```php
// Order by the translated title (not the ID or main table)
$articles = Article::orderByTranslation('title', 'asc')->get();
```

#### 4. Filter by Missing Translations
```php
// Find models that don't have a translation for Arabic
$missingAr = Article::emptyTranslation('ar')->get();
```

## Advanced Configuration

If your model or table names don't follow the defaults, you can override them:

```php
class Article extends Model
{
    use HasTranslations;

    protected $translatable = ['title'];
    
    // Optional Overrides
    protected $translationModel = \App\Models\Translations\ArticleTrans::class;
    protected $translationForeignKey = 'art_id';
    protected $localeColumn = 'lang';
}
```

## License
MIT

## Author
**Shadi Shammaa**
