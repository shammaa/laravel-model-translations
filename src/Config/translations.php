<?php

return [
    /**
     * The default locale to use if none is provided.
     */
    'default_locale' => env('APP_LOCALE', 'en'),

    /**
     * The default column name for the locale.
     */
    'locale_column' => 'locale',

    /**
     * Fallback to default locale if translation is missing for current locale.
     */
    'fallback_enabled' => true,
];
