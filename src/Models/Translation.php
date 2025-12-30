<?php

declare(strict_types=1);

namespace Shammaa\LaravelModelTranslations\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    public $timestamps = false;

    /**
     * Get the locale column name.
     */
    public function getLocaleColumn(): string
    {
        return 'locale';
    }
}
