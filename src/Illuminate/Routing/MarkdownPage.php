<?php

namespace Illuminate\Routing;

class MarkdownPage
{
    /**
     * Create a new markdown page instance.
     *
     * @param  string  $uri
     * @param  string  $path
     * @param  array  $meta
     */
    public function __construct(
        public string $uri,
        public string $path,
        public array $meta = [],
        public ?string $name = null,
        public ?string $url = null,
    ) {
    }

    /**
     * Get the page title.
     *
     * @return string|null
     */
    public function __get($key)
    {
        return $this->meta[$key] ?? null;
    }

    /**
     * Determine if the page metadata contains the given key.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->meta[$key]);
    }
}
