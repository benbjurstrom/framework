<?php

namespace Illuminate\Routing;

use InvalidArgumentException;

class MarkdownException extends InvalidArgumentException
{
    /**
     * Create a new exception for an invalid markdown path.
     *
     * @param  string  $path
     * @return static
     */
    public static function invalidPath($path)
    {
        return new static("Markdown path [{$path}] must be a relative directory within [resources/markdown].");
    }

    /**
     * Create a new exception for a missing markdown directory.
     *
     * @param  string  $path
     * @return static
     */
    public static function missingDirectory($path)
    {
        return new static("Markdown path [{$path}] does not exist within [resources/markdown].");
    }
}
