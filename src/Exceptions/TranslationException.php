<?php

declare(strict_types=1);

namespace Shammaa\LaravelModelTranslations\Exceptions;

use Exception;

class TranslationException extends Exception
{
}

class ModelNotFoundException extends TranslationException
{
}

class LocaleNotSupportedException extends TranslationException
{
}
